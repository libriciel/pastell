<?php

declare(strict_types=1);

class TedetisRecupTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @throws Exception
     */
    public function testCasNominal(): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_transac_get_status.php?transaction=42' =>
                "OK\n4\n" . file_get_contents(__DIR__ . '/../fixtures/aractes.xml'),
            '/modules/actes/actes_create_pdf.php?trans_id=42' => 'bordereau content',
            '/modules/actes/actes_transac_get_files_list.php?transaction=42' =>
                file_get_contents(__DIR__ . '/../fixtures/actes_transac_get_files_list.json'),
            '/modules/actes/actes_download_file.php?file=3968&tampon=true' => 'some pdf stuff tamponne',
            '/modules/actes/actes_download_file.php?file=3969&tampon=true' =>  'some annexe tamponne',
        ]);

        $connectorId = $this->createConnector('s2low', 's2low')['id_ce'];
        $this->associateFluxWithConnector($connectorId, 'actes-generique', 'TdT');
        $id_d = $this->createDocument('actes-generique')['id_d'];

        $this->configureDocument($id_d, [
            'objet' => "achat d'un bus logiciel",
            'numero_de_lacte' => '201812101049',
            'tedetis_transaction_id' => 42,
        ]);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->addFileFromData('arrete', 'mon_acte.pdf', '');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'ma_premiere_annexe.pdf', '');

        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);
        $actionChange->addAction($id_d, PastellTestCase::ID_E_COL, 0, 'send-tdt', 'phpunit');

        $result = $this->getInternalAPI()->post(
            '/entite/' . PastellTestCase::ID_E_COL . "/document/$id_d/action/verif-tdt"
        );

        static::assertTrue($result['result']);
        static::assertSame("L'acquittement du contrôle de légalité a été reçu.", $result['message']);

        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);

        $info_action = $documentActionEntite->getInfo($id_d, 1);
        static::assertSame('acquiter-tdt', $info_action['last_action']);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        static::assertStringEqualsFile(
            __DIR__ . '/../fixtures/aractes.xml',
            $donneesFormulaire->getFileContent('aractes')
        );
        static::assertSame('bordereau content', $donneesFormulaire->getFileContent('bordereau'));
        static::assertSame('some pdf stuff tamponne', $donneesFormulaire->getFileContent('acte_tamponne'));
        static::assertSame('some annexe tamponne', $donneesFormulaire->getFileContent('annexes_tamponnees'));

        static::assertSame(
            '201812101049-bordereau-tdt.pdf',
            $donneesFormulaire->getFileName('bordereau')
        );

        static::assertSame(
            '201812101049-ar-actes.xml',
            $donneesFormulaire->getFileName('aractes')
        );

        static::assertSame(
            'mon_acte-tampon.pdf',
            $donneesFormulaire->getFileName('acte_tamponne')
        );

        static::assertSame(
            [0 => 'ma_premiere_annexe-tampon.pdf'],
            $donneesFormulaire->get('annexes_tamponnees')
        );

        static::assertSame('2017-12-27', $donneesFormulaire->get('date_ar'));
    }

    /**
     * @throws Exception
     */
    public function testErreurAnnexes(): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_transac_get_status.php?transaction=42' =>
                "OK\n4\n" . file_get_contents(__DIR__ . '/../fixtures/aractes.xml'),
            '/modules/actes/actes_create_pdf.php?trans_id=42' => 'bordereau content',
            '/modules/actes/actes_transac_get_files_list.php?transaction=42' =>
                file_get_contents(__DIR__ . '/../fixtures/actes_transac_get_files_list.json'),
            '/modules/actes/actes_download_file.php?file=3968&tampon=true' => 'some pdf stuff tamponne',
            '/modules/actes/actes_download_file.php?file=3969&tampon=true' =>  'some annexe tamponne',
        ]);

        $connectorId = $this->createConnector('s2low', 's2low')['id_ce'];
        $this->associateFluxWithConnector($connectorId, 'actes-generique', 'TdT');
        $id_d = $this->createDocument('actes-generique')['id_d'];
        $this->configureDocument($id_d, [
            'objet' => "achat d'un bus logiciel",
            'numero_de_lacte' => '201812101049',
            'tedetis_transaction_id' => 42,
        ]);

        $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->addFileFromData('arrete', 'mon_acte.pdf', '');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'ma_premiere_annexe_envoyée.pdf', '');

        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);
        $actionChange->addAction($id_d, PastellTestCase::ID_E_COL, 0, 'send-tdt', 'phpunit');

        $this->expectException(Exception::class);
        $errorMessage = 'Une erreur est survenue lors de la récupération des annexes tamponnées de S²low ' .
            "L'annexe tamponée ma_premiere_annexe.pdf ne correspond pas avec ma_premiere_annexe_envoy__e.pdf";
        $this->expectExceptionMessage($errorMessage);
        $this->getInternalAPI()->post(
            '/entite/' . PastellTestCase::ID_E_COL . "/document/$id_d/action/verif-tdt"
        );
    }

    /**
     * @throws Exception
     */
    public function testS2lowSendError(): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_transac_get_status.php?transaction=42' =>
                mb_convert_encoding("OK\n-1\nEnveloppe invalide : raison de l'erreur hyper détaillé", 'ISO-8859-1'),
        ]);

        $connector = $this->createConnector('s2low', 's2low');
        $id_ce = $connector['id_ce'];

        $this->associateFluxWithConnector($id_ce, 'actes-generique', 'TdT');

        $document = $this->createDocument('actes-generique');
        $id_d = $document['id_d'];

        $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setData('tedetis_transaction_id', 42);

        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);
        $actionChange->addAction($id_d, PastellTestCase::ID_E_COL, 0, 'send-tdt', 'phpunit');

        $this->triggerActionOnDocument($id_d, 'verif-tdt');

        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);
        $info_action = $documentActionEntite->getInfo($id_d, 1);
        static::assertSame('erreur-verif-tdt', $info_action['last_action']);

        $this->assertLastMessage(
            "Transaction en erreur sur le TdT : Enveloppe invalide : raison de l'erreur hyper détaillé"
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testReStamp(): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_download_file.php?file=3968&tampon=true&date_affichage=2022-02-18' =>
                'some pdf stuff tamponne',
            '/modules/actes/actes_download_file.php?file=3969&tampon=true&date_affichage=2022-02-18' =>
                'some annexe tamponne',
            '/modules/actes/actes_transac_get_files_list.php?transaction=42' =>
                file_get_contents(__DIR__ . '/../fixtures/actes_transac_get_files_list.json'),
        ]);
        $id_ce = $this->createConnector('s2low', 'S2low')['id_ce'];
        $this->associateFluxWithConnector($id_ce, 'actes-generique', 'TdT');

        $id_d = $this->createDocument('actes-generique')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setData('tedetis_transaction_id', 42);
        $donneesFormulaire->setData('acte_publication_date', '2022-02-18');

        $donneesFormulaire->addFileFromData('arrete', 'mon_acte.pdf', '');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'ma_premiere_annexe.pdf', '');

        $this->getObjectInstancier()->getInstance(ActionChange::class)
            ->addAction($id_d, 1, 0, 'acquiter-tdt', 'test');

        $result = $this->triggerActionOnDocument($id_d, 'tamponner-tdt');
        static::assertTrue($result);
        static::assertSame(
            'some pdf stuff tamponne',
            $donneesFormulaire->getFileContent('acte_tamponne')
        );
        static::assertSame(
            'some annexe tamponne',
            $donneesFormulaire->getFileContent('annexes_tamponnees', 0)
        );
    }

    /**
     * @throws NotFoundException
     */
    public function testReStampInGoLot(): void
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setData('acte_publication_date', '2022-02-18');

        $this->getObjectInstancier()->getInstance(ActionChange::class)
            ->addAction($id_d, 1, 0, 'acquiter-tdt', 'test');

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeLotDocument(1, 1, [$id_d], 'tamponner-tdt');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        self::assertSame('2022-02-18', $donneesFormulaire->get('acte_publication_date'));
    }
}
