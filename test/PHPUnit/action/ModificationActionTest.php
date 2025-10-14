<?php

class ModificationActionTest extends PastellTestCase
{
    use TypeDossierLoaderTestTrait;

    private const FILENAME_FIXTURE = 'foo.pdf';
    private const TYPE_DOSSIER_FIXTURE_PATH = __DIR__ . "/fixtures/test-bug-1096.json";
    /**
     * @throws NotFoundException
     */
    public function testNoEditableContent()
    {
        $document = $this->createDocument('test');

        $this->configureDocument($document['id_d'], [
            'test2' => 'test required field'
        ]);

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $this->assertSame('test required field', $donnesFormulaire->get('test2'));

        $this->triggerActionOnDocument($document['id_d'], 'useless');

        $this->configureDocument($document['id_d'], [
            'test2' => 'test new value'
        ]);
        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $this->assertSame('test required field', $donnesFormulaire->get('test2'));
    }

    public function testDontRedirectOnAPICall()
    {
        $document = $this->createDocument('helios-generique');
        $this->expectOutputString("");
        $result = $this->getInternalAPI()->patch("/Entite/1/Document/{$document['id_d']}", ['envoi_sae' => 1]);
        $this->assertEquals(1, $result['content']['data']['envoi_sae']);
    }

    /**
     * @throws NotFoundException
     */
    public function testModifActionWhenFieldIsNotEditable()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];

        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);

        $actionChange->addAction($id_d, 1, 0, 'recu-iparapheur', 'test');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("Le contenu de arrete n'est pas éditable");
        $this->getInternalAPI()->post(
            "/Entite/1/Document/$id_d/file/arrete",
            ['file_content' => 'test', 'file_name' => self::FILENAME_FIXTURE]
        );

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $this->assertEmpty($donneesFormulaire->getFileContent('arrete'));
    }

    /**
     * @throws NotFoundException
     */
    public function testModifActionWhenFieldIsEditable()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];

        $this->getInternalAPI()->post(
            "/Entite/1/Document/$id_d/file/arrete",
            [
                'file_content' => file_get_contents(__DIR__ . "/../fixtures/vide.pdf"),
                'file_name' => self::FILENAME_FIXTURE
            ]
        );

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $this->assertEquals(
            file_get_contents(__DIR__ . "/../fixtures/vide.pdf"),
            $donneesFormulaire->getFileContent('arrete')
        );
    }

    /**
     * @throws TypeDossierException
     */
    public function testWhenTitreFieldIsAfile()
    {
        $this->loadTypeDossier(self::TYPE_DOSSIER_FIXTURE_PATH);
        $result = $this->createDocumentTestBug1096();
        $this->assertEquals(self::FILENAME_FIXTURE, $result['content']['info']['titre']);
        $this->unloadTypeDossier();
    }

    /**
     * @throws TypeDossierException
     */
    public function testWhenDeleteAFileField()
    {
        $this->loadTypeDossier(self::TYPE_DOSSIER_FIXTURE_PATH);
        $result = $this->createDocumentTestBug1096();
        $id_d = $result['content']['info']['id_d'];
        $result = $this->getInternalAPI()->delete("/Entite/1/Document/$id_d/file/un_fichier");
        $this->assertEquals($id_d, $result['info']['titre']);
        $this->unloadTypeDossier();
    }

    private function createDocumentTestBug1096()
    {
        $id_d = $this->createDocument('test-bug-1096')['id_d'];

        return $this->getInternalAPI()->post(
            "/Entite/1/Document/$id_d/file/un_fichier",
            [
                'file_content' => 'bar',
                'file_name' => self::FILENAME_FIXTURE
            ]
        );
    }

    public function testAddOrUpdateAction(): void
    {
        $documentEntite = $this->getObjectInstancier()->getInstance(DocumentEntite::class);
        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);

        $documentId = $this->createDocument('ls-document-pdf')['id_d'];
        $this->getInternalAPI()->patch("/Entite/1/Document/{$documentId}", ['envoi_sae' => 1]);
        self::assertSame(
            $documentActionEntite->getLastActionInfo(self::ID_E_COL, $documentId)['date'],
            $documentEntite->getFromAction(
                'ls-document-pdf',
                'modification'
            )[0]['last_action_date']
        );

        sleep(1);
        $this->getInternalAPI()->patch("/Entite/1/Document/{$documentId}", ['envoi_sae' => 0]);
        self::assertLessThan(
            $documentActionEntite->getLastActionInfo(self::ID_E_COL, $documentId)['date'],
            $documentEntite->getFromAction(
                'ls-document-pdf',
                'modification'
            )[0]['last_action_date']
        );
    }
}
