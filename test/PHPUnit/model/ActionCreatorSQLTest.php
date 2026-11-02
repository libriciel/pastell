<?php

class ActionCreatorSQLTest extends PastellTestCase
{
    /**
     * @throws Exception
     */
    public function testAddAction()
    {
        $actionCreatorSQL = $this->getObjectInstancier()->getInstance(ActionCreatorSQL::class);
        $id_d = $this->createDocument('test')['id_d'];

        $actionCreatorSQL->addAction(1, 0, "action-test", "test", $id_d);

        $documentActionEntiteSQL = $this->getObjectInstancier()->getInstance(DocumentActionEntiteSQL::class);

        $this->assertEquals(
            "action-test",
            $documentActionEntiteSQL->getLastAction(1, $id_d)
        );
    }
}
