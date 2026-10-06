<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Journal;

use Pastell\Service\Journal\JournalEntryService;
use Pastell\Service\Journal\JournalConsultationService;
use PastellTestCase;
use JournalEventType;

class JournalConsultationServiceTest extends PastellTestCase
{
    private JournalConsultationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getObjectInstancier()->getInstance(JournalConsultationService::class);
    }

    private function seedConsultation(): int
    {
        return (int) $this->getObjectInstancier()->getInstance(JournalEntryService::class)->addConsultation(1, 'XYZ', 1);
    }

    public function testGetListEnrichit(): void
    {
        $this->seedConsultation();
        $rows = $this->service->getList(1, false, false, 1, 0, 10, 'consulté');
        static::assertSame('Eric Pommateau a consulté le dossier', $rows[0]['message']);
        static::assertArrayHasKey('document_type_libelle', $rows[0]);
        static::assertArrayHasKey('action_libelle', $rows[0]);
    }

    public function testCountAll(): void
    {
        $this->seedConsultation();
        static::assertEquals(1, $this->service->countAll(1, false, false, 1, 'consulté', false, false));
    }

    public function testGetInfo(): void
    {
        $id_j = $this->seedConsultation();
        static::assertSame('Eric Pommateau a consulté le dossier', $this->service->getInfo($id_j)->message);
    }

    public function testGetAllInfo(): void
    {
        $id_j = $this->seedConsultation();
        $info = $this->service->getAllInfo($id_j);
        static::assertSame('Eric Pommateau a consulté le dossier', $info['message']);
        static::assertArrayHasKey('document_type_libelle', $info);
    }

    public function testGetAllInfoNotExisting(): void
    {
        static::assertFalse($this->service->getAllInfo(42));
    }

    public function testGetTypeAsString(): void
    {
        static::assertSame('Connexion', $this->service->getTypeAsString(JournalEventType::CONNEXION->value));
    }
}
