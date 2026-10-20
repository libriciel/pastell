<?php

class DocumentActionEntiteTest extends PastellTestCase
{
    /** @var DocumentActionEntite */
    private $documentActionEntite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);
    }

    private function addAction($id_d, $action_name): string
    {
        $action = new DocumentActionSQL($this->getSQLQuery());
        $id_a = $action->add($id_d, 1, 1, $action_name);
        $this->documentActionEntite->add($id_a, 1, false);
        return $id_a;
    }

    /**
     * @throws Exception
     */
    public function testGetLastAction()
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->addAction($id_d, "action-test");

        $this->assertEquals(
            "action-test",
            $this->documentActionEntite->getLastAction(1, $id_d)
        );
    }

    public function testGetLastActionNotModif()
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->addAction($id_d, "action-test");
        $this->addAction($id_d, "modification");

        $this->assertEquals(
            "action-test",
            $this->documentActionEntite->getLastActionNotModif(1, $id_d)
        );
        $this->assertEquals(
            "modification",
            $this->documentActionEntite->getLastAction(1, $id_d)
        );
    }

    public function testGetDocumentOlderThanDay()
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->addAction($id_d, "action-test");
        $this->addAction($id_d, "modification");
        $documents_list = $this->documentActionEntite->getDocumentOlderThanDay(
            1,
            "test",
            "action-test",
            0
        );
        $this->assertEquals([], $documents_list);
    }

    public function testGetDocumentInStateOlderThanDay()
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->addAction($id_d, "action-test");
        $this->addAction($id_d, "modification");
        $documents_list = $this->documentActionEntite->getDocumentInStateOlderThanDay(
            1,
            "test",
            "action-test",
            0
        );
        $this->assertEquals("modification", $documents_list[0]['last_action']);
    }

    /**
     * @throws Exception
     */
    public function testGetLastActionWithDateError(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $id_a = $this->addAction($id_d, "action-test");
        $this->addAction($id_d, Action::MODIFICATION);

        $date = date('Y-m-d H:i:s', strtotime("+1 hours"));
        $this->getSQLQuery()->query('UPDATE document_action SET date=? WHERE id_a=?', $date, $id_a);

        $this->assertSame(
            Action::MODIFICATION,
            $this->documentActionEntite->getLastAction(self::ID_E_COL, $id_d)
        );

        $this->assertSame(
            Action::MODIFICATION,
            $this->documentActionEntite->getLastActionInfo(self::ID_E_COL, $id_d)['action']
        );

        $actions = $this->documentActionEntite->getAction(self::ID_E_COL, $id_d);
        $this->assertSame('action-test', $actions[1]['action']);
        $this->assertSame(Action::MODIFICATION, $actions[2]['action']);
    }

    public function testGetInfoOfDocumentNotOnEntity(): void
    {
        $id_d = $this->createDocument('test', self::ID_E_SERVICE)['id_d'];

        static::assertSame([], $this->documentActionEntite->getInfo($id_d, self::ID_E_COL));
    }
}
