<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Daemon;

use Job;
use JobQueueSQL;
use Pastell\Service\Daemon\JobLaunchService;
use PastellTestCase;
use WorkerSQL;

final class JobLaunchServiceTest extends PastellTestCase
{
    private function getJobLaunchService(): JobLaunchService
    {
        return $this->getObjectInstancier()->getInstance(JobLaunchService::class);
    }

    private function getWorkerSQL(): WorkerSQL
    {
        return $this->getObjectInstancier()->getInstance(WorkerSQL::class);
    }

    private function createJob(string $id_verrou = '', string $next_try = 'yesterday'): int
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->id_e = 1;
        $job->id_d = uniqid('', true);
        $job->id_verrou = $id_verrou;
        $job->next_try = date('Y-m-d H:i:s', strtotime($next_try));
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        return (int)$jobQueueSQL->createJob($job);
    }

    private function attachWorker(int $id_job): void
    {
        $id_worker = $this->getWorkerSQL()->create(42);
        $this->getWorkerSQL()->attachJob($id_worker, $id_job);
    }

    public function testNoJobWithoutLimit(): void
    {
        $this->createJob();
        self::assertSame([], $this->getJobLaunchService()->getJobsToLaunch(0));
    }

    public function testGetJobsToLaunch(): void
    {
        $id_job = $this->createJob();
        self::assertSame([$id_job], $this->getJobLaunchService()->getJobsToLaunch(5));

        $this->attachWorker($id_job);
        self::assertSame([], $this->getJobLaunchService()->getJobsToLaunch(5));
    }

    public function testSortedByNextTry(): void
    {
        $id_job_recent = $this->createJob('', '-1 hour');
        $id_job_locked = $this->createJob('VERROU', '-2 days');
        $id_job_old = $this->createJob('', '-1 day');

        self::assertSame(
            [$id_job_locked, $id_job_old, $id_job_recent],
            $this->getJobLaunchService()->getJobsToLaunch(5)
        );
    }

    public function testLimitIsRespected(): void
    {
        $id_job_old = $this->createJob('', '-2 days');
        $this->createJob('', '-1 hour');
        $id_job_locked = $this->createJob('VERROU', '-1 day');

        self::assertSame([$id_job_old, $id_job_locked], $this->getJobLaunchService()->getJobsToLaunch(2));
    }

    public function testOneJobPerLock(): void
    {
        $id_job = $this->createJob('VERROU', '-2 days');
        $this->createJob('VERROU');

        self::assertSame([$id_job], $this->getJobLaunchService()->getJobsToLaunch(5));
    }

    public function testRunningJobBlocksLock(): void
    {
        $id_job = $this->createJob('VERROU');
        $this->attachWorker($id_job);
        $this->createJob('VERROU');

        self::assertSame([], $this->getJobLaunchService()->getJobsToLaunch(5));
        self::assertSame([], $this->getJobLaunchService()->getJobsToLaunchByLock('VERROU'));
    }

    public function testSeveralJobsPerLock(): void
    {
        $this->getObjectInstancier()->setInstance('nb_job_par_verrou', 2);
        $id_job_running = $this->createJob('VERROU', '-3 days');
        $this->attachWorker($id_job_running);
        $id_job = $this->createJob('VERROU', '-2 days');
        $this->createJob('VERROU');

        $job_list = $this->getJobLaunchService()->getJobsToLaunchByLock('VERROU');
        self::assertSame([$id_job], array_column($job_list, 'id_job'));
    }
}
