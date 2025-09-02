<?php

class GlaneurLancerGlanageTest extends PastellTestCase
{
    use MailerTransportTestingTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getObjectInstancier()->getInstance(ConfigurationSQL::class)->setAdminEmails(['test@libriciel.invalid']);
    }

    public function testGlaner(): void
    {
        $this->setMailerTransportForTesting();
        $id_ce = $this->createConnector('glaneur-sftp', 'Glaneur SFTP')['id_ce'];
        $this->configureConnector($id_ce, [
            'traitement_actif' => 'On',
            'type_depot' => 'VRAC',
        ]);
        $this->triggerActionOnConnector($id_ce, 'go');
        $this->assertMessageContainsString("Subject: [Pastell] Le traitement d'un glaneur est");
    }
}
