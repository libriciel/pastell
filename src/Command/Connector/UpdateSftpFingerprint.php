<?php

declare(strict_types=1);

namespace Pastell\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use DepotConnecteur;
use Exception;
use GlaneurSFTP;
use Pastell\Command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnrecoverableException;

#[AsCommand(
    name: 'app:connector:update-sftp-fingerprint',
    description: 'Update SFTP fingerprints for DepotSFTP and GlaneurSFTP connectors',
)]
final class UpdateSftpFingerprint extends BaseCommand
{
    public const string DEPOT_SFTP = 'depot-sftp';
    public const string GLANEUR_SFTP = 'glaneur-sftp';

    public function __construct(
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly ConnecteurFactory $connecteurFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simulate the update without applying changes'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');

        $this->getIO()->title('SFTP Fingerprint Update Command');
        if ($dryRun) {
            $this->getIO()->note('Running in DRY-RUN mode - no changes will be applied');
        }

        $depotSftpConnectors = $this->connecteurEntiteSQL->getAllByConnecteurId(self::DEPOT_SFTP);
        $glaneurSftpConnectors = $this->connecteurEntiteSQL->getAllByConnecteurId(self::GLANEUR_SFTP);
        $allConnectors = array_merge($depotSftpConnectors, $glaneurSftpConnectors);
        $totalConnectors = count($allConnectors);

        if ($totalConnectors === 0) {
            $this->getIO()->warning('No SFTP connectors found');
            return self::SUCCESS;
        }
        $this->getIO()->writeln("Found <info>$totalConnectors</info> SFTP connector(s)");
        $this->getIO()->writeln('');

        $connectorsToUpdate = [];

        $this->getIO()->section('Analyzing connectors...');
        $this->getIO()->progressStart($totalConnectors);

        foreach ($allConnectors as $connector) {
            $this->getIO()->progressAdvance();

            $id_ce = $connector['id_ce'];
            $id_connecteur = $connector['id_connecteur'];
            $libelle = $connector['libelle'];

            try {
                $result = $this->analyzeConnector($id_ce, $id_connecteur);
                if ($result['needsUpdate']) {
                    $connectorsToUpdate[] = $result;
                }
            } catch (Exception $e) {
                if ($this->getIO()->isVerbose()) {
                    $this->getIO()->warning("Error analyzing connector $libelle (ID: $id_ce): " . $e->getMessage());
                }
            }
        }

        $this->getIO()->progressFinish();
        $this->getIO()->writeln('');

        $updateCount = count($connectorsToUpdate);

        if ($updateCount === 0) {
            $this->getIO()->success('All SFTP connectors configured have valid fingerprints. No updates needed.');
            return self::SUCCESS;
        }

        $this->getIO()->section("Connectors requiring updates: $updateCount");

        if ($dryRun) {
            $this->getIO()->success('DRY-RUN complete. No changes were applied.');
            return self::SUCCESS;
        }

        if (!$this->getIO()->confirm("Do you want to update these $updateCount connector(s)?", false)) {
            $this->getIO()->note('Operation cancelled by user');
            return self::SUCCESS;
        }

        $this->getIO()->section('Applying updates...');
        $this->getIO()->progressStart($updateCount);

        $successCount = 0;
        $errorCount = 0;

        foreach ($connectorsToUpdate as $connector) {
            $this->getIO()->progressAdvance();

            try {
                $config = $this->connecteurFactory->getConnecteurConfig($connector['id_ce']);
                $config->setData($connector['fingerprintField'], $connector['newFingerprint']);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                if ($this->getIO()->isVerbose()) {
                    $this->getIO()->error(
                        "Failed to update connector {$connector['libelle']} (ID: {$connector['id_ce']}): " .
                        $e->getMessage()
                    );
                }
            }
        }

        $this->getIO()->progressFinish();
        $this->getIO()->writeln('');

        if ($errorCount > 0) {
            $this->getIO()->warning("Updated $successCount connector(s) with $errorCount error(s)");
            return self::FAILURE;
        }

        $this->getIO()->success("Successfully updated $successCount connector(s)");
        return self::SUCCESS;
    }

    /**
     * Analyze a connector and determine if it needs updating
     * @throws Exception
     */
    private function analyzeConnector(int $id_ce, string $id_connecteur): array
    {
        $fingerprintField = '';
        try {
            $connector = $this->connecteurFactory->getConnecteurById($id_ce);
            switch ($id_connecteur) {
                case self::DEPOT_SFTP:
                    $fingerprintField = 'depot_sftp_fingerprint';
                    /** @var DepotConnecteur $connector */
                    $connector->listDirectory();
                    break;

                case self::GLANEUR_SFTP:
                    $fingerprintField = 'glaneur_sftp_fingerprint';
                    /** @var GlaneurSFTP $connector */
                    $connector->listDirectories();
                    break;

                default:
                    throw new UnrecoverableException("Unknown SFTP connector type: $id_connecteur");
            }

            return [
                'needsUpdate' => false,
            ];
        } catch (UnrecoverableException $e) {
            $errorMessage = $e->getMessage();
            if (preg_match("/L'empreinte du serveur \(([^)]+)\) ne correspond pas/", $errorMessage, $matches)) {
                $serverFingerprint = $matches[1];
                return [
                    'needsUpdate' => true,
                    'id_ce' => $id_ce,
                    'fingerprintField' => $fingerprintField,
                    'newFingerprint' => $serverFingerprint,
                ];
            }
            return [
                'needsUpdate' => false,
            ];
        }
    }
}
