<?php

/**
 * @deprecated 4.1.25 Use DocumentEntiteSQLTest instead (tests DocumentEntiteSQL).
 */
class DocumentEntiteTest extends PastellTestCase
{
    private $id_d;

    /**
     * @var DocumentEntite
     */
    private $documentEntite;

    /**
     * @throws UnrecoverableException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $document = new DocumentSQL($this->getSQLQuery(), new PasswordGenerator());
        $this->id_d = $document->getNewId();
        $document->save($this->id_d, "document-type-test");
        $this->documentEntite = new DocumentEntite($this->getSQLQuery());
        $this->documentEntite->addRole($this->id_d, 1, "editeur");
        $action = new DocumentActionSQL($this->getSQLQuery());
        $action->add($this->id_d, 1, 1, "action-test");
    }

    public function testGetDocument()
    {
        $info = $this->documentEntite->getDocument(1, "document-type-test");
        $this->assertEquals($this->id_d, $info[0]['id_d']);
    }

    public function testHasRole()
    {
        $this->documentEntite->addRole($this->id_d, 1, "editeur");
        $this->assertTrue(boolval($this->documentEntite->hasRole($this->id_d, 1, "editeur")));
    }

    public function testGetEntite()
    {
        $info = $this->documentEntite->getEntite($this->id_d);
        $this->assertEquals(1, $info[0]['id_e']);
    }

    public function testGetRole()
    {
        $this->assertEquals("editeur", $this->documentEntite->getRole(1, $this->id_d));
    }

    public function testGetEntiteWithRole()
    {
        $this->assertEquals(1, $this->documentEntite->getEntiteWithRole($this->id_d, "editeur"));
    }

    public function testGetFromAction()
    {
        $info = $this->documentEntite->getFromAction("document-type-test", "action-test");
        $this->assertEquals($this->id_d, $info[0]['id_d']);
    }

    public function testGetAll()
    {
        $info = $this->documentEntite->getAll(1);
        $this->assertEquals($this->id_d, $info[0]['id_d']);
    }

    public function testGetNbAll()
    {
        $this->assertEquals(1, $this->documentEntite->getNbAll(1));
    }

    public function testGetAllByFluxAction()
    {
        $info = $this->documentEntite->getAllByFluxAction("document-type-test", "action-test");
        $this->assertEquals($this->id_d, $info[0]['id_d']);
    }

    public function testFixAction()
    {
        $this->documentEntite->fixAction("document-type-test", "action-test", "action-to");
        $info = $this->documentEntite->getAllByFluxAction("document-type-test", "action-to");
        $this->assertEquals($this->id_d, $info[0]['id_d']);
    }

    public function testGetAllLocal()
    {
        $info = $this->documentEntite->getAllLocal();
        $this->assertEquals($this->id_d, $info[0]['id_d']);
    }
}
