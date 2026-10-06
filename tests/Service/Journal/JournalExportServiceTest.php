<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Journal;

use Pastell\Service\Journal\JournalEntryService;
use Pastell\Service\Journal\JournalExportService;
use PastellTestCase;
use JournalEventType;

class JournalExportServiceTest extends PastellTestCase
{
    private JournalExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getObjectInstancier()->getInstance(JournalExportService::class);
    }

    private function seed(): void
    {
        $this->getObjectInstancier()->getInstance(JournalEntryService::class)
            ->add(JournalEventType::TEST, 1, '', 'action', 'message export');
    }

    public function testStreamRows(): void
    {
        $this->seed();
        $rows = iterator_to_array($this->service->streamRows(1, false, false, 1, '', false, false));
        static::assertNotEmpty($rows);
        static::assertSame('message export', $rows[0]['message']);
    }

    public function testStreamRowsSansPreuve(): void
    {
        $this->seed();
        $rows = iterator_to_array($this->service->streamRows(1, false, false, 1, '', false, false));
        static::assertArrayNotHasKey('preuve', $rows[0]);
    }
}
