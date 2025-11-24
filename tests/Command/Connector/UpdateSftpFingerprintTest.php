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
        string $scenario,
        ?string $connecteurType,
        ?array $connecteurConfig,
        string $expectedOutput,
        ?string $expectedFingerprintAfterUpdate = null
    ): void {
        $idCe = null;
        if ($connecteurType !== null) {
            $connector = $this->createConnector($connecteurType, 'Test SFTP Connector');
            $idCe = $connector['id_ce'];

            if ($connecteurConfig !== null) {
                $this->configureConnector($idCe, $connecteurConfig);
            }
        }

        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString($expectedOutput, $output);

        if ($expectedFingerprintAfterUpdate !== null && $idCe !== null) {
            $config = $this->connecteurFactory->getConnecteurConfig($idCe);
            $fingerprintField = $connecteurType === 'depot-sftp' ? 'depot_sftp_fingerprint' : 'glaneur_sftp_fingerprint';
            $actualFingerprint = $config->get($fingerprintField);

            static::assertNotSame($connecteurConfig[$fingerprintField], $actualFingerprint);
            static::assertSame($expectedFingerprintAfterUpdate, $actualFingerprint);
        }
    }

    public static function sftpConnectorProvider(): array
    {
        return [
            'pas_de_connecteur_sftp' => [
                'scenario' => 'pas_de_connecteur',
                'connecteurType' => null,
                'connecteurConfig' => null,
                'expectedOutput' => 'No SFTP connectors found',
                'expectedFingerprintAfterUpdate' => null,
            ],
            'connecteur_glaneur_sftp_non_configure' => [
                'scenario' => 'non_configure',
                'connecteurType' => 'glaneur-sftp',
                'connecteurConfig' => null,
                'expectedOutput' => 'All SFTP connectors configured have valid fingerprints',
                'expectedFingerprintAfterUpdate' => null,
            ],
            'connecteur_depot_sftp_mauvais_fingerprint' => [
                'scenario' => 'mauvais_fingerprint',
                'connecteurType' => 'depot-sftp',
                'connecteurConfig' => [
                    'depot_sftp_host' => 'sftp.example.com',
                    'depot_sftp_port' => '22',
                    'depot_sftp_login' => 'testuser',
                    'depot_sftp_password' => 'testpass',
                    'depot_sftp_repertoire' => '/test',
                    'depot_sftp_fingerprint' => 'SHA256:aki0Kgy9zYzhW2UtKpflOPQmBsNa+VdWvRlpE6dgDy0',
                ],
                'expectedOutput' => 'Found 1 SFTP connector(s)',
                'expectedFingerprintAfterUpdate' => null, // sera rempli dynamiquement selon le serveur
            ],
            'connecteur_glaneur_sftp_mauvais_fingerprint' => [
                'scenario' => 'mauvais_fingerprint',
                'connecteurType' => 'glaneur-sftp',
                'connecteurConfig' => [
                    'glaneur_sftp_host' => 'sftp.example.com',
                    'glaneur_sftp_port' => '22',
                    'glaneur_sftp_login' => 'testuser',
                    'glaneur_sftp_password' => 'testpass',
                    'glaneur_sftp_repertoire' => '/test',
                    'glaneur_sftp_fingerprint' => 'SHA256:aki0Kgy9zYzhW2UtKpflOPQmBsNa+VdWvRlpE6dgDy0',
                ],
                'expectedOutput' => 'Found 1 SFTP connector(s)',
                'expectedFingerprintAfterUpdate' => null, // sera rempli dynamiquement selon le serveur
            ],
        ];
    }
}
