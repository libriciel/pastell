<?php

class DocumentModificationServiceTest extends PastellTestCase
{
    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testRemoveFile()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->addFileFromData('arrete', 'arrete.txt', 'test');

        $info = $this->getInternalAPI()->get("entite/1/document/$id_d");
        $this->assertEquals('arrete.txt', $info['data']['arrete'][0]);

        $documentModificationService = $this->getObjectInstancier()->getInstance(DocumentModificationService::class);
        $documentModificationService->removeFile(1, 1, $id_d, "arrete", 0);


        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEmpty($donneesFormulaire->get('arrete'));


        $this->assertEquals(
            "Modification du document",
            $this->getJournalConsultationService()->getList(false, false, false, false, 0, 100)[0]['message']
        );
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function testModify()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];

        $documentModificationService = $this->getObjectInstancier()->getInstance(DocumentModificationService::class);

        $documentModificationService->modifyDocument(1, 1, $id_d, new Recuperateur([
            'numero_de_lacte' => '201906121136',
            'objet' => "L'objet de mon acte"
        ]), new FileUploader());

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEquals('201906121136', $donneesFormulaire->get('numero_de_lacte'));

        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);

        $info = $documentSQL->getInfo($id_d);
        $this->assertEquals("L'objet de mon acte", $info['titre']);
    }


    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function testModifyWhenHasEditableContent()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setTabData([
                'numero_de_lacte' => '201906121136',
                'objet' => "L'objet de mon acte"
            ]);

        $actionChange = $this->getObjectInstancier()->getInstance(ActionChange::class);
        $actionChange->addAction($id_d, 1, 0, "recu-iparapheur", "test");

        $documentModificationService = $this->getObjectInstancier()->getInstance(DocumentModificationService::class);

        $documentModificationService->modifyDocument(1, 1, $id_d, new Recuperateur([
            'numero_de_lacte' => 'autre_numero',
            'objet' => "autre_objet",
            'envoi_tdt' => 'On'
        ]), new FileUploader(), true);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEquals('201906121136', $donneesFormulaire->get('numero_de_lacte'));
        $this->assertTrue($donneesFormulaire->get('envoi_tdt'));

        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);

        $info = $documentSQL->getInfo($id_d);
        $this->assertEquals("L'objet de mon acte", $info['titre']);
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function testModifyOnConsole()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];


        $documentModificationService = $this->getObjectInstancier()->getInstance(DocumentModificationService::class);

        $documentModificationService->modifyDocument(1, 1, $id_d, new Recuperateur([
            'numero_de_lacte' => '201906121136',
            'objet' => "L'objet de mon acte",
            'envoi_tdt' => 'On',
            'page' => 0
        ]), new FileUploader());

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEquals('201906121136', $donneesFormulaire->get('numero_de_lacte'));
        $this->assertFalse($donneesFormulaire->get('envoi_tdt'));
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function testModifyDontAddModificationState()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];


        $documentModificationService = $this->getObjectInstancier()->getInstance(DocumentModificationService::class);

        $documentModificationService->modifyDocument(1, 1, $id_d, new Recuperateur([
            'numero_de_lacte' => '201906121136',
            'objet' => "L'objet de mon acte",
            'envoi_tdt' => 'On',
            'page' => 0
        ]), new FileUploader());

        $documentModificationService->modifyDocument(1, 1, $id_d, new Recuperateur([
            'numero_de_lacte' => '201906121136',
            'objet' => "Un changement",
            'envoi_tdt' => 'On',
            'page' => 0
        ]), new FileUploader());

        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);

        $this->assertCount(2, $documentActionEntite->getAction(1, $id_d));
    }
}
