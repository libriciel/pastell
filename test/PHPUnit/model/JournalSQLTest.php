<?php

use Pastell\Service\Journal\JournalEntryService;

class JournalSQLTest extends PastellTestCase
{
    private JournalSQL $journalSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->journalSQL = new JournalSQL(static::getSQLQuery());
    }

    private function seedConsultation(): int
    {
        return $this->getObjectInstancier()
            ->getInstance(JournalEntryService::class)
            ->addConsultation(1, 'XYZ', 1);
    }

    public function testGetAll(): void
    {
        $this->seedConsultation();
        $rows = $this->journalSQL->getAll(1, false, false, 1, 0, 10, 'consulté');
        static::assertSame('Eric Pommateau a consulté le dossier', $rows[0]['message']);
    }

    public function testCountAll(): void
    {
        $this->seedConsultation();
        static::assertEquals(1, $this->journalSQL->countAll(1, false, false, 1, 'consulté', false, false));
    }

    public function testGetInfo(): void
    {
        $id_j = $this->seedConsultation();
        static::assertSame('Eric Pommateau a consulté le dossier', $this->journalSQL->getInfo($id_j)->message);
    }

    public function testGetAllInfo(): void
    {
        $id_j = $this->seedConsultation();
        static::assertSame('Eric Pommateau a consulté le dossier', $this->journalSQL->getAllInfo($id_j)['message']);
    }

    public function testGetAllInfoNotExisting(): void
    {
        static::assertFalse($this->journalSQL->getAllInfo(42));
    }

    public function testGetCount(): void
    {
        $this->seedConsultation();
        static::assertGreaterThanOrEqual(1, $this->journalSQL->getCount());
    }

    public function testHistorisation(): void
    {
        $id_j = $this->journalSQL->insert(JournalEventType::TEST->value, 0, 1, 0, '', 'message', '2015-01-01 00:00:00', '', '', '', '');

        static::assertContains($id_j, array_map('intval', $this->journalSQL->getIdListOlderThan('2020-01-01 00:00:00')));
        static::assertFalse($this->journalSQL->existsInHistorique($id_j));

        $this->journalSQL->copyToHistorique($id_j);
        static::assertTrue($this->journalSQL->existsInHistorique($id_j));

        $this->journalSQL->delete($id_j);
        static::assertNull($this->journalSQL->getInfo($id_j));
    }

    public function testInsert(): void
    {
        $id_j = $this->journalSQL->insert(JournalEventType::TEST->value, 0, 1, 0, 'test', 'message', '2015-01-01 00:00:00', 'horodate', '', '', 'preuve');
        static::assertSame('message', $this->journalSQL->getInfo($id_j)->message);
        static::assertSame('preuve', $this->journalSQL->getInfo($id_j)->preuve);
    }

    public function testCountConsultation(): void
    {
        $this->seedConsultation();
        static::assertSame(1, $this->journalSQL->countConsultation(1, 'XYZ'));
        static::assertSame(0, $this->journalSQL->countConsultation(1, 'OTHER'));
    }

    public function testAttentePreuve(): void
    {
        $id_j = $this->journalSQL->insert(JournalEventType::TEST->value, 0, 1, 0, '', '', '2015-01-01 00:00:00', '', '', '', '');
        $this->journalSQL->insertAttentePreuve($id_j);
        static::assertContains($id_j, array_map('intval', $this->journalSQL->getAttentePreuveIdList()));

        $this->journalSQL->updateProof($id_j, '2015-01-02 00:00:00', 'proof');
        static::assertSame('proof', $this->journalSQL->getInfo($id_j)->preuve);

        $this->journalSQL->deleteAttentePreuve($id_j);
        static::assertNotContains($id_j, array_map('intval', $this->journalSQL->getAttentePreuveIdList()));
    }
}
