<?php

use Pastell\Service\TypeDossier\TypeDossierEditionService;
use Pastell\Service\TypeDossier\TypeDossierImportService;
use Pastell\Service\TypeDossier\TypeDossierManager;

class TypeDossierServiceTest extends PastellTestCase
{
    private function getTypeDossierService()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierService::class);
    }

    private function getTypeDossierManager()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierManager::class);
    }

    /**
     * @throws TypeDossierException
     */
    public function testEditionElement()
    {
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = "test";
        $typeDossierEditionService = $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
        $typeDossierManager = $this->getObjectInstancier()->getInstance(TypeDossierManager::class);
        $id_t = $typeDossierEditionService->edit(0, $typeDossierProperties);

        $recuperateur = new Recuperateur([
            'element_id' => 'nom_agent',
            'name' => "Nom de l'agent",
            'type' => 'text',
            'commentaire' => "Mettre ici le nom de l'agent",
            'requis' => true,
            'champs_affiches' => true,
            'champs_recherche_avancee' => true,
            'titre' => true,
            'default_value' => 'Anderson'
        ]);
        $this->getTypeDossierService()->editionElement($id_t, $recuperateur);
        $file_content = $typeDossierManager->getRawData($id_t);
        $this->assertEquals(
            [
                'id_type_dossier' => 'test',
                'nom' => '',
                'type' => '',
                'description' => '',
                'nom_onglet' => '',
                'formulaireElement' =>
                    [
                        0 =>
                            [
                                'element_id' => 'nom_agent',
                                'name' => 'Nom de l\'agent',
                                'type' => 'text',
                                'commentaire' => 'Mettre ici le nom de l\'agent',
                                'requis' => '1',
                                'champs_affiches' => '1',
                                'champs_recherche_avancee' => '1',
                                'titre' => '1',
                                'select_value' => '',
                                'preg_match' => '',
                                'preg_match_error' => '',
                                'content_type' => '',
                                'default_value' => 'Anderson',

                            ],
                    ],
                'etape' =>
                    [],
                'restriction_pack' => ''
            ],
            $file_content
        );
        $type_dossier_data = $typeDossierManager->getTypeDossierProperties($id_t);
        $this->assertEquals(
            "Mettre ici le nom de l'agent",
            $type_dossier_data->formulaireElement[0]->commentaire
        );
    }

    /**
     * @throws Exception
     */
    public function testChangeTitreElement()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierDefinition = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);

        $this->assertTrue((bool)$typeDossierDefinition->formulaireElement[0]->titre);
        $this->assertFalse((bool)$typeDossierDefinition->formulaireElement[2]->titre);
        $this->getTypeDossierService()->editionElement($id_t, new Recuperateur([
            'orig_element_id' => 'nom_agent',
            'element_id' => 'nom_agent',
            'type' => 'text',
            'titre' => 'on'
        ]));
        $typeDossierDefinition = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertFalse((bool)$typeDossierDefinition->formulaireElement[0]->titre);
        $this->assertTrue((bool)$typeDossierDefinition->formulaireElement[2]->titre);
    }

    /**
     * @throws Exception
     */
    public function testGetFormulaireElement()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierFormulaireElement =
            $this->getTypeDossierService()->getFormulaireElement($id_t, 'objet');
        $this->assertEquals('Objet', $typeDossierFormulaireElement->name);
    }

    /**
     * @throws Exception
     */
    public function testGetFormulaireElementEmpty()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierFormulaireElement =
            $this->getTypeDossierService()->getFormulaireElement($id_t, 'foo');
        $this->assertEquals('', $typeDossierFormulaireElement->name);
    }

    /**
     * @throws Exception
     */
    public function testEditionElementChangeElementId()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierFormulaireElement =
            $this->getTypeDossierService()->getFormulaireElement($id_t, 'objet');
        $this->assertEquals('Objet', $typeDossierFormulaireElement->name);
        $this->getTypeDossierService()->editionElement(
            $id_t,
            new Recuperateur([
                'orig_element_id' => 'objet',
                'element_id' => 'new_objet',
                'name' => 'Objet',
                'type' => 'text'
            ])
        );
        $typeDossierFormulaireElement =
            $this->getTypeDossierService()->getFormulaireElement($id_t, 'objet');
        $this->assertEquals('', $typeDossierFormulaireElement->name);
        $typeDossierFormulaireElement =
            $this->getTypeDossierService()->getFormulaireElement($id_t, 'new_objet');
        $this->assertEquals('Objet', $typeDossierFormulaireElement->name);
    }

    /**
     * @throws Exception
     */
    public function testEditWithoutElementId()
    {
        $id_t = $this->copyTypeDossierTest();
        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage("L'identifiant de l'élément est obligatoire");
        $this->getTypeDossierService()->editionElement($id_t, new Recuperateur([]));
    }


    /**
     * @throws Exception
     */
    public function testEditWithSameElementId()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierProperties = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertCount(5, $typeDossierProperties->formulaireElement);
        try {
            $this->getTypeDossierService()->editionElement(
                $id_t,
                new Recuperateur(['element_id' => 'prenom_agent', 'type' => 'text'])
            );
        } catch (TypeDossierException $e) {
            $this->assertEquals("L'identifiant « prenom_agent » existe déjà sur ce formulaire", $e->getMessage());
        }
        $typeDossierProperties = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertCount(5, $typeDossierProperties->formulaireElement);
    }

    /**
     * @throws Exception
     */
    public function testDeleteElement()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierData =
            $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertTrue($this->getTypeDossierService()->hasFormulaireElement($typeDossierData, 'nom_agent'));

        $this->getTypeDossierService()->deleteElement($id_t, 'nom_agent');
        $typeDossierData =
            $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertFalse($this->getTypeDossierService()->hasFormulaireElement($typeDossierData, 'nom_agent'));
    }

    /**
     * @throws Exception
     */
    public function testSortElement()
    {
        $id_t = $this->copyTypeDossierTest();
        $sort_order = [
            'objet',
            'nom_agent',
            'prenom_agent',
            'annexe',
            'arrete'
        ];
        $this->getTypeDossierService()->sortElement($id_t, $sort_order);

        $typeDossierData =
            $this->getTypeDossierManager()->getTypeDossierProperties($id_t);

        $result = [];
        foreach ($typeDossierData->formulaireElement as $i => $formulaireElementProperties) {
            $result[] = $formulaireElementProperties->element_id;
        }

        $this->assertEquals($sort_order, $result);
    }


    /**
     * @throws Exception
     */
    public function testSortElementMissedElement()
    {
        $id_t = $this->copyTypeDossierTest();
        $sort_order = [
            'objet',
            'prenom_agent',
            'annexe',
            'arrete'
        ];
        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage("Impossible de retrier le tableau");
        $this->getTypeDossierService()->sortElement($id_t, $sort_order);
    }

    /**
     * @throws Exception
     */
    public function testgetFieldWithType()
    {
        $id_t = $this->copyTypeDossierTest();
        $this->assertEquals(
            ['objet', 'prenom_agent', 'nom_agent'],
            array_keys($this->getTypeDossierService()->getFieldWithType(
                $id_t,
                TypeDossierFormulaireElementManager::TYPE_TEXT
            ))
        );
    }

    /**
     * @throws TypeDossierException
     * @throws UnrecoverableException
     */
    public function testNewEtape()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierData = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertFalse(
            isset($typeDossierData->etape[5])
        );
        $this->getTypeDossierService()->newEtape($id_t, new Recuperateur([
            'type' => 'signature'
        ]));
        $typeDossierData = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertEquals(
            'signature',
            $typeDossierData->etape[5]->type
        );
    }

    /**
     * @throws Exception
     */
    public function testEditionEtape()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 0);
        $this->assertEquals('arrete', $typeDossierEtapeInfo->specific_type_info['document_a_signer']);

        $this->getTypeDossierService()->editionEtape($id_t, new Recuperateur([
            'num_etape' => 0,
            'document_a_signer' => 'foo'
        ]));
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 0);
        $this->assertEquals('foo', $typeDossierEtapeInfo->specific_type_info['document_a_signer']);
    }

    /**
     * @throws Exception
     */
    public function testDeleteEtape()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 2);
        $this->assertEquals('mailsec', $typeDossierEtapeInfo->type);
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 3);
        $this->assertEquals('depot', $typeDossierEtapeInfo->type);
        $this->assertCount(5, $this->getTypeDossierManager()->getTypeDossierProperties($id_t)->etape);
        $this->getTypeDossierService()->deleteEtape($id_t, 2);
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 2);
        $this->assertEquals('depot', $typeDossierEtapeInfo->type);
        $this->assertCount(4, $this->getTypeDossierManager()->getTypeDossierProperties($id_t)->etape);
    }

    /**
     * @throws Exception
     */
    public function testSortEtape()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 0);
        $this->assertEquals('signature', $typeDossierEtapeInfo->type);
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 1);
        $this->assertEquals('depot', $typeDossierEtapeInfo->type);
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 2);
        $this->assertEquals('mailsec', $typeDossierEtapeInfo->type);
        $this->getTypeDossierService()->sortEtape($id_t, [1, 0, 2, 3, 4]);

        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 0);
        $this->assertEquals('depot', $typeDossierEtapeInfo->type);
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 1);
        $this->assertEquals('signature', $typeDossierEtapeInfo->type);
        $typeDossierEtapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 2);
        $this->assertEquals('mailsec', $typeDossierEtapeInfo->type);
    }

    /**
     * @throws Exception
     */
    public function testSortEtapeFailed()
    {
        $id_t = $this->copyTypeDossierTest();
        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage("Impossible de retrier le tableau");
        $this->getTypeDossierService()->sortEtape($id_t, [1, 0]);
    }

    /**
     * @throws Exception
     */
    public function testNewEtapeInfo()
    {
        $id_t = $this->copyTypeDossierTest();
        $etapeInfo = $this->getTypeDossierService()->getEtapeInfo($id_t, 6);
        $this->assertEquals('new', $etapeInfo->num_etape);
    }


    /**
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testGetNextActionFirstEtape()
    {
        $id_t = $this->copyTypeDossierTest();
        $this->assertEquals(
            'preparation-send-iparapheur',
            $this->getTypeDossierService()->getNextAction($id_t, 'modification')
        );
    }

    /**
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testGetNextActionNoStep()
    {
        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage('Impossible de trouver la première action à effectuer sur le document');
        $id_t = $this->copyTypeDossierTest(__DIR__ . '/fixtures/no-step.json');
        $this->getTypeDossierService()->getNextAction($id_t, 'modification');
    }

    /**
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testGetNextAction()
    {
        $id_t = $this->copyTypeDossierTest();
        $this->assertEquals(
            'preparation-send-ged_1',
            $this->getTypeDossierService()->getNextAction($id_t, 'recu-iparapheur')
        );
    }

    /**
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testGetLastAction()
    {
        $id_t = $this->copyTypeDossierTest();
        $this->assertEquals(
            'termine',
            $this->getTypeDossierService()->getNextAction($id_t, 'accepter-sae')
        );
    }

    /**
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testGetNextActionCheminementFacultatif()
    {
        $id_t = $this->copyTypeDossierTest();
        $this->assertEquals(
            'preparation-send-mailsec',
            $this->getTypeDossierService()->getNextAction($id_t, 'recu-iparapheur', [1, 0, 1, 1, 1])
        );
    }

    /**
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testGetNextActionCheminementFacultatifFirstStep()
    {
        $id_t = $this->copyTypeDossierTest();
        $this->assertEquals(
            'preparation-send-mailsec',
            $this->getTypeDossierService()->getNextAction($id_t, 'importation', [0, 0, 1, 1, 1])
        );
    }

    /**
     * @throws Exception
     */
    public function testGetEtapeWithSameType()
    {
        $typeDossierImportService = $this->getObjectInstancier()->getInstance(TypeDossierImportService::class);
        $id_t = $typeDossierImportService->importFromFilePath(__DIR__ . "/fixtures/double-ged.json")['id_t'];
        $typeDossierData = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertEquals(0, $typeDossierData->etape[0]->num_etape_same_type);
        $this->assertEquals(1, $typeDossierData->etape[1]->num_etape_same_type);
        $this->assertTrue($typeDossierData->etape[0]->etape_with_same_type_exists);
        $this->assertTrue($typeDossierData->etape[1]->etape_with_same_type_exists);
    }

    /**
     *
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testGetNextActionDoubleConnecteur()
    {
        $typeDossierImportService = $this->getObjectInstancier()->getInstance(TypeDossierImportService::class);
        $id_t = $typeDossierImportService->importFromFilePath(__DIR__ . "/fixtures/double-ged.json")['id_t'];
        $this->assertEquals(
            'preparation-send-ged_1',
            $this->getTypeDossierService()->getNextAction($id_t, "modification")
        );
    }

    /**
     * @throws Exception
     */
    public function testRebuildAll()
    {
        $typeDossierImportService = $this->getObjectInstancier()->getInstance(TypeDossierImportService::class);
        $typeDossierImportService->importFromFilePath(__DIR__ . "/fixtures/double-ged.json");
        $definition_path = $this->getObjectInstancier()->getInstance("workspacePath")
            . "/type-dossier-personnalise/module/double-ged/definition.yml";
        $this->assertFileExists($definition_path);
        unlink($definition_path);
        $this->assertFileDoesNotExist($definition_path);
        $this->getTypeDossierService()->rebuildAll();
        $this->assertFileExists($definition_path);
//        file_put_contents(__DIR__ . "/fixtures/double-ged.yml", file_get_contents($definition_path));
        $this->assertFileEquals(__DIR__ . "/fixtures/double-ged.yml", $definition_path);
    }

    /**
     * @throws TypeDossierException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testAddSameSecondStep()
    {
        $id_t = $this->copyTypeDossierTest(__DIR__ . '/fixtures/ged-only.json');

        $typeDossierData = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertSame(1, count($typeDossierData->etape));
        $this->getTypeDossierService()->newEtape($id_t, new Recuperateur([
            'type' => 'depot'
        ]));

        $typeDossierRawData = $this->getTypeDossierManager()->getRawData($id_t);
        $this->assertSame(2, count($typeDossierRawData['etape']));

        $this->assertTrue($typeDossierRawData['etape'][0]['etape_with_same_type_exists']);
        $this->assertTrue($typeDossierRawData['etape'][1]['etape_with_same_type_exists']);

        $this->assertSame(0, $typeDossierRawData['etape'][0]['num_etape_same_type']);
        $this->assertSame(1, $typeDossierRawData['etape'][1]['num_etape_same_type']);
    }

    /**
     * @throws TypeDossierException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testDeleteSameSecondStep()
    {
        $id_t = $this->copyTypeDossierTest(__DIR__ . '/fixtures/double-parapheur.json');

        $typeDossierData = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertSame(2, count($typeDossierData->etape));
        $this->getTypeDossierService()->deleteEtape($id_t, 1);

        $typeDossierRawData = $this->getTypeDossierManager()->getRawData($id_t);

        $this->assertSame(1, count($typeDossierRawData['etape']));
        $this->assertFalse($typeDossierRawData['etape'][0]['etape_with_same_type_exists']);
    }

    /**
     * @throws TypeDossierException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testEditStepWithMultipleSameSteps()
    {
        $id_t = $this->copyTypeDossierTest(__DIR__ . '/fixtures/double-ged.json');

        $typeDossierData = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        $this->assertSame(2, count($typeDossierData->etape));

        $this->getTypeDossierService()->editionEtape($id_t, new Recuperateur([
            'num_etape' => 1,
            'requis' => false
        ]));

        $typeDossierRawData = $this->getTypeDossierManager()->getRawData($id_t);
        $this->assertSame(2, count($typeDossierRawData['etape']));

        $this->assertTrue($typeDossierRawData['etape'][0]['etape_with_same_type_exists']);
        $this->assertSame(0, $typeDossierRawData['etape'][0]['num_etape_same_type']);

        $this->assertTrue($typeDossierRawData['etape'][1]['etape_with_same_type_exists']);
        $this->assertSame(1, $typeDossierRawData['etape'][1]['num_etape_same_type']);
        $this->assertSame('', $typeDossierRawData['etape'][1]['requis']);
    }

    /**
     * @throws TypeDossierException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testSortStepWithMultipleSameSteps()
    {
        $id_t = $this->copyTypeDossierTest(__DIR__ . '/fixtures/double-ged.json');
        $this->getTypeDossierService()->sortEtape($id_t, [1, 0]);

        $typeDossierRawData = $this->getTypeDossierManager()->getRawData($id_t);
        $this->assertSame(0, $typeDossierRawData['etape'][0]['num_etape_same_type']);
        $this->assertSame(1, $typeDossierRawData['etape'][1]['num_etape_same_type']);
    }


    /**
     * @throws Exception
     */
    public function testEditionElementSelectWithFalseDefaultKey(): void
    {
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = "test";
        $typeDossierEditionService = $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
        $id_t = $typeDossierEditionService->edit(0, $typeDossierProperties);

        $recuperateur = new Recuperateur([
            'element_id' => 'maliste',
            'name' => 'Ma liste',
            'type' => 'select',
            'select_value' => "0:a\nb\nc\nd",
            'default_value' => '5'
        ]);
        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage('La clé de la valeur par défaut ne correspond à aucune valeur de la liste déroulante');
        $this->getTypeDossierService()->editionElement($id_t, $recuperateur);
    }

    public function testEditionElementTextWithPregMatch(): void
    {
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = "test";
        $typeDossierEditionService = $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
        $id_t = $typeDossierEditionService->edit(0, $typeDossierProperties);

        $recuperateur = new Recuperateur([
            'element_id' => 'montexte',
            'name' => 'Mon texte',
            'type' => 'text',
            'preg_match' => '/^[0-9A-Z_]{2,15}$/',
            'default_value' => 'aaa'
        ]);
        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage('La valeur par défaut ne répond pas à l\'expression régulière.');
        $this->getTypeDossierService()->editionElement($id_t, $recuperateur);
    }
}
