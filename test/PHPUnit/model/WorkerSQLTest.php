<?php

declare(strict_types=1);

class WorkerSQLTest extends PastellTestCase
{
    private WorkerSQL $workerSQL;
    private Daemon $globalDaemon;
    private JobQueueSQL $jobQueueSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workerSQL = new WorkerSQL(static::getSQLQuery());
        $daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $daemonSQL->setNbWorkers((int)NB_WORKERS);
        $daemonSQL->insertGlobalDaemon();
        $this->globalDaemon = $daemonSQL->getGlobalDaemon();
        $this->jobQueueSQL = new JobQueueSQL(
            static::getSQLQuery(),
            $this->workerSQL,
            $daemonSQL
        );
    }

    public function testCreate(): void
    {
        static::assertNotNull($this->workerSQL->create(42));
    }

    public function testGetInfo(): void
    {
        $id_worker = $this->workerSQL->create(42);
        $worker = $this->workerSQL->getWorker($id_worker);
        static::assertEquals(42, $worker->pid);
    }

    public function testError(): void
    {
        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->error($id_worker, "Message d'erreur");
        $worker = $this->workerSQL->getWorker($id_worker);
        static::assertEquals(1, $worker->termine);
    }

    public function testRunningWorkerInfo(): void
    {
        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->attachJob($id_worker, 12);
        $worker = $this->workerSQL->getRunningWorker(12);
        static::assertEquals(12, $worker->id_job);
    }

    public function testSuccess(): void
    {
        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->success($id_worker);
        static::assertNull($this->workerSQL->getWorker($id_worker));
    }

    public function testGetAllRunningWorker(): void
    {
        $id_worker = $this->workerSQL->create(42);
        $workers = $this->workerSQL->getAllRunningWorker();
        static::assertEquals($id_worker, $workers[0]->id_worker);
    }

    /**
     * @throws Exception
     */
    public function testGetAllRunningWorkerForDaemon(): void
    {
        $id_worker_1 = $this->workerSQL->create(42);
        $id_job_1 = $this->addJobWithDaemon($this->globalDaemon->id_daemon);
        $this->workerSQL->attachJob($id_worker_1, $id_job_1);

        $id_worker_2 = $this->workerSQL->create(43);
        $id_job_2 = $this->addJobWithDaemon(2);
        $this->workerSQL->attachJob($id_worker_2, $id_job_2);
        $workers = $this->workerSQL->getRunningWorkersForDaemon($this->globalDaemon->id_daemon);
        static::assertCount(1, $workers);
    }

    public function testGetJobToLauchLimit(): void
    {
        static::assertEmpty($this->workerSQL->getJobsToLaunch(0, 0));
    }

    /**
     * @throws Exception
     */
    private function createJob(): string
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->next_try = date('Y-M-d', strtotime('yesterday'));
        return $this->jobQueueSQL->createJob($job);
    }

    /**
     * @throws Exception
     */
    private function launchWorker(): bool|string
    {
        $id_job = $this->createJob();
        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->attachJob($id_worker, $id_job);
        return $id_worker;
    }

    public function testGetJobToLauch(): void
    {
        $id_job = $this->createJob();
        $id_worker = $this->workerSQL->create(42);

        $id_job_list = $this->workerSQL->getJobsToLaunch(5, $this->globalDaemon->id_daemon);
        static::assertEquals([$id_job], $id_job_list);

        $this->workerSQL->attachJob($id_worker, $id_job);
        static::assertEmpty($this->workerSQL->getJobsToLaunch(5, $this->globalDaemon->id_daemon));
    }

    public function testGetNbActif(): void
    {
        $this->launchWorker();
        static::assertEquals(1, $this->workerSQL->getNbActif());
    }

    public function testGetActif(): void
    {
        $id_worker = $this->launchWorker();
        $info = $this->workerSQL->getActif();
        static::assertEquals($id_worker, $info[0]['id_worker']);
    }

    public function testGetJobListWithWorker(): void
    {
        $id_worker = $this->launchWorker();
        $info = $this->workerSQL->getJobListWithWorker(0, 20, 'toto');
        static::assertEquals($id_worker, $info[0]['id_worker']);
        static::assertEquals(1, $this->workerSQL->getNbJob('toto'));
    }

    public function testGetJobLock(): void
    {
        $this->launchWorker();
        $info = $this->workerSQL->getJobListWithWorker(0, 20, 'lock');
        static::assertEmpty($info);
        static::assertEquals(0, $this->workerSQL->getNbJob('lock'));
    }

    public function testGetJobWait(): void
    {
        $id_worker = $this->launchWorker();
        $info = $this->workerSQL->getJobListWithWorker(0, 20, 'wait');
        static::assertEquals($id_worker, $info[0]['id_worker']);
        static::assertEquals(1, $this->workerSQL->getNbJob('wait'));
    }

    public function testGetJobActif(): void
    {
        $id_worker = $this->launchWorker();
        $info = $this->workerSQL->getJobListWithWorker(0, 20, 'actif');
        static::assertEquals($id_worker, $info[0]['id_worker']);
        static::assertEquals(1, $this->workerSQL->getNbJob('actif'));
    }

    public function testGetJobListWithWorkerForConnecteur(): void
    {
        static::assertEmpty($this->workerSQL->getJobListWithWorkerForConnecteur(11));
    }

    public function testGetJobListWithWorkerForDocument(): void
    {
        static::assertEmpty($this->workerSQL->getJobListWithWorkerForDocument(42, 8));
    }

    public function testGetActionEnCours(): void
    {
        static::assertEmpty($this->workerSQL->getActionEnCours(42, 8));
    }

    /**
     * @throws Exception
     */
    public function testNoLaunchWithIdVerrou(): void
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->id_d = 'XYZT';
        $job->id_e = 1;
        $job->id_verrou = 'VERROU';
        $job->next_try = date('Y-M-d', strtotime('yesterday'));
        $id_job_1 = $this->jobQueueSQL->createJob($job);

        $id_job_list = $this->workerSQL->getJobsToLaunch(5, $this->globalDaemon->id_daemon);
        $this->assertEquals([$id_job_1], $id_job_list);

        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->attachJob($id_worker, $id_job_1);

        $all_verrou = $this->workerSQL->getVerrou();
        static::assertEquals(['VERROU'], $all_verrou);

        $job->id_d = 'ABCD';
        $this->jobQueueSQL->createJob($job);
        $id_job_list = $this->workerSQL->getJobsToLaunch(5, $this->globalDaemon->id_daemon);
        static::assertEmpty($id_job_list);
    }

    /**
     * @throws Exception
     */
    public function testNoLaunchSimultaneousWithIdVerrou(): void
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->id_d = 'XYZT';
        $job->id_e = 1;
        $job->id_verrou = 'VERROU';
        $job->next_try = date('Y-M-d', strtotime('yesterday'));
        $id_job_1 = $this->jobQueueSQL->createJob($job);

        $job->id_d = 'ABCD';
        $this->jobQueueSQL->createJob($job);

        $id_job_list = $this->workerSQL->getJobsToLaunch(5, $this->globalDaemon->id_daemon);
        $this->assertEquals([$id_job_1], $id_job_list);
    }

    /**
     * @throws Exception
     */
    private function addJobWithVerrou(): void
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->id_d = 'XYZT';
        $job->id_e = 1;
        $job->id_verrou = 'VERROU';
        $job->next_try = date('Y-M-d', strtotime('yesterday'));
        $this->jobQueueSQL->createJob($job);
    }

    /**
     * @throws Exception
     */
    public function testGetAllVerrou(): void
    {
        $this->addJobWithVerrou();
        $all_verrou = $this->workerSQL->getAllVerrou();
        static::assertEquals(['VERROU'], $all_verrou);
    }

    /**
     * @throws Exception
     */
    public function testGetFirstJobToLaunch(): void
    {
        $this->addJobWithVerrou();
        $job = $this->workerSQL->getJobsToLaunchByLock('VERROU', $this->globalDaemon->id_daemon);
        static::assertEquals(1, $job[0]['id_job']);
    }

    /**
     * @throws Exception
     */
    public function testGetJobToLaunch(): void
    {
        $this->createJob();
        $this->addJobWithVerrou();
        $job_list = $this->workerSQL->getJobsToLaunch(4, $this->globalDaemon->id_daemon);
        static::assertCount(2, $job_list);
    }

    /**
     * @throws Exception
     */
    private function addJobWithDaemon(int $id_daemon): string
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->id_d = 'XYZT';
        $job->id_e = 1;
        $job->id_verrou = 'VERROU';
        $job->next_try = date('Y-M-d', strtotime('yesterday'));
        $job->id_daemon = $id_daemon;
        return $this->jobQueueSQL->createJob($job);
    }

    /**
     * @throws Exception
     */
    public function testGetJobToLaunchWithMultipleDaemons(): void
    {
        $this->addJobWithDaemon($this->globalDaemon->id_daemon);
        $this->addJobWithDaemon(2);
        $job_list = $this->workerSQL->getJobsToLaunch(4, $this->globalDaemon->id_daemon);
        static::assertCount(1, $job_list);
    }

    /**
     * @throws Exception
     */
    public function testgetActionEnCoursForConnecteur(): void
    {
        $job = new Job();
        $job->type = Job::TYPE_CONNECTEUR;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->next_try = date('Y-M-d', strtotime('yesterday'));
        $job->id_ce = 1;
        $id_job = $this->jobQueueSQL->createJob($job);
        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->attachJob($id_worker, $id_job);

        static::assertEquals(
            $id_worker,
            $this->workerSQL->getActionEnCoursForConnecteur(1, 'cible')
        );
    }
}
