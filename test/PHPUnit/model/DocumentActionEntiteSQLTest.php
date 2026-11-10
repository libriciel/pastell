<?php

declare(strict_types=1);

class DocumentActionEntiteSQLTest extends PastellTestCase
{
    private DocumentActionEntiteSQL $documentActionEntiteSQL;
    private string $id_d;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentActionEntiteSQL = $this->getObjectInstancier()->getInstance(DocumentActionEntiteSQL::class);
        $this->id_d = $this->createDocument('test')['id_d'];
    }

    private function addAction(string $action): int
    {
        $id_a = (int) $this->getObjectInstancier()->getInstance(DocumentActionSQL::class)
            ->add($this->id_d, self::ID_E_COL, 1, $action);
        $this->documentActionEntiteSQL->add($id_a, self::ID_E_COL, 0);
        return $id_a;
    }

    public function testGetLastAction(): void
    {
        $this->addAction('action-test');
        static::assertSame('action-test', $this->documentActionEntiteSQL->getLastAction(self::ID_E_COL, $this->id_d));
        static::assertNull($this->documentActionEntiteSQL->getLastAction(self::ID_E_SERVICE, $this->id_d));
    }

    public function testGetLastActionNotModif(): void
    {
        $this->addAction('action-test');
        $this->addAction(Action::MODIFICATION);
        static::assertSame(
            'action-test',
            $this->documentActionEntiteSQL->getLastActionNotModif(self::ID_E_COL, $this->id_d)
        );
    }

    public function testLastActionWithFutureDate(): void
    {
        $id_a = $this->addAction('action-test');
        $this->addAction(Action::MODIFICATION);
        $this->getSQLQuery()->query(
            'UPDATE document_action SET date = ? WHERE id_a = ?',
            date('Y-m-d H:i:s', strtotime('+1 hours')),
            $id_a
        );

        static::assertSame(Action::MODIFICATION, $this->documentActionEntiteSQL->getLastAction(self::ID_E_COL, $this->id_d));
        static::assertSame('action-test', $this->documentActionEntiteSQL->getLastActionByDate(self::ID_E_COL, $this->id_d));
        static::assertSame(
            Action::MODIFICATION,
            $this->documentActionEntiteSQL->getLastActionInfo(self::ID_E_COL, $this->id_d)['action']
        );
        $actions = $this->documentActionEntiteSQL->getActions(self::ID_E_COL, $this->id_d);
        static::assertSame('action-test', $actions[1]['action']);
        static::assertSame(Action::MODIFICATION, $actions[2]['action']);
    }

    public function testLastActionInfoNotFound(): void
    {
        static::assertNull($this->documentActionEntiteSQL->getLastActionInfo(self::ID_E_SERVICE, $this->id_d));
    }

    public function testGetUserFromAction(): void
    {
        $this->addAction('action-test');
        static::assertSame(
            'admin',
            $this->documentActionEntiteSQL->getUserFromAction(self::ID_E_COL, $this->id_d, 'action-test')['login']
        );
        static::assertNull($this->documentActionEntiteSQL->getUserFromAction(self::ID_E_COL, $this->id_d, 'other'));
    }

    public function testGetOtherEntites(): void
    {
        $id_a = (int) $this->getObjectInstancier()->getInstance(DocumentActionSQL::class)
            ->add($this->id_d, self::ID_E_SERVICE, 1, 'action-test');
        $this->documentActionEntiteSQL->add($id_a, self::ID_E_COL, 0);

        $entites = $this->documentActionEntiteSQL->getOtherEntites(self::ID_E_COL, $this->id_d);
        static::assertCount(1, $entites);
        static::assertSame(self::ID_E_SERVICE, $entites[0]['id_e']);
    }

    public function testRemove(): void
    {
        $id_a = $this->addAction('action-test');
        $this->documentActionEntiteSQL->remove($id_a);
        static::assertSame('creation', $this->documentActionEntiteSQL->getLastAction(self::ID_E_COL, $this->id_d));
    }
}
