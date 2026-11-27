<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Daemon;

use Pastell\Service\Daemon\JobConsultationService;
use PastellTestCase;

final class JobConsultationServiceTest extends PastellTestCase
{
    public function testGetJobsForDocument(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $jobConsultationService = $this->getObjectInstancier()->getInstance(JobConsultationService::class);
        self::assertCount(0, $jobConsultationService->getJobsForDocument(self::ID_E_COL, $id_d));

        $this->triggerActionOnDocument($id_d, 'action-auto');

        $job_list = $jobConsultationService->getJobsForDocument(self::ID_E_COL, $id_d);
        self::assertCount(1, $job_list);
        self::assertSame($id_d, $job_list[0]['id_d']);
    }
}
