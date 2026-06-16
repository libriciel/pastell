<?php

declare(strict_types=1);

class DepotPastellTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    //phpcs:ignore Generic.Files.LineLength.TooLong
    public const PASTELL_METADATA_DEFAULT = "objet:%objet%\nacte_nature:%acte_nature%\nenvoi_tdt:on\narrete:%arrete%\nautre_document_attache:%autre_document_attache%";

    /**
     * @throws Exception
     */
    private function getDepotPastell(string $pastell_metadata = self::PASTELL_METADATA_DEFAULT): DepotPastell
    {
        $info = $this->createConnector(DepotPastell::CONNECTEUR_ID, 'Dépôt Pastell');

        $connecteurConfig = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($info['id_ce']);
        $connecteurConfig->setTabData([
            DepotPastell::PASTELL_URL => 'https://pastell',
            DepotPastell::PASTELL_LOGIN => 'user_technique',
            DepotPastell::PASTELL_PASSWORD => 'mot_de_passe_user_technique',
            DepotPastell::PASTELL_ID_E => 34,
            DepotPastell::PASTELL_ACTION => 'send-tdt',
            DepotPastell::PASTELL_METADATA => $pastell_metadata,
            DepotPastell::PASTELL_TYPE_DOSSIER => 'actes-generique',
        ]);
        /** @var DepotPastell */
        return $this->getConnecteurFactory()->getConnecteurById($info['id_ce']);
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testConnexion(): void
    {
        $this->mockCurl([
            'https://pastell/api/v2/version' => file_get_contents(__DIR__ . '/fixtures/api-response-version.json'),
        ]);

        $depotPastell = $this->getDepotPastell();
        $info = $depotPastell->getVersion();
        static::assertSame('Version 2.0.X - Révision  31810', $info);
    }

    /**
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    private function createActeGenerique(): string
    {
        $connecteur_info = $this->createConnector('fakeTdt', 'Bouchon tdt');

        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);

        $connecteurDonneesFormulaire->addFileFromCopy(
            'classification_file',
            'classification.xml',
            __DIR__ . '/../../module/actes-generique/fixtures/classification.xml'
        );
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'actes-generique', 'TdT');

        $document_info = $this->createDocument('actes-generique');
        return $document_info['id_d'];
    }

    /**
     * @throws DonneesFormulaireException
     * @throws NotFoundException
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
            'classification' => '1.1',
        ]);
        $donneesFormulaire->addFileFromData('arrete', 'arrete.pdf', __DIR__ . '/../../fixtures/vide.pdf');
        $donneesFormulaire->addFileFromData('autre_document_attache', 'autre_document_attache_0.txt', 'foo', 0);
        $donneesFormulaire->addFileFromData('autre_document_attache', 'autre_document_attache_1.txt', 'bar', 1);

        return $donneesFormulaire;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testSend(): void
    {
        $this->mockCurl([
            'https://pastell/api/v2/entite/34/document?type=actes-generique'
            => file_get_contents(__DIR__ . '/fixtures/api-response-create-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt'
            => file_get_contents(__DIR__ . '/fixtures/api-response-patch-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/arrete/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/1'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/action/send-tdt'
            => file_get_contents(__DIR__ . '/fixtures/api-response-action.json'),
        ]);

        $depotPastell = $this->getDepotPastell();
        $donneesFormulaire = $this->getDonneesFormulaire();
        static::assertSame(
            ['68hpWOt' => '68hpWOt'],
            $depotPastell->send($donneesFormulaire)
        );
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testSendWhenCantCreateDocument(): void
    {
        $this->mockCurl([
            'https://pastell/api/v2/entite/34/document?type=actes-generique' => '{"foo":"bar"}',
        ]);

        $depotPastell = $this->getDepotPastell();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Impossible de créer le dossier sur Pastell');
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testSendWhenFormulaireIsNotOk(): void
    {
        $this->mockCurl([
            'https://pastell/api/v2/entite/34/document?type=actes-generique'
            => file_get_contents(__DIR__ . '/fixtures/api-response-create-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt'
            => file_get_contents(__DIR__ . '/fixtures/api-response-patch-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/arrete/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-patch-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-patch-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/1'
            => file_get_contents(__DIR__ . '/fixtures/api-response-patch-document.json'),
        ]);

        $depotPastell = $this->getDepotPastell();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
        //phpcs:ignore Generic.Files.LineLength.TooLong
            "Impossible d'appeller l'action sur le document Pastell car le formulaire n'est pas valide : Le formulaire est incomplet : le champ «Acte» est obligatoire."
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testSendWhenActionFailed(): void
    {
        $this->mockCurl([
            'https://pastell/api/v2/entite/34/document?type=actes-generique'
            => file_get_contents(__DIR__ . '/fixtures/api-response-create-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt'
            => file_get_contents(__DIR__ . '/fixtures/api-response-patch-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/arrete/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/1'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/action/send-tdt'
            => file_get_contents(__DIR__ . '/fixtures/api-response-action-failed.json'),
        ]);

        $depotPastell = $this->getDepotPastell();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
        //phpcs:ignore Generic.Files.LineLength.TooLong
            "Erreur lors de l'appel à l'action sur le document : L'action « send-tdt »  n'est pas permise : or_1 n'est pas vérifiée"
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testSendWhenErrorInInputMetadata(): void
    {
        $depotPastell = $this->getDepotPastell('foo:%bar%');

        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "L'élement « bar » n'existe pas pour le type de dossier actes-generique"
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testWhenCallApiReturnNonOK(): void
    {
        $this->mockCurl(
            [
                'https://pastell/api/v2/entite/34/document?type=actes-generique' => '{"foo":"bar"}',
            ],
            404
        );

        $donneesFormulaire = $this->getDonneesFormulaire();
        $depotPastell = $this->getDepotPastell();

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Erreur 404 () lors de la réponse de Pastell');
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testWhenCallApiReturnNotJsonData(): void
    {
        $this->mockCurl([
            'https://pastell/api/v2/entite/34/document?type=actes-generique' => 'foo',
        ]);

        $donneesFormulaire = $this->getDonneesFormulaire();
        $depotPastell = $this->getDepotPastell();

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            'Message de Pastell non compréhensible : foo'
        );
        $depotPastell->send($donneesFormulaire);
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testSendDocumentWithoutAction(): void
    {
        $this->mockCurl([
            'https://pastell/api/v2/entite/34/document?type=actes-generique'
            => file_get_contents(__DIR__ . '/fixtures/api-response-create-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt'
            => file_get_contents(__DIR__ . '/fixtures/api-response-patch-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/arrete/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/autre_document_attache/1'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
        ]);

        $depotPastell = $this->getDepotPastell();
        $id_ce = $depotPastell->getConnecteurInfo()['id_ce'];
        $this->configureConnector($id_ce, [
            DepotPastell::PASTELL_ACTION => DepotPastell::NO_ACTION,
        ]);
        /** @var DepotPastell $depotPastell */
        $depotPastell = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $donneesFormulaire = $this->getDonneesFormulaire();
        static::assertSame(
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
        $this->mockCurl([
            'https://pastell/api/v2/entite/34/document?type=actes-generique'
            => file_get_contents(__DIR__ . '/fixtures/api-response-create-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt'
            => file_get_contents(__DIR__ . '/fixtures/api-response-patch-document.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/file/arrete/0'
            => file_get_contents(__DIR__ . '/fixtures/api-response-post-file.json'),
            'https://pastell/api/v2//entite/34/document/68hpWOt/action/send-tdt'
            => file_get_contents(__DIR__ . '/fixtures/api-response-action.json'),
        ]);

        $depotPastell = $this->getDepotPastell();
        $id_ce = $depotPastell->getConnecteurInfo()['id_ce'];
        $this->associateFluxWithConnector($id_ce, 'actes-generique', 'GED', 1);
        $id_d = $this->createActeGenerique();
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData([
            'objet' => 'Mon objet',
            'acte_nature' => 3,
            'envoi_tdt' => false,
            'numero_de_lacte' => '201905161006',
            'date_de_lacte' => '2019-05-01',
            'classification' => '1.1',
            'envoi_ged' => 1,
        ]);
        $donneesFormulaire->addFileFromData('arrete', 'arrete.pdf', __DIR__ . '/../../fixtures/vide.pdf');
        $this->getInternalAPI()->patch("/entite/1/document/$id_d/externalData/type_piece", ['type_pj' => ['22_NE']]);

        set_error_handler(static function ($errno, $errstr) {
            static::fail("Warning PHP inattendu: $errstr");
        }, E_WARNING);

        try {
            $info = $this->getInternalAPI()->post("entite/1/document/$id_d/action/send-ged");
            static::assertSame('Le dossier Mon objet a été versé sur le dépôt', $info['message']);
        } finally {
            restore_error_handler();
        }
    }
}
