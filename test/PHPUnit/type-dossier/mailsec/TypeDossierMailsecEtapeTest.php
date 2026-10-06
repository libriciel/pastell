<?php

class TypeDossierMailsecEtapeTest extends PastellTestCase
{
    public const MAILSEC_ONLY = 'mailsec-only';

    private TypeDossierLoader $typeDossierLoader;
    private JobQueueSQL $jobQueueSQL;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->typeDossierLoader = $this->getObjectInstancier()->getInstance(TypeDossierLoader::class);
        $this->jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->typeDossierLoader->unload();
    }

    private function createMailsecConnector(string $type): void
    {
        $info_connecteur = $this->createConnector("mailsec", "Mail sécurisé");
        $this->associateFluxWithConnector($info_connecteur['id_ce'], $type, "mailsec");
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    private function createAndFillDocument(string $type): string
    {
        $info = $this->createDocument($type);
        $id_d = $info['id_d'];

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData(['titre' => 'Foo', 'to' => 'foo@bar.com']);
        $donneesFormulaire->addFileFromData('document', 'fichier1.txt', 'bar');
        return $id_d;
    }

    /**
     * @throws Exception
     */
    public function testDepot(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::MAILSEC_ONLY);
        $this->createMailsecConnector(self::MAILSEC_ONLY);
        $id_d = $this->createAndFillDocument(self::MAILSEC_ONLY);

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "orientation")
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "send-mailsec")
        );
        $this->assertLastMessage("Le document a été envoyé au(x) destinataire(s)");


        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "mailsec-relance")
        );
        $last_message = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        $this->assertMatchesRegularExpression("#Relance programmée le#", $last_message);

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "renvoi")
        );
        $this->assertLastMessage("Un email a été renvoyé à tous les destinataires n'ayant pas ouvert le précédent");
        $this->assertLastDocumentAction('renvoi', $id_d);

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "mailsec-relance")
        );
        $last_message = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        $this->assertMatchesRegularExpression("#Relance programmée le#", $last_message);
        $this->assertMatchesRegularExpression("#Mail défini comme non-reçu le#", $last_message);

        $documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        $document_email_info = $documentEmail->getInfo($id_d);
        $documentEmail->consulter($document_email_info[0]['key']);
        $this->assertLastDocumentAction('reception', $id_d);

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "orientation")
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");
        $this->assertLastDocumentAction('termine', $id_d);
    }

    /**
     * @return void
     * @throws NotFoundException
     * @throws TypeDossierException
     */
    public function testFrequenceRelance(): void
    {
        $connecteurFrequenceSQL = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = 'mailsec';
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence->type_document = self::MAILSEC_ONLY;
        $connecteurFrequence->action = 'mailsec-relance';
        $connecteurFrequence->id_verrou = 'MAILSEC_RELANCE';
        $connecteurFrequence->expression = "1440";
        $connecteurFrequenceSQL->edit($connecteurFrequence);

        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::MAILSEC_ONLY);
        $this->createMailsecConnector(self::MAILSEC_ONLY);
        $id_d = $this->createAndFillDocument(self::MAILSEC_ONLY);

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "orientation")
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "send-mailsec")
        );
        $this->assertLastMessage("Le document a été envoyé au(x) destinataire(s)");

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "mailsec-relance")
        );
        $id_job = $this->jobQueueSQL->getJobIdForDocument(1, $id_d);
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertSame(1, $job->nb_try);
        $this->assertSame('MAILSEC_RELANCE', $job->id_verrou);
        $this->assertSame(
            strtotime($job->next_try) - strtotime($job->last_try),
            $connecteurFrequence->expression * 60
        );

        $last_message = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        $this->assertMatchesRegularExpression("#Relance programmée le#", $last_message);
    }

    /**
     * @throws TypeDossierException
     * @throws NotFoundException
     */
    public function testNotReceived(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::MAILSEC_ONLY);
        $this->createMailsecConnector(self::MAILSEC_ONLY);
        $id_d = $this->createAndFillDocument(self::MAILSEC_ONLY);

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "orientation")
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "send-mailsec")
        );
        $this->assertLastMessage("Le document a été envoyé au(x) destinataire(s)");
        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "non-recu")
        );

        $this->assertLastMessage('Mail défini comme non-reçu.');
        $this->assertLastDocumentAction('non-recu', $id_d);

        $documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        $this->assertEquals(1, $documentEmail->getInfo($id_d)[0]['non_recu']);
    }

    /**
     * @throws TypeDossierException
     * @throws NotFoundException
     * @throws Exception
     */
    public function testNonRecuApresRenvois(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::MAILSEC_ONLY);

        $id_ce = $this->createConnector('mailsec', 'Mail sécurisé')['id_ce'];
        $this->configureConnector($id_ce, ['nb_day_relance' => '5', 'nb_day_next_state' => '15']);
        $this->associateFluxWithConnector($id_ce, self::MAILSEC_ONLY, 'mailsec');

        $id_d = $this->createAndFillDocument(self::MAILSEC_ONLY);

        $this->triggerActionOnDocument($id_d, 'orientation');
        $this->triggerActionOnDocument($id_d, 'send-mailsec');
        $this->triggerActionOnDocument($id_d, 'renvoi');
        $this->assertLastDocumentAction('renvoi', $id_d);

        $this->backdateAction($id_d, 'send-mailsec', 20);
        $this->backdateAction($id_d, 'renvoi', 3);

        $this->triggerActionOnDocument($id_d, 'mailsec-relance');

        $this->assertLastDocumentAction('preparation-non-recu', $id_d);
        $this->assertLastMessage('Le document passe en non reçu !');
    }

    /**
     * @throws Exception
     */
    private function backdateAction(string $id_d, string $action, int $days): void
    {
        static::getSQLQuery()->query(
            'UPDATE document_action SET date = ? WHERE id_d = ? AND action = ?',
            date('Y-m-d H:i:s', strtotime("-$days days")),
            $id_d,
            $action
        );
    }
}
