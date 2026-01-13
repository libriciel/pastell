<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use Exception;
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

        // Si une mise à jour a eu lieu, vérifier que le fingerprint a changé
        if ($idCe !== null && $fingerprintField !== null && $initialFingerprint !== null && str_contains($output, 'Successfully updated')) {
            $config = $this->connecteurFactory->getConnecteurConfig($idCe);
            $updatedFingerprint = $config->get($fingerprintField);

            static::assertNotSame(
                $initialFingerprint,
                $updatedFingerprint,
                "Le fingerprint devrait avoir changé de '$initialFingerprint' à '$updatedFingerprint'"
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
                'connecteurType' => 'glaneur-sftp',
                'connecteurConfig' => null,
                'expectedOutput' => 'All SFTP connectors configured have valid fingerprints',
                'fingerprintField' => 'glaneur_sftp_fingerprint',
            ],
            'connecteur_depot_sftp_mauvais_fingerprint' => [
                'connecteurType' => 'depot-sftp',
                'connecteurConfig' => [
                    'depot_sftp_host' => 'pastell-depot-sftp-1',
                    'depot_sftp_port' => '22',
                    'depot_sftp_login' => 'sftp',
                    'depot_sftp_password' => 'sftp',
                    'depot_sftp_repertoire' => '/upload',
                    'depot_sftp_fingerprint' => 'SHA256:BADFINGERPRINTthatWillTriggerAnUpdate',
                ],
                'expectedOutput' => 'Found 1 SFTP connector(s)',
                'fingerprintField' => 'depot_sftp_fingerprint',
            ],
            'connecteur_glaneur_sftp_mauvais_fingerprint' => [
                'connecteurType' => 'glaneur-sftp',
                'connecteurConfig' => [
                    'glaneur_sftp_host' => 'pastell-glaneur-sftp-1',
                    'glaneur_sftp_port' => '22',
                    'glaneur_sftp_login' => 'sftp',
                    'glaneur_sftp_password' => 'sftp',
                    'glaneur_sftp_repertoire' => '/upload',
                    'glaneur_sftp_fingerprint' => 'SHA256:BADFINGERPRINTthatWillTriggerAnUpdate',
                ],
                'expectedOutput' => 'Found 1 SFTP connector(s)',
                'fingerprintField' => 'glaneur_sftp_fingerprint',
            ],
        ];
    }
}
