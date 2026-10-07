<?php

use Pastell\Configuration\JobStatus;
use Pastell\Model\Daemon\JobAdvancedFilters;
use Pastell\Model\Daemon\JobSort;
use Pastell\Model\Daemon\JobSortColumn;
use Pastell\Model\Daemon\SortDirection;

class JobQueueSQLTest extends PastellTestCase
{
    public const ID_D = 'foo';
    private JobQueueSQL $jobQueueSQL;
    private WorkerSQL $workerSQL;

    /**
     * @var Job
     */
    private $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $this->workerSQL = $this->getObjectInstancier()->getInstance(WorkerSQL::class);
        $this->job = new Job();
    }

    /**
     * @throws Exception
     */
    private function getNewJob(): Job
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_cible = 'cible';
        $job->etat_source = 'source';
        return $job;
    }

    /**
     * @throws Exception
     */
    public function testAddJobNoJobConfigured(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Type de job non pris en charge');
        $this->jobQueueSQL->createJob($this->job);
    }

    /**
     * @throws Exception
     */
    public function testAddJobNoCible(): void
    {
        $job = $this->getNewJob();
        $this->jobQueueSQL->createJob($job);
        static::assertNotSame(0, $job->id_job);
    }

    /**
     * @throws Exception
     */
    public function testAddJob(): void
    {
        $job = $this->getNewJob();
        $job->id_verrou = 'VERROU';
        $id_job = $this->jobQueueSQL->createJob($job);
        $job_result = $this->jobQueueSQL->getJob($id_job);
        static::assertSame('VERROU', $job_result->id_verrou);
    }

    /**
     * @throws Exception
     */
    public function testDeleteJobAlsoClearsWorker(): void
    {
        $this->job->type = Job::TYPE_DOCUMENT;
        $this->job->etat_cible = 'cible';
        $this->job->etat_source = 'source';
        $id_job = $this->jobQueueSQL->createJob($this->job);

        $workerSQL = new WorkerSQL(self::getSQLQuery());
        $id_worker = $workerSQL->create(1234);
        $workerSQL->attachJob($id_worker, $id_job);

        $this->jobQueueSQL->deleteJob($id_job);
        $this->assertNull($workerSQL->getWorker($id_worker));
    }

    /**
     * @throws Exception
     */
    public function testDeleteDocument(): void
    {
        $job = $this->getNewJob();
        $job->id_e = PastellTestCase::ID_E_COL;
        $job->id_d = self::ID_D;
        $id_job = $this->jobQueueSQL->createJob($job);
        static::assertNotEmpty($id_job);
        static::assertEquals(
            $id_job,
            $this->jobQueueSQL->getJobIdForDocument(
                PastellTestCase::ID_E_COL,
                self::ID_D
            )
        );
        $this->jobQueueSQL->deleteDocument(
            PastellTestCase::ID_E_COL,
            self::ID_D
        );
        static::assertFalse(
            $this->jobQueueSQL->getJobIdForDocument(
                PastellTestCase::ID_E_COL,
                self::ID_D
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testGetJobsByDaemon(): void
    {
        static::assertCount(0, $this->jobQueueSQL->getJobsByDaemon(DaemonSQL::GLOBAL_DAEMON));
        $job = $this->getNewJob();
        $job->id_daemon = DaemonSQL::GLOBAL_DAEMON;
        $this->jobQueueSQL->createJob($job);
        static::assertCount(1, $this->jobQueueSQL->getJobsByDaemon(DaemonSQL::GLOBAL_DAEMON));
    }

    /**
     * @throws Exception
     */
    public function testUpdateDaemon(): void
    {
        $job = $this->getNewJob();
        $job->id_daemon = DaemonSQL::GLOBAL_DAEMON;
        $job_id = $this->jobQueueSQL->createJob($job);
        static::assertCount(1, $this->jobQueueSQL->getJobsByDaemon(DaemonSQL::GLOBAL_DAEMON));
        $this->jobQueueSQL->updateDaemon((int)$job_id, 2);
        static::assertCount(0, $this->jobQueueSQL->getJobsByDaemon(DaemonSQL::GLOBAL_DAEMON));
    }

    /**
     * @throws Exception
     */
    public function testGetAllJobs(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $job = $this->getNewJob();
            $this->jobQueueSQL->createJob($job);
        }
        $job_list = $this->jobQueueSQL->getAllJobs();
        static::assertCount(10, $job_list);
        $job_list = $this->jobQueueSQL->getAllJobs(1);
        static::assertCount(9, $job_list);
        $job_list = $this->jobQueueSQL->getAllJobs(0, 3);
        static::assertCount(3, $job_list);
        $job_list = $this->jobQueueSQL->getAllJobs(9, 3);
        static::assertCount(1, $job_list);
    }

    /**
     * @throws Exception
     */
    public function testGetFilteredJobListSortByDate(): void
    {
        $old_job = $this->getNewJob();
        $old_job->next_try = '2000-01-01 00:00:00';
        $id_old = $this->jobQueueSQL->createJob($old_job);

        $recent_job = $this->getNewJob();
        $recent_job->next_try = '2030-01-01 00:00:00';
        $id_recent = $this->jobQueueSQL->createJob($recent_job);

        $asc = $this->jobQueueSQL->getFilteredJobList(
            20,
            0,
            '',
            null,
            null,
            new JobSort(JobSortColumn::NEXT_TRY, SortDirection::ASC)
        );
        static::assertSame((int)$id_old, (int)$asc[0]->id_job);
        static::assertSame((int)$id_recent, (int)$asc[1]->id_job);

        $desc = $this->jobQueueSQL->getFilteredJobList(
            20,
            0,
            '',
            null,
            null,
            new JobSort(JobSortColumn::NEXT_TRY, SortDirection::DESC)
        );
        static::assertSame((int)$id_recent, (int)$desc[0]->id_job);
        static::assertSame((int)$id_old, (int)$desc[1]->id_job);
    }

    /**
     * @throws Exception
     */
    public function testBulkUpdateSearch(): void
    {
        $id_job_1 = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        $id_job_2 = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        $id_job_3 = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        $this->jobQueueSQL->lock($id_job_2, JobStatus::KILLED_BY_USER);
        $this->jobQueueSQL->lock($id_job_3, JobStatus::ERROR_ACTION);

        // Les travaux déjà suspendus gardent la raison de leur arrêt
        static::assertSame(1, $this->jobQueueSQL->lockAll(null, new JobAdvancedFilters()));
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $this->jobQueueSQL->getJob($id_job_1)->job_status);
        static::assertSame(JobStatus::KILLED_BY_USER, $this->jobQueueSQL->getJob($id_job_2)->job_status);
        static::assertSame(JobStatus::ERROR_ACTION, $this->jobQueueSQL->getJob($id_job_3)->job_status);

        static::assertSame(3, $this->jobQueueSQL->unlockAll(null, new JobAdvancedFilters()));
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job_1)->job_status);
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job_2)->job_status);
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job_3)->job_status);
    }

    /**
     * @throws Exception
     */
    public function testBulkRunningJobs(): void
    {
        $id_job = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        $id_worker = $this->workerSQL->create(1234);
        $this->workerSQL->attachJob($id_worker, $id_job);

        static::assertSame(0, $this->jobQueueSQL->lockAll(null, new JobAdvancedFilters()));
        static::assertSame(
            ['count' => 1, 'lockable' => 0, 'unlockable' => 0],
            $this->jobQueueSQL->getNbJobWithBulkUpdatable(new JobAdvancedFilters())
        );
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job)->job_status);

        $this->workerSQL->success($id_worker);
        static::assertSame(1, $this->jobQueueSQL->lockAll(null, new JobAdvancedFilters()));
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $this->jobQueueSQL->getJob($id_job)->job_status);
    }

    /**
     * Un travail en cours de lancement ne peut pas être suspendu, mais peut être repris
     * @throws Exception
     */
    public function testBulkLaunchingJobs(): void
    {
        $id_job = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        $this->jobQueueSQL->lock($id_job, JobStatus::LAUNCHING);

        static::assertSame(
            ['count' => 1, 'lockable' => 0, 'unlockable' => 1],
            $this->jobQueueSQL->getNbJobWithBulkUpdatable(new JobAdvancedFilters())
        );
        static::assertSame(0, $this->jobQueueSQL->lockAll(null, new JobAdvancedFilters()));
        static::assertSame(1, $this->jobQueueSQL->unlockAll(null, new JobAdvancedFilters()));
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job)->job_status);
    }

    /**
     * Un travail en cours de lancement avec un worker actif ne peut pas être repris
     * @throws Exception
     */
    public function testBulkUnlockLaunchingJobWithRunningWorker(): void
    {
        $id_job = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        $id_worker = $this->workerSQL->create(1234);
        $this->workerSQL->attachJob($id_worker, $id_job);
        $this->jobQueueSQL->lock($id_job, JobStatus::LAUNCHING);

        static::assertSame(
            ['count' => 1, 'lockable' => 0, 'unlockable' => 0],
            $this->jobQueueSQL->getNbJobWithBulkUpdatable(new JobAdvancedFilters())
        );
        static::assertSame(0, $this->jobQueueSQL->unlockAll(null, new JobAdvancedFilters()));
        static::assertSame(JobStatus::LAUNCHING, $this->jobQueueSQL->getJob($id_job)->job_status);
    }

    /**
     * Un travail ayant plusieurs workers n'est compté qu'une fois
     * @throws Exception
     */
    public function testBulkCountWithSeveralWorkers(): void
    {
        $id_job = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        for ($i = 0; $i < 3; $i++) {
            $id_worker = $this->workerSQL->create(1234 + $i);
            $this->workerSQL->attachJob($id_worker, $id_job);
            $this->workerSQL->success($id_worker);
        }
        $this->jobQueueSQL->lock($id_job, JobStatus::ERROR_ACTION);

        static::assertSame(
            ['count' => 1, 'lockable' => 0, 'unlockable' => 1],
            $this->jobQueueSQL->getNbJobWithBulkUpdatable(new JobAdvancedFilters())
        );
    }

    /**
     * Un travail dont le worker est mort sans être terminé peut être repris
     * @throws Exception
     */
    public function testBulkUnlockOrphanWorkerJobs(): void
    {
        $id_job = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        $id_worker = $this->workerSQL->create(1234);
        $this->workerSQL->attachJob($id_worker, $id_job);
        $this->jobQueueSQL->lock($id_job, JobStatus::ERROR_DAEMON);

        static::assertSame(
            ['count' => 1, 'lockable' => 0, 'unlockable' => 1],
            $this->jobQueueSQL->getNbJobWithBulkUpdatable(new JobAdvancedFilters())
        );
        static::assertSame(1, $this->jobQueueSQL->unlockAll(null, new JobAdvancedFilters()));
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job)->job_status);
    }

    /**
     * Le filtre « en retard » utilise la date figée à l'affichage
     * @throws Exception
     */
    public function testBulkLateSearchUsesReferenceDate(): void
    {
        $id_job = (int)$this->jobQueueSQL->createJob($this->getNewJob());

        $before = new JobAdvancedFilters(late: '1', late_before: '2000-01-01 00:00:00');
        static::assertSame(0, $this->jobQueueSQL->getNbJobWithBulkUpdatable($before)['count']);
        static::assertSame(0, $this->jobQueueSQL->lockAll(null, $before));

        $after = new JobAdvancedFilters(late: '1', late_before: '2999-01-01 00:00:00');
        static::assertSame(1, $this->jobQueueSQL->lockAll(null, $after));
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $this->jobQueueSQL->getJob($id_job)->job_status);
    }

    /**
     * @throws Exception
     */
    public function testBulkDaemonScope(): void
    {
        $id_job = (int)$this->jobQueueSQL->createJob($this->getNewJob());
        $this->jobQueueSQL->updateDaemon($id_job, 42);

        static::assertSame(0, $this->jobQueueSQL->lockAll(43, new JobAdvancedFilters()));
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job)->job_status);

        static::assertSame(1, $this->jobQueueSQL->lockAll(42, new JobAdvancedFilters()));
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $this->jobQueueSQL->getJob($id_job)->job_status);
    }

    /**
     * @throws Exception
     */
    public function testBulkSearch(): void
    {
        $job = $this->getNewJob();
        $job->id_verrou = 'VERROU_A';
        $id_job_a = (int)$this->jobQueueSQL->createJob($job);
        $job = $this->getNewJob();
        $job->id_verrou = 'VERROU_B';
        $id_job_b = (int)$this->jobQueueSQL->createJob($job);

        $filters = new JobAdvancedFilters(id_verrou: ['VERROU_A']);
        static::assertSame(
            ['count' => 1, 'lockable' => 1, 'unlockable' => 0],
            $this->jobQueueSQL->getNbJobWithBulkUpdatable($filters)
        );
        static::assertSame(0, $this->jobQueueSQL->unlockAll(null, $filters));
        static::assertSame(1, $this->jobQueueSQL->lockAll(null, $filters));
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $this->jobQueueSQL->getJob($id_job_a)->job_status);
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job_b)->job_status);

        $this->jobQueueSQL->lock($id_job_b, JobStatus::SUSPENDED_BY_USER);
        static::assertSame(
            ['count' => 1, 'lockable' => 0, 'unlockable' => 1],
            $this->jobQueueSQL->getNbJobWithBulkUpdatable($filters)
        );
        static::assertSame(1, $this->jobQueueSQL->unlockAll(null, $filters));
        static::assertSame(JobStatus::WAITING, $this->jobQueueSQL->getJob($id_job_a)->job_status);
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $this->jobQueueSQL->getJob($id_job_b)->job_status);
    }

    /**
     * @throws Exception
     */
    public function testGetJobsForConnector(): void
    {
        $job = $this->getNewJob();
        $job->id_ce = 1;
        $this->jobQueueSQL->createJob($job);
        static::assertCount(1, $this->jobQueueSQL->getJobsForConnector(1));
    }

    /**
     * @throws Exception
     */
    public function testGetJobsForDocument(): void
    {
        $job = $this->getNewJob();
        $job->id_d = '12345ABC';
        $this->jobQueueSQL->createJob($job);
        static::assertCount(1, $this->jobQueueSQL->getJobsForDocument('12345ABC'));
    }

    /**
     * @throws Exception
     */
    public function testGetWorkerFromJob(): void
    {
        $job_id = $this->jobQueueSQL->createJob($this->getNewJob());
        $worker_id = $this->workerSQL->create('0');
        $this->workerSQL->attachJob($worker_id, $job_id);

        $worker = $this->workerSQL->getWorker($worker_id);
        $job_result = $this->jobQueueSQL->getJob($job_id);
        static::assertEquals($worker, $job_result->worker);
    }
}
