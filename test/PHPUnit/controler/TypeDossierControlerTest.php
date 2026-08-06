<?php

use Pastell\Service\TypeDossier\TypeDossierActionService;
use Pastell\Service\TypeDossier\TypeDossierEditionService;
use Pastell\Service\TypeDossier\TypeDossierExportService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class TypeDossierControlerTest extends ControlerTestCase
{
    /**
     * @return TypeDossierControler
     */
    private function getTypeDossierController()
    {
        return $this->getControlerInstance(TypeDossierControler::class);
    }

    /**
     * @param $type_dossier_id
     * @return int
     * @throws TypeDossierException
     */
    private function createTypeDossier($type_dossier_id): int
    {
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = $type_dossier_id;
        $typeDossierEditionService = $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
        return $typeDossierEditionService->create($typeDossierProperties);
    }
    /**
     * @throws Exception
     */
    public function testExportAction()
    {
        $id_t = $this->copyTypeDossierTest();
        $typeDossierExportService = $this->getObjectInstancier()->getInstance(TypeDossierExportService::class);
        $typeDossierExportService->setTimeFunction(function () {
            return "42";
        });
        $this->setGetInfo(['id_t' => $id_t]);
        $this->expectOutputRegex("#cas-nominal.json#");
        $this->getTypeDossierController()->exportAction();
    }

    /**
     * @throws Exception
     */
    public function testDoDeleteAction()
    {

        $typeDossierSQL = $this->getObjectInstancier()->getInstance(TypeDossierSQL::class);
        $typeDossierPersonnaliseDirectoryManager = $this->getObjectInstancier()->getInstance(TypeDossierPersonnaliseDirectoryManager::class);

        $id_t = $this->copyTypeDossierTest();
        $this->assertTrue($typeDossierSQL->exists($id_t));
        $this->assertFileExists($typeDossierPersonnaliseDirectoryManager->getTypeDossierPath($id_t));
        $type_dossier_path = $typeDossierPersonnaliseDirectoryManager->getTypeDossierPath($id_t);
        $this->setGetInfo(['id_t' => $id_t]);
        try {
            $this->getTypeDossierController()->doDeleteAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression("#Le type de dossier <b>cas-nominal</b> a été supprimé#", $e->getMessage());
        }
        $this->assertFalse($typeDossierSQL->exists($id_t));
        $this->assertFileDoesNotExist($type_dossier_path);
    }

    /**
     * @throws Exception
     */
    public function testDoDeleteActionWhenTypeDossierIsUsed()
    {
        $typeDossierSQL = $this->getObjectInstancier()->getInstance(TypeDossierSQL::class);
        $typeDossierPersonnaliseDirectoryManager = $this->getObjectInstancier()->getInstance(TypeDossierPersonnaliseDirectoryManager::class);

        $id_t = $this->copyTypeDossierTest();

        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', DroitService::getDroitFor('cas-nominal', DroitType::LECTURE));
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', DroitService::getDroitFor('cas-nominal', DroitType::EDITION));
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->deleteCache(1, 1);

        $this->createDocument('cas-nominal');

        $this->assertTrue($typeDossierSQL->exists($id_t));
        $this->assertFileExists($typeDossierPersonnaliseDirectoryManager->getTypeDossierPath($id_t));

        $this->setGetInfo(['id_t' => $id_t]);
        try {
            $this->getTypeDossierController()->doDeleteAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression("#Le type de dossier cas-nominal est utilisé par des dossiers qui
                ne sont pas dans l'état <i>terminé</i> ou <i>erreur fatale</i>#", $e->getMessage());
            $this->assertMatchesRegularExpression("#Bourg-en-Bresse</a>\s*</td>\s*<td>1</td>#", $e->getMessage());
        }
        $this->assertTrue($typeDossierSQL->exists($id_t));
        $this->assertFileExists($typeDossierPersonnaliseDirectoryManager->getTypeDossierPath($id_t));
    }

    /**
     * @throws Exception
     */
    public function testDoEditionAction()
    {
        $id_type_dossier = 'test-52';
        $this->getTypeDossierController();
        $this->setGetInfo(['id_type_dossier' => $id_type_dossier]);
        try {
            $this->getTypeDossierController()->doEditionAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression("#Le type de dossier personnalisé $id_type_dossier a été créé#", $e->getMessage());
        }

        $typeDossierSQL = $this->getObjectInstancier()->getInstance(TypeDossierSQL::class);
        $id_t = $typeDossierSQL->getByIdTypeDossier($id_type_dossier);
        $this->assertEquals($id_type_dossier, $typeDossierSQL->getInfo($id_t)['id_type_dossier']);

        $typeDossierActionService = $this->getObjectInstancier()->getInstance(TypeDossierActionService::class);
        $type_dossier_action_message = $typeDossierActionService->getById($id_t)[0]['message'];
        $this->assertEquals("Le type de dossier personnalisé $id_type_dossier a été créé", $type_dossier_action_message);
    }

    /**
     * @throws Exception
     */
    public function testDoEditionActionWhenIdTypeDossierIsNull()
    {
        try {
            $this->getTypeDossierController()->doEditionAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression("#Aucun identifiant de type de dossier fourni#", $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function testDoEditionActionWhenIdTypeDossierDoesNotMatchRegexp()
    {
        $this->getTypeDossierController();
        $this->setGetInfo(['id_type_dossier' => 'AAAAA']);
        try {
            $this->getTypeDossierController()->doEditionAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression(
                "#L'identifiant du type de dossier « AAAAA » ne respecte pas l'expression rationnelle#u",
                $e->getMessage()
            );
        }
    }
    /**
     * @throws Exception
     */
    public function testDoEditionActionWhenIdTypeDossierOverflowMaxLength()
    {
        $this->getTypeDossierController();
        $this->setGetInfo(['id_type_dossier' => str_repeat("a", 33)]);
        try {
            $this->getTypeDossierController()->doEditionAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression(
                "#L'identifiant du type de dossier « aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa » ne respecte pas l'expression rationnelle#",
                $e->getMessage()
            );
        }
    }

    /**
     * @throws TypeDossierException
     */
    public function testDoNewEtapeAction()
    {
        $this->getTypeDossierController();
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = 'test-42';
        $typeDossierEditionService = $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
        $id_t = $typeDossierEditionService->create($typeDossierProperties);
        $this->setGetInfo(['id_t' => $id_t,'type' => 'signature']);

        try {
            $this->getTypeDossierController()->doNewEtapeAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression("#/TypeDossier/editionEtape\?id_t=$id_t&num_etape=0#", $e->getMessage());
        }

        $typeDossierActionService = $this->getObjectInstancier()->getInstance(TypeDossierActionService::class);
        $type_dossier_action_message = $typeDossierActionService->getById($id_t)[0]['message'];
        $this->assertEquals("La modification des étapes du cheminement a été enregistrée", $type_dossier_action_message);
    }

    public function testDoNewEtapeActionNoSpecificData()
    {
        $this->getTypeDossierController();
        $id_t = $this->createTypeDossier('test-42');
        $this->setGetInfo(['id_t' => $id_t,'type' => 'depot']);

        try {
            $this->getTypeDossierController()->doNewEtapeAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression("#/TypeDossier/detail\?id_t=$id_t#", $e->getMessage());
        }

        $typeDossierActionService = $this->getObjectInstancier()->getInstance(TypeDossierActionService::class);
        $type_dossier_action_message = $typeDossierActionService->getById($id_t)[0]['message'];
        $this->assertEquals("La modification des étapes du cheminement a été enregistrée", $type_dossier_action_message);
    }

    public function testDelete()
    {
        $typeDossierSQL = $this->getObjectInstancier()->getInstance(TypeDossierSQL::class);

        $this->getTypeDossierController();
        $id_t = $this->createTypeDossier('test-42');
        $this->assertTrue($typeDossierSQL->exists($id_t));
        $this->setGetInfo(['id_t' => $id_t]);
        try {
            $this->getTypeDossierController()->doDeleteAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression("#Le type de dossier <b>test-42</b> a été supprimé#", $e->getMessage());
        }
        $this->assertFalse($typeDossierSQL->exists($id_t));
    }

    public function testDeleteWhenUsedByDroit()
    {
        $typeDossierSQL = $this->getObjectInstancier()->getInstance(TypeDossierSQL::class);

        $this->getTypeDossierController();
        $id_t = $this->createTypeDossier('test-42');
        $this->assertTrue($typeDossierSQL->exists($id_t));

        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', DroitService::getDroitFor('test-42', DroitType::LECTURE));
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', DroitService::getDroitFor('test-42', DroitType::EDITION));

        $this->setGetInfo(['id_t' => $id_t]);
        try {
            $this->getTypeDossierController()->doDeleteAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression(
                "#Le type de dossier <b>test-42</b> est utilisé par le rôle « admin »#",
                $e->getMessage()
            );
        }
        $this->assertTrue($typeDossierSQL->exists($id_t));
    }

    public function testDeleteWhenConnecteurIsAssociatedWithTypeDossier()
    {
        $typeDossierSQL = $this->getObjectInstancier()->getInstance(TypeDossierSQL::class);

        $this->getTypeDossierController();
        $id_t = $this->createTypeDossier('test-42');

        $fluxEntiteSQL = $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class);

        $fluxEntiteSQL->addConnecteur(1, 'test-42', 'GED', 42);

        $this->setGetInfo(['id_t' => $id_t]);
        try {
            $this->getTypeDossierController()->doDeleteAction();
        } catch (Exception $e) {
            $this->assertMatchesRegularExpression(
                "#Le type de dossier <b>test-42</b> a été associé avec des connecteurs sur l'entité Bourg-en-Bresse \(id_e=1\)#",
                $e->getMessage()
            );
        }
        $this->assertTrue($typeDossierSQL->exists($id_t));
    }

    public function testSetAllFatalError(): void
    {
        $this->createTypeDossier('fluxstudio');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', DroitService::getDroitFor('fluxstudio', DroitType::LECTURE));
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', DroitService::getDroitFor('fluxstudio', DroitType::EDITION));
        $this->createDocument('fluxstudio');
        $docInfo = $this->getObjectInstancier()->getInstance(DocumentSQL::class)->getAllIdByType('fluxstudio');
        $lastActionDoc = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class)->getLastAction($docInfo[0]['id_e'], $docInfo[0]['id_d']);
        $this->assertEquals('creation', $lastActionDoc, '');

        $this->setPostInfo(['id_type_dossier' => 'fluxstudio']);
        try {
            $this->getTypeDossierController()->doPutInFatalErrorAction();
        } catch (Exception $e) {
        }

        $lastActionDoc = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class)
            ->getLastAction($docInfo[0]['id_e'], $docInfo[0]['id_d']);
        $this->assertEquals('fatal-error', $lastActionDoc, '');
    }

    /**
     * @throws TypeDossierException
     * @throws Exception
     */
    public function testNoJobLeftFatalErrorFromTypeDossier(): void
    {
        $this->getObjectInstancier()->getInstance(TypeDossierLoader::class)->createTypeDossierDefinitionFile(
            'cas-nominal'
        );
        $info = $this->createDocument('cas-nominal');
        $id_d = $info['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->addFileFromData(
            'arrete',
            'arrete.pdf',
            'aaa'
        );
        $this->configureDocument($id_d, [
            'objet' => 'test',
            'prenom_agent' => 'eric',
            'nom_agent' => 'foo',
            'iparapheur_sous_type' => 'TEST',
            'to' => 'foo@bar.com',
        ]);
        $this->triggerActionOnDocument($id_d, 'orientation');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        static::assertTrue($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));
        $this->setPostInfo([
            'id_type_dossier' => 'cas-nominal',
        ]);
        try {
            $this->getTypeDossierController()->doPutInFatalErrorAction();
        } catch (Exception) {
        }
        static::assertFalse($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));
    }

    public static function provideDeleteActionsWithFolder(): array
    {
        return [
            ['deleteAction'],
            ['doDeleteAction'],
        ];
    }

    /**
     * @dataProvider provideDeleteActionsWithFolder
     * @throws TypeDossierException
     */
    public function testDeleteBlockedWithFolder(string $action): void
    {
        $type_dossier_id = 'test-42';
        $id_t = $this->createTypeDossier($type_dossier_id);

        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', "$type_dossier_id:lecture");
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', "$type_dossier_id:edition");
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->deleteCache(self::ID_E_COL, self::ID_U_ADMIN);

        $this->createDocument($type_dossier_id);

        $this->setGetInfo(['id_t' => $id_t]);
        try {
            $this->getTypeDossierController()->$action();
            static::fail();
        } catch (LastErrorException $e) {
            static::assertMatchesRegularExpression(
                "#/TypeDossier/list#",
                $e->getMessage()
            );
            static::assertMatchesRegularExpression(
                "#Le type de dossier $type_dossier_id est utilisé par des dossiers#",
                $e->getMessage()
            );
        }
    }

    public static function provideEditionActionsWithFolder(): array
    {
        return [
            ['editionAction'],
            ['doEditionAction'],
        ];
    }

    /**
     * @dataProvider provideEditionActionsWithFolder
     * @throws TypeDossierException
     */
    public function testEditionBlockedWithFolder(string $action): void
    {
        $type_dossier_id = 'test-42';
        $id_t = $this->createTypeDossier($type_dossier_id);

        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', "$type_dossier_id:lecture");
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', "$type_dossier_id:edition");
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->deleteCache(self::ID_E_COL, self::ID_U_ADMIN);

        $this->createDocument($type_dossier_id);

        $this->setGetInfo(['id_t' => $id_t, 'id_type_dossier' => "$type_dossier_id-new"]);
        try {
            $this->getTypeDossierController()->$action();
            static::fail();
        } catch (LastErrorException $e) {
            static::assertMatchesRegularExpression(
                "#/TypeDossier/detail\?id_t=$id_t#",
                $e->getMessage()
            );
            static::assertMatchesRegularExpression(
                "#Des dossiers du type.*$type_dossier_id.*existent#",
                $e->getMessage()
            );
        }
    }

    public static function provideActionsWithActiveFolder(): array
    {
        return [
            ['editionElementAction'],
            ['doEditionElementAction'],
            ['deleteElementAction'],
            ['editionEtapeAction'],
            ['doEditionEtapeAction'],
            ['deleteEtapeAction'],
            ['sortEtapeAction'],
            ['newEtapeAction'],
            ['doNewEtapeAction'],
        ];
    }

    /**
     * @dataProvider provideActionsWithActiveFolder
     * @throws TypeDossierException
     */
    public function testBlockedWithActiveFolder(string $action): void
    {
        $type_dossier_id = 'test-42';
        $id_t = $this->createTypeDossier($type_dossier_id);

        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', "$type_dossier_id:lecture");
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->addDroit('admin', "$type_dossier_id:edition");
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->deleteCache(self::ID_E_COL, self::ID_U_ADMIN);

        $this->createDocument($type_dossier_id);

        $this->setGetInfo(['id_t' => $id_t]);
        try {
            $this->getTypeDossierController()->$action();
            static::fail();
        } catch (LastErrorException $e) {
            static::assertMatchesRegularExpression(
                "#/TypeDossier/detail\?id_t=$id_t#",
                $e->getMessage()
            );
            static::assertMatchesRegularExpression(
                "#Le type de dossier $type_dossier_id est utilisé par des dossiers#",
                $e->getMessage()
            );
        }
    }
}
