<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Daemon;

use Job;
use JobQueueSQL;
use Pastell\Service\Daemon\JobCreationService;
use PastellTestCase;
use UnrecoverableException;

final class JobCreationServiceTest extends PastellTestCase
{
    private function getJobCreationService(): JobCreationService
    {
        return $this->getObjectInstancier()->getInstance(JobCreationService::class);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testCreate(): void
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->id_verrou = 'VERROU';

        $id_job = $this->getJobCreationService()->create($job);

        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $createdJob = $jobQueueSQL->getJob($id_job);
        self::assertSame(Job::TYPE_DOCUMENT, (int)$createdJob->type);
        self::assertSame('VERROU', $createdJob->id_verrou);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testCreateUnsupportedType(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Type de job non pris en charge');
        $this->getJobCreationService()->create(new Job());
    }
}
