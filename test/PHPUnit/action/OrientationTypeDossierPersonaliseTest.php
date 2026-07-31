<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class OrientationTypeDossierPersonaliseTest extends PastellTestCase
{
    /** @var TypeDossierLoader */
    private $typeDossierLoader;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->typeDossierLoader = $this->getObjectInstancier()->getInstance(TypeDossierLoader::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->typeDossierLoader->unload();
    }

    /**
     * @throws Exception
     */
    public function testCasNominal()
    {

        $this->typeDossierLoader->createTypeDossierDefinitionFile("cas-nominal");

        $info = $this->createDocument("cas-nominal");

        $id_d = $info['id_d'];

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->addFileFromData(
            "arrete",
            "arrete.pdf",
            "aaa"
        );
        $info = $this->getInternalAPI()->patch("/Entite/1/document/$id_d", [
            "objet" => 'test',
            "prenom_agent" => "eric",
            "nom_agent" => "foo",
            "iparapheur_sous_type" => "TEST",
            'to' => 'foo@bar.com'
        ]);

        $this->assertEquals(1, $info['formulaire_ok']);

        $result = $this->triggerActionOnDocument($id_d, "orientation", self::ID_E_COL, self::ID_U_ADMIN);
        $this->assertTrue($result);

        $info = $this->getInternalAPI()->get("/Entite/1/document/$id_d");
        $this->assertEquals('preparation-send-iparapheur', $info['last_action']['action']);
    }

    public function testWithFluxFromFluxStudio()
    {
        $this->getObjectInstancier()->getInstance(ExtensionLoader::class)->loadExtension([__DIR__ . "/fixtures/"]);

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);

        $roleSQL->addDroit('admin', DroitService::getDroitFor('test-simple', DroitType::LECTURE));
        $roleSQL->addDroit('admin', DroitService::getDroitFor('test-simple', DroitType::EDITION));

        $id_d = $this->createDocument("test-simple")['id_d'];

        $result = $this->triggerActionOnDocument($id_d, 'orientation');
        $this->assertTrue($result);
        $info = $this->getInternalAPI()->get("/Entite/1/document/$id_d");
        $this->assertEquals('preparation-transformation', $info['last_action']['action']);
    }

    public function testWithNotFluxStudio()
    {
        $this->getObjectInstancier()->getInstance(ExtensionLoader::class)->loadExtension([__DIR__ . "/fixtures/"]);

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);

        $roleSQL->addDroit('admin', DroitService::getDroitFor('test-failed', DroitType::LECTURE));
        $roleSQL->addDroit('admin', DroitService::getDroitFor('test-failed', DroitType::EDITION));

        $id_d = $this->createDocument("test-failed")['id_d'];

        $result = $this->triggerActionOnDocument($id_d, 'orientation');
        $this->assertFalse($result);
        $this->assertLastLog(
            "executeOnDocument - fin - id_e=1,id_d=$id_d,id_u=1,action_name=orientation - KO - \"La d\u00e9finition du type de dossier est vide\""
        );
    }
}
