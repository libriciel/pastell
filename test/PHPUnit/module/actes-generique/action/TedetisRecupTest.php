<?php

class TedetisRecupTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @throws Exception
     */
    public function testCasNominal()
    {
        // Préparation du test
        $curlWrapper = $this->createMock(CurlWrapper::class);
        $curlWrapper->method("get")->willReturnCallback(function ($url) {

            if ($url == "/admin/users/api-list-login.php") {
                return true;
            } elseif ($url == "/modules/actes/actes_transac_get_status.php?transaction=42") {
                return "OK\n4\n" . file_get_contents(__DIR__ . "/../fixtures/aractes.xml");
            } elseif ($url == "/modules/actes/actes_create_pdf.php?trans_id=42") {
                return "bordereau content";
            } elseif ($url == "/modules/actes/actes_transac_get_files_list.php?transaction=42") {
                return file_get_contents(__DIR__ . "/../fixtures/actes_transac_get_files_list.json");
            } elseif ($url == "/modules/actes/actes_download_file.php?file=3968&tampon=true") {
                return "some pdf stuff tamponne";
            } elseif ($url == "/modules/actes/actes_download_file.php?file=3969&tampon=true") {
                return "some annexe tamponne";
            }

            throw new Exception("$url inatendu");
        });

        $curlWrapperFactory = $this->createMock(CurlWrapperFactory::class);
        $curlWrapperFactory->method("getInstance")->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);


        $result = $this->getInternalAPI()->post(
            "/entite/1/connecteur/",
            ['libelle' => 's2low','id_connecteur' => 's2low']
        );
        $id_ce = $result['id_ce'];

        $this->getInternalAPI()->post(
            "/entite/1/flux/actes-generique/connecteur/$id_ce",
            ['type' => 'TdT']
        );

        $result = $this->getInternalAPI()->post(
            "/Document/1",
            ['type' => 'actes-generique']
        );
        $id_d = $result['id_d'];


        $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setTabData([
            'objet' => "achat d'un bus logiciel",
            'numero_de_lacte' => '201812101049',
        ]);

        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $documentSQL->setTitre($id_d, "achat d'un bus logiciel");

        $donneesFormulaire->setData('tedetis_transaction_id', 42);
        $donneesFormulaire->addFileFromData('arrete', 'mon_acte.pdf', '');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'ma_premiere_annexe.pdf', '');

        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);
        $actionChange->addAction($id_d, PastellTestCase::ID_E_COL, 0, 'send-tdt', 'phpunit');

        $result = $this->getInternalAPI()->post(
            "/entite/" . PastellTestCase::ID_E_COL . "/document/{$id_d}/action/verif-tdt"
        );

        //Analyse des résultats
        $this->assertEquals(1, $result['result']);
        $this->assertEquals("L'acquittement du contrôle de légalité a été reçu.", $result['message']);

        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);

        $info_action = $documentActionEntite->getInfo($id_d, 1);
        $this->assertEquals('acquiter-tdt', $info_action['last_action']);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $this->assertStringEqualsFile(
            __DIR__ . "/../fixtures/aractes.xml",
            $donneesFormulaire->getFileContent('aractes')
        );
        $this->assertEquals("bordereau content", $donneesFormulaire->getFileContent('bordereau'));
        $this->assertEquals("some pdf stuff tamponne", $donneesFormulaire->getFileContent('acte_tamponne'));
        $this->assertEquals("some annexe tamponne", $donneesFormulaire->getFileContent('annexes_tamponnees'));

        $this->assertEquals(
            '201812101049-bordereau-tdt.pdf',
            $donneesFormulaire->getFileName('bordereau')
        );

        $this->assertEquals(
            '201812101049-ar-actes.xml',
            $donneesFormulaire->getFileName('aractes')
        );

        $this->assertEquals(
            'mon_acte-tampon.pdf',
            $donneesFormulaire->getFileName('acte_tamponne')
        );

        $this->assertEquals(
            [0 => 'ma_premiere_annexe-tampon.pdf'],
            $donneesFormulaire->get('annexes_tamponnees')
        );

        $this->assertEquals("2017-12-27", $donneesFormulaire->get('date_ar'));
    }

    /**
     * @throws Exception
     */
    public function testErreurAnnexes()
    {
        // Préparation du test
        $curlWrapper = $this->createMock(CurlWrapper::class);
        $curlWrapper->method("get")->willReturnCallback(function ($url) {

            if ($url == "/admin/users/api-list-login.php") {
                return true;
            } elseif ($url == "/modules/actes/actes_transac_get_status.php?transaction=42") {
                return "OK\n4\n" . file_get_contents(__DIR__ . "/../fixtures/aractes.xml");
            } elseif ($url == "/modules/actes/actes_create_pdf.php?trans_id=42") {
                return "bordereau content";
            } elseif ($url == "/modules/actes/actes_transac_get_files_list.php?transaction=42") {
                return file_get_contents(__DIR__ . "/../fixtures/actes_transac_get_files_list.json");
            } elseif ($url == "/modules/actes/actes_download_file.php?file=3968&tampon=true") {
                return "some pdf stuff tamponne";
            } elseif ($url == "/modules/actes/actes_download_file.php?file=3969&tampon=true") {
                return "some annexe tamponne";
            }

            throw new Exception("$url inatendu");
        });

        $curlWrapperFactory = $this->createMock(CurlWrapperFactory::class);
        $curlWrapperFactory->method("getInstance")->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);


        $result = $this->getInternalAPI()->post(
            "/entite/1/connecteur/",
            ['libelle' => 's2low','id_connecteur' => 's2low']
        );
        $id_ce = $result['id_ce'];

        $this->getInternalAPI()->post(
            "/entite/1/flux/actes-generique/connecteur/$id_ce",
            ['type' => 'TdT']
        );

        $result = $this->getInternalAPI()->post(
            "/Document/1",
            ['type' => 'actes-generique']
        );
        $id_d = $result['id_d'];


        $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setTabData([
            'objet' => "achat d'un bus logiciel",
            'numero_de_lacte' => '201812101049',
        ]);

        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $documentSQL->setTitre($id_d, "achat d'un bus logiciel");

        $donneesFormulaire->setData('tedetis_transaction_id', 42);
        $donneesFormulaire->addFileFromData('arrete', 'mon_acte.pdf', '');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'ma_premiere_annexe_envoyée.pdf', '');

        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);
        $actionChange->addAction($id_d, PastellTestCase::ID_E_COL, 0, 'send-tdt', 'phpunit');

        $this->expectException(Exception::class);
        $errorMessage = 'Une erreur est survenue lors de la récupération des annexes tamponnées de S²low ' .
         "L'annexe tamponée ma_premiere_annexe.pdf ne correspond pas avec ma_premiere_annexe_envoy__e.pdf";
        $this->expectExceptionMessage($errorMessage);
        $this->getInternalAPI()->post(
            "/entite/" . PastellTestCase::ID_E_COL . "/document/{$id_d}/action/verif-tdt"
        );
    }

    /**
     * @throws Exception
     */
    public function testS2lowSendError(): void
    {
        // Préparation du test
        $curlWrapper = $this->createMock(CurlWrapper::class);
        $curlWrapper->method('get')->willReturnCallback(function ($url) {
            if ($url === '/admin/users/api-list-login.php') {
                return true;
            }

            if ($url === '/modules/actes/actes_transac_get_status.php?transaction=42') {
                return mb_convert_encoding("OK\n-1\nEnveloppe invalide : raison de l'erreur hyper détaillé", 'ISO-8859-1');
            }

            throw new Exception("$url inatendu");
        });

        $curlWrapperFactory = $this->createMock(CurlWrapperFactory::class);
        $curlWrapperFactory->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);

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

        //Analyse des résultats
        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);
        $info_action = $documentActionEntite->getInfo($id_d, 1);
        $this->assertEquals('erreur-verif-tdt', $info_action['last_action']);

        $sql = 'SELECT message FROM journal ORDER BY id_j DESC LIMIT 1;';
        $message = self::getSQLQuery()->queryOne($sql);
        static::assertSame(
            "Transaction en erreur sur le TdT : Enveloppe invalide : raison de l'erreur hyper détaillé",
            $message
        );
    }

    public function testReStamp(): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_download_file.php?file=3968&tampon=true&date_affichage=2022-02-18' => 'some pdf stuff tamponne',
            '/modules/actes/actes_download_file.php?file=3969&tampon=true&date_affichage=2022-02-18' => 'some annexe tamponne',
             '/modules/actes/actes_transac_get_files_list.php?transaction=42' =>
                 file_get_contents(__DIR__ . "/../fixtures/actes_transac_get_files_list.json"),
        ]);
        $id_ce = $this->createConnector('s2low', 'S2low')['id_ce'];
        $this->associateFluxWithConnector($id_ce, 'actes-generique', 'TdT');


        $id_d = $this->createDocument('actes-generique')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setData('tedetis_transaction_id', 42);
        $donneesFormulaire->setData('acte_publication_date', '2022-02-18');

        $donneesFormulaire->addFileFromData('arrete', 'mon_acte.pdf', '');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'ma_premiere_annexe.pdf', '');

        $actionCreator = $this->getObjectInstancier()->getInstance(ActionCreatorSQL::class);
        $actionCreator->addAction(1, 0, 'acquiter-tdt', "test", $id_d);


        $result = $this->triggerActionOnDocument($id_d, 'tamponner-tdt');
        if (! $result) {
            print_r($this->getLogRecords());
        }
        static::assertTrue($result);
        static::assertEquals(
            'some pdf stuff tamponne',
            $donneesFormulaire->getFileContent('acte_tamponne')
        );
        static::assertEquals(
            'some annexe tamponne',
            $donneesFormulaire->getFileContent('annexes_tamponnees', 0)
        );
    }

    public function testReStampInGoLot(): void
    {

        $id_d = $this->createDocument('actes-generique')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setData('acte_publication_date', '2022-02-18');

        $actionCreator = $this->getObjectInstancier()->getInstance(ActionCreatorSQL::class);
        $actionCreator->addAction(1, 0, 'acquiter-tdt', "test", $id_d);

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeLotDocument(1, 1, [$id_d], "tamponner-tdt");

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        self::assertEquals('2022-02-18', $donneesFormulaire->get('acte_publication_date'));
    }
}
