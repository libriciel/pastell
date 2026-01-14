<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use DepotSFTP;
use Exception;
use GlaneurConnecteur;
use GlaneurSFTP;
use Pastell\Command\Connector\UpdateSftpFingerprint;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateSftpFingerprintTest extends PastellTestCase
{
    private CommandTester $commandTester;
    private ConnecteurFactory $connecteurFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $connecteurEntiteSQL = $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class);
        $this->connecteurFactory = $this->getObjectInstancier()->getInstance(ConnecteurFactory::class);

        $command = new UpdateSftpFingerprint(
            $connecteurEntiteSQL,
            $this->connecteurFactory
        );

        $this->commandTester = new CommandTester($command);
    }

    /**
     * @dataProvider sftpConnectorProvider
     * @throws Exception
     */
    public function testUpdateSftpFingerprint(
        ?string $connecteurType,
        ?array $connecteurConfig,
        string $expectedOutput,
        ?string $fingerprintField
    ): void {
        $idCe = null;
        $initialFingerprint = null;

        if ($connecteurType !== null) {
            $connector = $this->createConnector($connecteurType, 'Test SFTP Connector');
            $idCe = $connector['id_ce'];

            if ($connecteurConfig !== null && $fingerprintField !== null) {
                $this->configureConnector($idCe, $connecteurConfig);
                $initialFingerprint = $connecteurConfig[$fingerprintField] ?? null;
            }
        }

        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString($expectedOutput, $output);

        if ($idCe !== null && $fingerprintField !== null && $initialFingerprint !== null && str_contains($output, 'Successfully updated')) {
            $config = $this->connecteurFactory->getConnecteurConfig($idCe);
            $updatedFingerprint = $config->get($fingerprintField);

            static::assertNotSame(
                $initialFingerprint,
                $updatedFingerprint
            );
        }
    }

    public static function sftpConnectorProvider(): array
    {
        return [
            'pas_de_connecteur_sftp' => [
                'connecteurType' => null,
                'connecteurConfig' => null,
                'expectedOutput' => 'No SFTP connectors found',
                'fingerprintField' => null,
            ],
            'connecteur_glaneur_sftp_non_configure' => [
                'connecteurType' => UpdateSftpFingerprint::GLANEUR_SFTP,
                'connecteurConfig' => null,
                'expectedOutput' => 'All SFTP connectors configured have valid fingerprints',
                'fingerprintField' => GlaneurSFTP::GLANEUR_SFTP_FINGERPRINT,
            ],
            'connecteur_depot_sftp_mauvais_fingerprint' => [
                'connecteurType' => UpdateSftpFingerprint::DEPOT_SFTP,
                'connecteurConfig' => [
                    DepotSFTP::DEPOT_SFTP_HOST => 'pastell-depot-sftp-1',
                    DepotSFTP::DEPOT_SFTP_PORT => '22',
                    DepotSFTP::DEPOT_SFTP_LOGIN => 'sftp',
                    DepotSFTP::DEPOT_SFTP_PASSWORD => 'sftp',
                    DepotSFTP::DEPOT_SFTP_DIRECTORY => '/upload',
                    DepotSFTP::DEPOT_SFTP_FINGERPRINT => 'SHA256:BADFINGERPRINTthatWillTriggerAnUpdate',
                ],
                'expectedOutput' => 'Found 1 SFTP connector(s)',
                'fingerprintField' => DepotSFTP::DEPOT_SFTP_FINGERPRINT,
            ],
            'connecteur_glaneur_sftp_mauvais_fingerprint' => [
                'connecteurType' => UpdateSftpFingerprint::GLANEUR_SFTP,
                'connecteurConfig' => [
                    GlaneurSFTP::GLANEUR_SFTP_HOST => 'pastell-glaneur-sftp-1',
                    GlaneurSFTP::GLANEUR_SFTP_PORT => '22',
                    GlaneurSFTP::GLANEUR_SFTP_LOGIN => 'sftp',
                    GlaneurSFTP::GLANEUR_SFTP_PASSWORD => 'sftp',
                    GlaneurConnecteur::DIRECTORY => '/upload',
                    GlaneurSFTP::GLANEUR_SFTP_FINGERPRINT => 'SHA256:BADFINGERPRINTthatWillTriggerAnUpdate',
                ],
                'expectedOutput' => 'Found 1 SFTP connector(s)',
                'fingerprintField' => GlaneurSFTP::GLANEUR_SFTP_FINGERPRINT,
            ],
        ];
    }
}
