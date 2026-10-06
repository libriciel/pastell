<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Journal;

use DocumentSQL;
use DocumentTypeFactory;
use JournalEventType;
use JournalSQL;
use MockHorodateur;
use OpensslTSWrapper;
use Pastell\Service\Journal\JournalEntryService;
use Pastell\Storage\StorageInterface;
use PastellTestCase;
use UtilisateurSQL;

class JournalEntryServiceTest extends PastellTestCase
{
    private JournalEntryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->makeService();
        $this->service->setHorodateur(new MockHorodateur($this->getObjectInstancier()->getInstance(OpensslTSWrapper::class)));
    }

    private function makeService(): JournalEntryService
    {
        $service = new JournalEntryService(
            new JournalSQL(static::getSQLQuery()),
            $this->getObjectInstancier()->getInstance(UtilisateurSQL::class),
            $this->getObjectInstancier()->getInstance(DocumentSQL::class),
            $this->getLogger(),
            false,
            $this->getObjectInstancier()->getInstance('use_external_storage_for_journal_proof'),
            $this->getObjectInstancier()->getInstance(StorageInterface::class),
        );
        $service->setId(1);
        return $service;
    }

    private function readProof(int $id_j): string
    {
        $raw = (new JournalSQL(static::getSQLQuery()))->getInfo($id_j);
        if ($raw->preuve === '' && $this->getObjectInstancier()->getInstance('use_external_storage_for_journal_proof')) {
            return $this->getObjectInstancier()->getInstance(StorageInterface::class)->read($id_j . 'preuve.tsa');
        }
        return $raw->preuve;
    }

    public function testAddConsultation(): void
    {
        $id_j = $this->service->addConsultation(1, 'XYZT', 1);
        $raw = (new JournalSQL(static::getSQLQuery()))->getInfo((int) $id_j);
        static::assertSame('Eric Pommateau a consulté le dossier', $raw->message);
    }

    public function testAddSameConsultation(): void
    {
        $this->service->addConsultation(1, 'XYZT', 1);
        static::assertFalse($this->service->addConsultation(1, 'XYZT', 1));
    }

    public function testAddActionAutomatique(): void
    {
        $id_j = $this->service->addActionAutomatique(JournalEventType::DOCUMENT_ACTION, 1, 'XYZT', 'test', 'message');
        $raw = (new JournalSQL(static::getSQLQuery()))->getInfo((int) $id_j);
        static::assertEquals(0, $raw->id_u);
    }

    public function testAddSql(): void
    {
        $id_j = $this->service->addSQL(JournalEventType::TEST, false, false, false, false, false);
        static::assertSame('MOCK TIMESTAMP', $this->readProof((int) $id_j));
    }

    public function testAddSqlObjectStorage(): void
    {
        $this->getObjectInstancier()->setInstance('use_external_storage_for_journal_proof', true);
        $this->setUp();
        $this->testAddSql();
    }

    public function testAddSqlNoHorodateur(): void
    {
        $service = $this->makeService();
        $id_j = $service->addSQL(JournalEventType::TEST, false, false, false, false, false);
        static::assertSame('', $this->readProof((int) $id_j));
    }

    public function testHorodateAllNoHorodateur(): void
    {
        $service = $this->makeService();
        $this->expectExceptionMessage('Aucun horodateur configuré');
        $service->horodateAll();
    }

    public function testHorodateAll(): void
    {
        $id_j = (int) $this->makeService()->addConsultation(1, 'XYZ', 1);
        static::assertSame('', $this->readProof($id_j));

        $this->expectOutputString("$id_j horodaté : 1977-02-18 08:40:00\n");
        $this->service->horodateAll();

        static::assertSame('MOCK TIMESTAMP', $this->readProof($id_j));
    }

    public function testHorodateAllObjectStorage(): void
    {
        $this->getObjectInstancier()->setInstance('use_external_storage_for_journal_proof', true);
        $this->setUp();
        $this->testHorodateAll();
    }
}
