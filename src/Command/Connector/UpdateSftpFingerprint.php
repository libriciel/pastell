<?php

declare(strict_types=1);

namespace Pastell\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use DepotConnecteur;
use Exception;
use GlaneurSFTP;
use Pastell\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnrecoverableException;

class UpdateSftpFingerprint extends BaseCommand
{
    public function __construct(
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly ConnecteurFactory $connecteurFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:connector:update-sftp-fingerprint')
            ->setDescription('Update SFTP fingerprints for DepotSFTP and GlaneurSFTP connectors')
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

        $depotSftpConnectors = $this->connecteurEntiteSQL->getAllByConnecteurId('depot-sftp');
        $glaneurSftpConnectors = $this->connecteurEntiteSQL->getAllByConnecteurId('glaneur-sftp');
        $allConnectors = array_merge($depotSftpConnectors, $glaneurSftpConnectors);
        $totalConnectors = count($allConnectors);

        if ($totalConnectors === 0) {
            $this->getIO()->warning('No SFTP connectors found');
            return 0;
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
            return 0;
        }

        $this->getIO()->section("Connectors requiring updates: $updateCount");

        if ($dryRun) {
            $this->getIO()->success('DRY-RUN complete. No changes were applied.');
            return 0;
        }

        if (!$this->getIO()->confirm("Do you want to update these $updateCount connector(s)?", false)) {
            $this->getIO()->note('Operation cancelled by user');
            return 0;
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
        } else {
            $this->getIO()->success("Successfully updated $successCount connector(s)");
        }

        return 0;
    }

    /**
     * Analyze a connector and determine if it needs updating
     * @throws Exception
     */
    private function analyzeConnector(int $id_ce, string $id_connecteur): array
    {
        $isDepot = $id_connecteur === 'depot-sftp';
        $prefix = $isDepot ? 'depot_sftp_' : 'glaneur_sftp_';
        $fingerprintField = $prefix . 'fingerprint';

        try {
            $connector = $this->connecteurFactory->getConnecteurById($id_ce);
            if ($isDepot) {
                /** @var DepotConnecteur $connector */
                $connector->listDirectory();
            } else {
                /** @var GlaneurSFTP $connector */
                $connector->listDirectories();
            }
            return [
                'needsUpdate' => false,
                'reason' => 'Fingerprint is valid'
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
                'reason' => 'Connection error: ' . $errorMessage
            ];
        }
    }
}
