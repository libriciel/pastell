<?php

class TypeDossierTdtActesTest extends PastellTestCase
{
    public const TDT_ACTES_ONLY = 'tdt-actes-only';
    public const TDT_ACTES_MULTI_ANNEXES = 'tdt-actes-multi-annexes';

    private TypeDossierLoader $typeDossierLoader;
    private string $dataDir;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->typeDossierLoader = $this->getObjectInstancier()->getInstance(TypeDossierLoader::class);
        $this->dataDir = $this->getObjectInstancier()->getInstance('data_dir');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->typeDossierLoader->unload();
    }

    /**
     * @throws TypeDossierException
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testEtapeTdtActes(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::TDT_ACTES_ONLY);

        $info_connecteur = $this->createConnector('fakeTdt', 'Bouchon Tdt');

        $connecteurInfo = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire(
            $info_connecteur['id_ce']
        );

        $connecteurInfo->addFileFromCopy(
            'classification_file',
            'classifiction.xml',
            $this->dataDir . '/connector/fakeTdt/classification.xml'
        );

        $this->associateFluxWithConnector($info_connecteur['id_ce'], self::TDT_ACTES_ONLY, 'TdT');

        $id_d = $this->createDocument(self::TDT_ACTES_ONLY)['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData(['titre' => 'Foo']);
        $donneesFormulaire->addFileFromData('fichier', 'fichier.txt', 'bar');

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "orientation")
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "send-tdt")
        );
        $this->assertLastMessage("Le document a été envoyé au contrôle de légalité");

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "verif-tdt")
        );
        $this->assertLastMessage("L'acquittement du contrôle de légalité a été reçu.");

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "orientation")
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $this->assertLastDocumentAction('termine', $id_d);

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "annulation-tdt")
        );
        $this->assertLastMessage("Une notification d'annulation a été envoyée au contrôle de légalité");
        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, "verif-annulation-tdt")
        );
        $this->assertLastMessage("L'acquittement pour l'annulation de l'acte a été reçu.");
        $this->assertLastDocumentAction('annuler-tdt', $id_d);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("L'action « tamponner-tdt »  n'est pas permise : no-action n'est pas vérifiée");
        $this->getInternalAPI()->post("/entite/1/document/$id_d/action/tamponner-tdt");
    }

    /**
     * @throws TypeDossierException
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testEtapeTdtActesWithMultipleAnnexeElements(): void
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::TDT_ACTES_MULTI_ANNEXES);

        $info_connecteur = $this->createConnector('fakeTdt', 'Bouchon Tdt');

        $connecteurInfo = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire(
            $info_connecteur['id_ce']
        );

        $connecteurInfo->addFileFromCopy(
            'classification_file',
            'classification.xml',
            __DIR__ . '/../../module/actes-generique/fixtures/classification.xml'
        );

        $this->associateFluxWithConnector($info_connecteur['id_ce'], self::TDT_ACTES_MULTI_ANNEXES, 'TdT');

        $id_d = $this->createDocument(self::TDT_ACTES_MULTI_ANNEXES)['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData(['objet' => 'Foo', 'acte_nature' => 3]);
        $donneesFormulaire->addFileFromData('actes', 'arrete.pdf', 'foo');
        $donneesFormulaire->addFileFromData('annexe', 'annexe1.pdf', 'bar', 0);
        $donneesFormulaire->addFileFromData('annexe', 'annexe2.pdf', 'baz', 1);
        $donneesFormulaire->addFileFromData('autre_annexe', 'annexe3.pdf', 'bazz', 0);

        $info = $this->getInternalAPI()->patch(
            "/entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['41_NC', '22_DP', '22_AV', '22_TA']]
        );
        $this->assertEquals('41_NC', $info['data']['type_acte']);
        $this->assertEquals('["22_DP","22_AV","22_TA"]', $info['data']['type_pj']);
        $this->assertEquals('4 fichier(s) typé(s)', $info['data']['type_piece']);

        $expectedJson = [
            [
                'filename' => 'arrete.pdf',
                'typologie' => 'Notification de création ou de vacance de poste (41_NC)',
            ],
            [
                'filename' => 'annexe1.pdf',
                'typologie' => 'Document photographique (22_DP)',
            ],
            [
                'filename' => 'annexe2.pdf',
                'typologie' => 'Avis (22_AV)',
            ],
            [
                'filename' => 'annexe3.pdf',
                'typologie' => 'Tableau (22_TA)',
            ],
        ];
        $this->assertJsonStringEqualsJsonString(
            json_encode($expectedJson),
            $donneesFormulaire->getFileContent('type_piece_fichier')
        );

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, 'orientation')
        );
        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, 'send-tdt')
        );
        $this->assertLastMessage('Le document a été envoyé au contrôle de légalité');

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, 'verif-tdt')
        );
        $this->assertLastMessage("L'acquittement du contrôle de légalité a été reçu.");

        // Les annexes tamponnées sont reprises dans l'ordre d'envoi au Tdt,
        // c'est-à-dire élément par élément du mapping `autre_document_attache`
        $this->assertSame(
            ['annexe1-tampon.pdf', 'annexe2-tampon.pdf', 'annexe3-tampon.pdf'],
            $this->getDonneesFormulaireFactory()->get($id_d)->get('annexes_tamponnees')
        );
    }
}
