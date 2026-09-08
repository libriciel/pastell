<?php

class GlaneurLancerGlanageTest extends PastellTestCase
{
    use MailerTransportTestingTrait;

    public function testGlaner(): void
    {
        $this->setMailerTransportForTesting();

        $sftp = $this->createMock(SFTP::class);
        $sftp->method('listDirectory')
            ->willThrowException(new UnrecoverableException('Cannot connect to localhost:22'));
        $sftpFactory = $this->createMock(SFTPFactory::class);
        $sftpFactory->method('getInstance')->willReturn($sftp);
        $this->getObjectInstancier()->setInstance(SFTPFactory::class, $sftpFactory);

        $id_ce = $this->createConnector('glaneur-sftp', 'Glaneur SFTP')['id_ce'];
        $this->configureConnector($id_ce, [
            'traitement_actif' => 'On',
            'type_depot' => 'VRAC',
        ]);
        $this->triggerActionOnConnector($id_ce, 'go');
        $this->assertMessageContainsString("Subject: [Pastell] Le traitement d'un glaneur est");
    }
}
