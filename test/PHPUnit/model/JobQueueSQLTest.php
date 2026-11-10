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
    private function getNewJob(?string $next_try = null): Job
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_cible = 'cible';
        $job->etat_source = 'source';
        $job->next_try = $next_try ?? $job->next_try;
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
    public function testDeleteJobClearsWorker(): void
    {
        $this->job->type = Job::TYPE_DOCUMENT;
        $this->job->etat_cible = 'cible';
        $this->job->etat_source = 'source';
        $id_job = (int)$this->jobQueueSQL->createJob($this->job);
        $id_worker_finished = $this->workerSQL->create(1234, $id_job);
        $this->workerSQL->error($id_worker_finished, 'error');
        $id_worker_running = $this->workerSQL->create(1235, $id_job);

        $this->jobQueueSQL->deleteJob($id_job);

        self::assertNull($this->jobQueueSQL->getJob($id_job));
        self::assertNull($this->workerSQL->getWorker($id_worker_finished));
        self::assertNotNull($this->workerSQL->getWorker($id_worker_running));
    }

    /**
     * @throws Exception
     */
    public function testDeleteDocumentClearsWorker(): void
    {
        $job = $this->getNewJob();
        $job->id_e = PastellTestCase::ID_E_COL;
        $job->id_d = self::ID_D;
        $id_job = $this->jobQueueSQL->createJob($job);
        $id_worker_finished = $this->workerSQL->create(1234, (int)$id_job);
        $this->workerSQL->error($id_worker_finished, 'error');
        $id_worker_running = $this->workerSQL->create(1235, (int)$id_job);

        $this->jobQueueSQL->deleteDocument(PastellTestCase::ID_E_COL, self::ID_D);

        self::assertNull($this->jobQueueSQL->getJob($id_job));
        self::assertNull($this->workerSQL->getWorker($id_worker_finished));
        self::assertNotNull($this->workerSQL->getWorker($id_worker_running));
    }

    /**
     * @throws Exception
     */
    public function testDeleteConnecteurClearsWorker(): void
    {
        $job = $this->getNewJob();
        $job->type = Job::TYPE_CONNECTEUR;
        $job->id_ce = 42;
        $id_job = $this->jobQueueSQL->createJob($job);
        $id_worker_finished = $this->workerSQL->create(1234, (int)$id_job);
        $this->workerSQL->error($id_worker_finished, 'error');
        $id_worker_running = $this->workerSQL->create(1235, (int)$id_job);

        $this->jobQueueSQL->deleteConnecteur(42);

        self::assertNull($this->jobQueueSQL->getJob($id_job));
        self::assertNull($this->workerSQL->getWorker($id_worker_finished));
        self::assertNotNull($this->workerSQL->getWorker($id_worker_running));
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
    public function testFilteredJobListPagination(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $job = $this->getNewJob();
            $job->next_try = sprintf('2030-01-%02d 00:00:00', 10 - $i);
            $this->jobQueueSQL->createJob($job);
        }
        $job_list = $this->jobQueueSQL->getFilteredJobList();
        self::assertCount(10, $job_list);
        self::assertSame('2030-01-01 00:00:00', $job_list[0]->next_try);
        self::assertCount(9, $this->jobQueueSQL->getFilteredJobList(offset: 1));
        self::assertCount(3, $this->jobQueueSQL->getFilteredJobList(3));
        self::assertCount(1, $this->jobQueueSQL->getFilteredJobList(3, 9));
    }

    /**
     * @throws Exception
     */
    public function testFilteredListNoDuplicate(): void
    {
        $id_job = $this->jobQueueSQL->createJob($this->getNewJob());
        $id_worker = $this->workerSQL->create(1234, (int)$id_job);
        $this->workerSQL->error($id_worker, 'error');
        $this->workerSQL->create(1235, (int)$id_job);

        self::assertCount(1, $this->jobQueueSQL->getFilteredJobList());
        self::assertSame(1, $this->jobQueueSQL->getNbJob(''));
        self::assertCount(1, $this->jobQueueSQL->getFilteredJobList(filtre: 'actif'));
    }

    /**
     * @throws Exception
     */
    public function testFilteredListByDaemon(): void
    {
        $job = $this->getNewJob();
        $job->id_daemon = DaemonSQL::GLOBAL_DAEMON;
        $id_job = $this->jobQueueSQL->createJob($job);
        $job = $this->getNewJob();
        $job->id_daemon = 2;
        $this->jobQueueSQL->createJob($job);

        $job_list = $this->jobQueueSQL->getFilteredJobList(id_daemon: DaemonSQL::GLOBAL_DAEMON);
        self::assertCount(1, $job_list);
        self::assertSame((int)$id_job, (int)$job_list[0]->id_job);
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

        $before = new JobAdvancedFilters(late: '1', search_date: '2000-01-01 00:00:00');
        static::assertSame(0, $this->jobQueueSQL->getNbJobWithBulkUpdatable($before)['count']);
        static::assertSame(0, $this->jobQueueSQL->lockAll(null, $before));

        $after = new JobAdvancedFilters(late: '1', search_date: '2999-01-01 00:00:00');
        static::assertSame(1, $this->jobQueueSQL->lockAll(null, $after));
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $this->jobQueueSQL->getJob($id_job)->job_status);
    }

    /**
     * @throws Exception
     */
    public function testSinceOneHourFrozen(): void
    {
        $this->jobQueueSQL->createJob($this->getNewJob(date(Date::DATE_ISO, strtotime('-2 hours'))));

        $before = new JobAdvancedFilters(since_one_hour: '1', search_date: '2000-01-01 00:00:00');
        self::assertCount(0, $this->jobQueueSQL->getFilteredJobList(advancedFilters: $before));

        $after = new JobAdvancedFilters(since_one_hour: '1', search_date: '2999-01-01 00:00:00');
        self::assertCount(1, $this->jobQueueSQL->getFilteredJobList(advancedFilters: $after));
    }

    /**
     * @throws Exception
     */
    public function testSearchSinceOneHour(): void
    {
        $two_hours_ago = date(Date::DATE_ISO, strtotime('-2 hours'));
        $now = date(Date::DATE_ISO);

        $this->jobQueueSQL->createJob($this->getNewJob($now));
        $id_job_waiting_old = (int)$this->jobQueueSQL->createJob($this->getNewJob($two_hours_ago));
        $id_job_locked_recent = (int)$this->jobQueueSQL->createJob($this->getNewJob($two_hours_ago));
        $this->jobQueueSQL->lock($id_job_locked_recent, JobStatus::SUSPENDED_BY_USER);
        $id_job_locked_old = (int)$this->jobQueueSQL->createJob($this->getNewJob($now));
        $this->jobQueueSQL->lock($id_job_locked_old, JobStatus::SUSPENDED_BY_USER);
        self::getSQLQuery()->query(
            'UPDATE job_queue SET lock_since = ? WHERE id_job = ?',
            $two_hours_ago,
            $id_job_locked_old
        );

        $job_list = $this->jobQueueSQL->getFilteredJobList(
            advancedFilters: new JobAdvancedFilters(since_one_hour: '1')
        );
        $id_job_list = array_map(static fn (Job $job): int => (int)$job->id_job, $job_list);
        sort($id_job_list);
        self::assertSame([$id_job_waiting_old, $id_job_locked_old], $id_job_list);
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
        $worker_id = $this->workerSQL->create(0);
        $this->workerSQL->attachJob($worker_id, $job_id);

        $worker = $this->workerSQL->getWorker($worker_id);
        $job_result = $this->jobQueueSQL->getJob($job_id);
        static::assertEquals($worker, $job_result->worker);
    }
}
