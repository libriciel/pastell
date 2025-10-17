<?php

class PDFGeneriqueTest extends PastellTestCase
{
    public const FILENAME = "Délib Libriciel.pdf";
    public const ANNEXE1 = "Annexe1 Délib.pdf";
    public const ANNEXE2 = "Annexe2 Délib.pdf";
    public const SIGNATURE_ENVOIE = "send-iparapheur";

    /**
     * @throws Exception
     */
    public function testCasNominal()
    {

        $result = $this->getInternalAPI()->post(
            "/entite/" . self::ID_E_COL . "/connecteur",
            ['libelle' => 'Signature', 'id_connecteur' => 'fakeIparapheur']
        );

        $id_ce = $result['id_ce'];

        $this->getInternalAPI()->post(
            "/entite/" . self::ID_E_COL . "/flux/pdf-generique/connecteur/$id_ce",
            ['type' => 'signature']
        );

        $result = $this->getInternalAPI()->post(
            "/Document/" . PastellTestCase::ID_E_COL,
            ['type' => 'pdf-generique']
        );
        $id_d = $result['id_d'];

        $this->getInternalAPI()->patch(
            "/entite/1/document/$id_d",
            [
            'libelle' => 'Test pdf générique',
                'envoi_signature' => '1',
                'iparapheur_type' => 'Actes',
                'iparapheur_sous_type' => 'Délibération',
            ]
        );

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->addFileFromCopy('document', self::FILENAME, __DIR__ . "/fixtures/" . self::FILENAME, 0);
        $donneesFormulaire->addFileFromCopy('annexe', self::ANNEXE1, __DIR__ . "/fixtures/" . self::ANNEXE1, 0);
        $donneesFormulaire->addFileFromCopy('annexe', self::ANNEXE2, __DIR__ . "/fixtures/" . self::ANNEXE2, 1);

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeOnDocument(1, 0, $id_d, self::SIGNATURE_ENVOIE);

        $this->assertEquals(
            "Le document a été envoyé au parapheur électronique",
            $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage()
        );
    }

    /**
     * Test qu'un utilisateur abonné à 'reception' sur le flux 'pdf-generique' reçoit bien un mail lorsque le flux passe
     * dans cet état
     */
    public function testMailsecNotification()
    {
        $document = $this->createDocument('pdf-generique');
        $id_d = $document['id_d'];

        $mailsec = $this->createConnector('mailsec', 'Mail sécurisé');
        $this->associateFluxWithConnector($mailsec['id_ce'], 'pdf-generique', 'mailsec');
        $this->getInternalAPI()->patch("entite/1/document/$id_d", [
            'envoi_mailsec' => true,
            'to' => 'email@example.org',
        ]);

        $notification = $this->getObjectInstancier()->getInstance(Notification::class);
        $notification->add(self::ID_U_ADMIN, self::ID_E_COL, 'pdf-generique', 'reception', true);

        $action = $this->triggerActionOnDocument($id_d, 'send-mailsec');
        $this->assertTrue($action);

        $documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        $info = $documentEmail->getInfo($id_d);
        $key = $info[0]['key'];

        $documentEmail->consulter($key);

        $notificationDigestSql = $this->getObjectInstancier()->getInstance(NotificationDigestSQL::class);

        $admin = $this->getInternalAPI()->get("utilisateur/" . self::ID_U_ADMIN);
        $allNotification = $notificationDigestSql->getAll();

        $this->assertNotEmpty($allNotification);

        $notif = $allNotification[$admin['email']][0];
        $this->assertSame('pdf-generique', $notif['type']);
        $this->assertSame('reception', $notif['action']);
    }

    /**
     * @throws Exception
     */
    public function testPossibleSuppressionWhenEnding()
    {
        $id_d = $this->createDocument('pdf-generique')['id_d'];
        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);
        $actionChange->addAction($id_d, 1, 0, "termine", "test");
        $this->assertActionPossible(['modification', 'supression'], $id_d);
    }
}
