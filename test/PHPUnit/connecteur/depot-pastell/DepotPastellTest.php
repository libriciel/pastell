<?php

class DepotPastellTest extends PastellTestCase
{
    public const PASTELL_METADATA_DEFAULT = "objet:%objet%\nacte_nature:%acte_nature%\nenvoi_tdt:on\narrete:%arrete%\nautre_document_attache:%autre_document_attache%";

    private function setCurlWrapperMock(callable $function_for_get_method)
    {
        $curlWrapper = $this->createMock(CurlWrapper::class);

        $curlWrapper
            ->method('get')
            ->willReturnCallback($function_for_get_method);

        $curlWrapper->expects($this->atLeastOnce())
            ->method('httpAuthentication')
            ->willReturnCallback(function ($a, $b) {
                $this->assertEquals("user_technique", $a);
                $this->assertEquals("mot_de_passe_user_technique", $b);
            });

        $curlWrapper->expects($this->atLeastOnce())
            ->method('getLastHttpCode')
            ->willReturn(200);

        $curlWrapperFactory = $this->createMock(CurlWrapperFactory::class);

        $curlWrapperFactory
            ->method('getInstance')
            ->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
    }

    /**
     * @param string $pastell_metadata
     * @return Connecteur
     * @throws Exception
     */
    private function getDepotPastell($pastell_metadata = self::PASTELL_METADATA_DEFAULT)
    {

        $info = $this->createConnector(DepotPastell::CONNECTEUR_ID, "Dépôt Pastell");

        $connecteurConfig = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($info['id_ce']);
        $connecteurConfig->setTabData([
            DepotPastell::PASTELL_URL => "https://pastell2.test.libriciel.fr/",
            DepotPastell::PASTELL_LOGIN => "user_technique",
            DepotPastell::PASTELL_PASSWORD => "mot_de_passe_user_technique",
            DepotPastell::PASTELL_ID_E => 34,
            DepotPastell::PASTELL_ACTION => "send-tdt",
            DepotPastell::PASTELL_METADATA => $pastell_metadata,
            DepotPastell::PASTELL_TYPE_DOSSIER => "actes-generique",
        ]);

        return $this->getConnecteurFactory()->getConnecteurById($info['id_ce']);
    }

    /**
     * @throws Exception
     * @throws UnrecoverableException
     */
    public function testConnexion()
    {
        $this->setCurlWrapperMock(function ($a) {
            if ($a == 'https://pastell2.test.libriciel.fr/api/v2/version') {
                return file_get_contents(__DIR__ . "/fixtures/api-response-version.json");
            }
        });
        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();
        $info = $depotPastell->getVersion();
        $this->assertEquals("Version 2.0.X - Révision  31810", $info);
    }

    /**
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    private function createActeGenerique(): string
    {
        $connecteur_info = $this->createConnector("fakeTdt", "Bouchon tdt");

        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);

        $connecteurDonneesFormulaire->addFileFromCopy(
            'classification_file',
            "classification.xml",
            __DIR__ . "/../../module/actes-generique/fixtures/classification.xml"
        );
        $this->associateFluxWithConnector($connecteur_info['id_ce'], "actes-generique", "TdT");

        $document_info = $this->createDocument("actes-generique");
        return $document_info['id_d'];
    }

    /**
     * @return DonneesFormulaire
     * @throws Exception
     */
    private function getDonneesFormulaire(): DonneesFormulaire
    {
        $id_d = $this->createActeGenerique();
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData([
            'objet' => 'Mon objet',
            'acte_nature' => 2,
            'envoi_tdt' => false,
            'numero_de_lacte' => '201905161006',
            'date_de_lacte' => '2019-05-01',
            'classification' => '1.1'
        ]);
        $donneesFormulaire->addFileFromData('arrete', 'arrete.pdf', __DIR__ . "/../../fixtures/vide.pdf");
        $donneesFormulaire->addFileFromData('autre_document_attache', 'autre_document_attache_0.txt', "foo", 0);
        $donneesFormulaire->addFileFromData('autre_document_attache', 'autre_document_attache_1.txt', "bar", 0);
        return $donneesFormulaire;
    }

    /**
     * @throws Exception
     */
    public function testSend()
    {
        $this->setCurlWrapperMock(function ($a) {
            if ($a == "https://pastell2.test.libriciel.fr/api/v2/entite/34/document?type=actes-generique") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-create-document.json");
            }
            if ($a == "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-patch-document.json");
            }
            if (
                in_array($a, [
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/arrete/0",
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/0",
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/1"
                ])
            ) {
                return file_get_contents(__DIR__ . "/fixtures/api-response-post-file.json");
            }
            if ($a == "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/action/send-tdt") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-action.json");
            }

            throw new UnrecoverableException("Appel à une URL inatendue $a");
        });
        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->assertSame(
            ['68hpWOt' => '68hpWOt'],
            $depotPastell->send($donneesFormulaire)
        );
    }

    /**
     * @throws Exception
     */
    public function testSendWhenCantCreateDocument()
    {
        $this->setCurlWrapperMock(function ($a) {
            if ($a == "https://pastell2.test.libriciel.fr/api/v2/entite/34/document?type=actes-generique") {
                return '{"foo":"bar"}';
            }
            throw new UnrecoverableException("Appel à une URL inatendue $a");
        });

        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Impossible de créer le dossier sur Pastell");
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws Exception
     */
    public function testSendWhenFormulaireIsNotOk()
    {
        $this->setCurlWrapperMock(function ($a) {
            if ($a == "https://pastell2.test.libriciel.fr/api/v2/entite/34/document?type=actes-generique") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-create-document.json");
            }
            if ($a == "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-patch-document.json");
            }
            if (
                in_array($a, [
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/arrete/0",
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/0",
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/1"
                ])
            ) {
                return file_get_contents(__DIR__ . "/fixtures/api-response-patch-document.json");
            }
            throw new UnrecoverableException("Appel à une URL inatendue $a");
        });

        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "Impossible d'appeller l'action sur le document Pastell car le formulaire n'est pas valide : Le formulaire est incomplet : le champ «Acte» est obligatoire."
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws Exception
     */
    public function testSendWhenActionFailed()
    {
        $this->setCurlWrapperMock(function ($a) {
            if ($a == "https://pastell2.test.libriciel.fr/api/v2/entite/34/document?type=actes-generique") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-create-document.json");
            }
            if ($a == "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-patch-document.json");
            }
            if (
                in_array($a, [
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/arrete/0",
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/0",
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/1"
                ])
            ) {
                return file_get_contents(__DIR__ . "/fixtures/api-response-post-file.json");
            }
            if ($a == "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/action/send-tdt") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-action-failed.json");
            }
            throw new UnrecoverableException("Appel à une URL inatendue $a");
        });

        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "Erreur lors de l'appel à l'action sur le document : L'action « send-tdt »  n'est pas permise : or_1 n'est pas vérifiée"
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testSendWhenErrorInInputMetadata()
    {
        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell("foo:%bar%");

        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "L'élement « bar » n'existe pas pour le type de dossier actes-generique"
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws Exception
     */
    public function testWhenCallApiReturnNonOK()
    {
        $curlWrapper = $this->createMock(CurlWrapper::class);

        $curlWrapper->expects($this->atLeastOnce())
            ->method('getLastHttpCode')
            ->willReturn(404);

        $curlWrapperFactory = $this->createMock(CurlWrapperFactory::class);

        $curlWrapperFactory
            ->method('getInstance')
            ->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $donneesFormulaire = $this->getDonneesFormulaire();

        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "Erreur 404 () lors de la réponse de Pastell"
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws Exception
     */
    public function testWhenCallApiReturnNotJsonData()
    {
        $curlWrapper = $this->createMock(CurlWrapper::class);

        $curlWrapper->expects($this->atLeastOnce())
            ->method('getLastHttpCode')
            ->willReturn(200);

        $curlWrapper
            ->method('get')
            ->willReturn("foo");

        $curlWrapperFactory = $this->createMock(CurlWrapperFactory::class);

        $curlWrapperFactory
            ->method('getInstance')
            ->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $donneesFormulaire = $this->getDonneesFormulaire();

        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "Message de Pastell non compréhensible : foo"
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testSendDocumentWithoutAction()
    {
        $this->setCurlWrapperMock(function ($url) {
            // assert no action is being sent
            $this->assertFalse(strstr('action', $url));

            if ($url == "https://pastell2.test.libriciel.fr/api/v2/entite/34/document?type=actes-generique") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-create-document.json");
            }
            if ($url == "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-patch-document.json");
            }
            if (
                in_array($url, [
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/arrete/0",
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/0",
                "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/1"
                ])
            ) {
                return file_get_contents(__DIR__ . "/fixtures/api-response-post-file.json");
            }

            throw new UnrecoverableException("Appel à une URL inatendue $url");
        });
        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();
        $id_ce = $depotPastell->getConnecteurInfo()['id_ce'];
        $this->configureConnector($id_ce, [
            DepotPastell::PASTELL_ACTION => DepotPastell::NO_ACTION,
        ]);
        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->assertSame(
            ['68hpWOt' => '68hpWOt'],
            $depotPastell->send($donneesFormulaire)
        );
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testSendWithoutAnnexe(): void
    {
        $this->setCurlWrapperMock(function ($a) {
            if ($a === "https://pastell2.test.libriciel.fr/api/v2/entite/34/document?type=actes-generique") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-create-document.json");
            }
            if ($a === "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-patch-document.json");
            }
            if ($a === "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/file/arrete/0") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-post-file.json");
            }
            if ($a === "https://pastell2.test.libriciel.fr/api/v2//entite/34/document/68hpWOt/action/send-tdt") {
                return file_get_contents(__DIR__ . "/fixtures/api-response-action.json");
            }
            throw new UnrecoverableException("Appel à une URL inattendue $a");
        });

        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getDepotPastell();
        $id_ce = $depotPastell->getConnecteurInfo()['id_ce'];
        $this->associateFluxWithConnector($id_ce, 'actes-generique', "GED", 1);
        $id_d = $this->createActeGenerique();
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData([
            'objet' => 'Mon objet',
            'acte_nature' => 3,
            'envoi_tdt' => false,
            'numero_de_lacte' => '201905161006',
            'date_de_lacte' => '2019-05-01',
            'classification' => '1.1',
            'envoi_ged' => 1
        ]);
        $donneesFormulaire->addFileFromData('arrete', 'arrete.pdf', __DIR__ . "/../../fixtures/vide.pdf");
        $this->getInternalAPI()->patch("/entite/1/document/$id_d/externalData/type_piece", ['type_pj' => ['22_NE']]);

        set_error_handler(function ($errno, $errstr) {
            $this->fail("Warning PHP inattendu: $errstr");
        }, E_WARNING);

        try {
            $info = $this->getInternalAPI()->post("entite/1/document/$id_d/action/send-ged");
            $this->assertSame('Le dossier Mon objet a été versé sur le dépôt', $info['message']);
        } finally {
            restore_error_handler();
        }
    }
}
