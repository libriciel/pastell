<?php

declare(strict_types=1);

use Pastell\Configuration\JobStatus;
use Pastell\Model\Daemon\JobAdvancedFilters;

class WorkerSQLTest extends PastellTestCase
{
    private WorkerSQL $workerSQL;
    private Daemon $globalDaemon;
    private JobQueueSQL $jobQueueSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workerSQL =  $this->getObjectInstancier()->getInstance(WorkerSQL::class);
        $daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $daemonSQL->setNbWorkers((int)NB_WORKERS);
        $daemonSQL->insertGlobalDaemon('admin@libriciel.invalid');
        $this->globalDaemon = $daemonSQL->getGlobalDaemon();
        $this->jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
    }

    public function testCreate(): void
    {
        static::assertNotNull($this->workerSQL->create(42));
    }

    public function testGetInfo(): void
    {
        $id_worker = $this->workerSQL->create(42);
        $worker = $this->workerSQL->getWorker($id_worker);
        static::assertSame(42, $worker->pid);
    }

    public function testError(): void
    {
        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->error($id_worker, "Message d'erreur");
        $worker = $this->workerSQL->getWorker($id_worker);
        static::assertSame(1, $worker->termine);
    }

    public function testRunningWorkerInfo(): void
    {
        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->attachJob($id_worker, 12);
        $worker = $this->workerSQL->getRunningWorker(12);
        static::assertSame(12, $worker->id_job);
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
        static::assertSame((int)$id_worker, $workers[0]->id_worker);
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
    private function createJob(?string $next_try = null): string
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->next_try = $next_try ?? date('Y-M-d', strtotime('yesterday'));
        return $this->jobQueueSQL->createJob($job);
    }

    /**
     * @throws Exception
     */
    private function launchWorker(): int
    {
        $id_job = $this->createJob();
        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->attachJob($id_worker, $id_job);
        return (int)$id_worker;
    }

    public function testGetJobToLauch(): void
    {
        $id_job = $this->createJob();
        $id_worker = $this->workerSQL->create(42);

        $id_job_list = $this->workerSQL->getJobsToLaunch(5, $this->globalDaemon->id_daemon);
        static::assertSame([(int)$id_job], $id_job_list);

        $this->workerSQL->attachJob($id_worker, $id_job);
        static::assertEmpty($this->workerSQL->getJobsToLaunch(5, $this->globalDaemon->id_daemon));
    }

    public function testGetNbActif(): void
    {
        $this->launchWorker();
        static::assertSame(1, $this->workerSQL->getNbActif());
    }

    public function testGetNbActifForDameon(): void
    {
        $this->launchWorker();
        static::assertSame(1, $this->workerSQL->getNbActifForDaemon(1));
        static::assertSame(0, $this->workerSQL->getNbActifForDaemon(2));
    }

    public function testGetActif(): void
    {
        $id_worker = $this->launchWorker();
        $info = $this->workerSQL->getActif();
        static::assertSame($id_worker, $info[0]['id_worker']);
    }

    public function testGetJobListWithWorker(): void
    {
        $id_worker = $this->launchWorker();
        $job_list = $this->jobQueueSQL->getFilteredJobList(20, 0, 'toto');
        static::assertSame($id_worker, $job_list[0]->worker->id_worker);
        static::assertSame(1, $this->jobQueueSQL->getNbJob('toto'));
    }

    public function testGetJobSuspended(): void
    {
        $this->launchWorker();
        $filters = new JobAdvancedFilters(job_status: array_map('strval', JobStatus::suspendedValues()));
        $job_list = $this->jobQueueSQL->getFilteredJobList(20, 0, '', null, $filters);
        static::assertEmpty($job_list);
        static::assertSame(0, $this->jobQueueSQL->getNbJob('', null, $filters));
    }

    public function testGetJobWaiting(): void
    {
        $id_worker = $this->launchWorker();
        $filters = new JobAdvancedFilters(job_status: [(string)JobStatus::WAITING->value]);
        $job_list = $this->jobQueueSQL->getFilteredJobList(20, 0, '', null, $filters);
        static::assertSame($id_worker, $job_list[0]->worker->id_worker);
        static::assertSame(1, $this->jobQueueSQL->getNbJob('', null, $filters));
    }

    public function testGetJobLate(): void
    {
        $id_job_late = $this->createJob(date('Y-m-d H:i:s', strtotime('-1 hour')));
        $this->createJob(date('Y-m-d H:i:s', strtotime('+1 hour')));
        $id_job_suspended = $this->createJob(date('Y-m-d H:i:s', strtotime('-1 hour')));
        $this->jobQueueSQL->lock((int)$id_job_suspended, JobStatus::SUSPENDED_BY_USER);

        $filters = new JobAdvancedFilters(late: '1');
        $job_list = $this->jobQueueSQL->getFilteredJobList(20, 0, '', null, $filters);
        static::assertEqualsCanonicalizing(
            [(int)$id_job_late, (int)$id_job_suspended],
            array_map(static fn ($job) => $job->id_job, $job_list)
        );
        static::assertSame(2, $this->jobQueueSQL->getNbJob('', null, $filters));

        $filters = new JobAdvancedFilters(
            job_status: [(string)JobStatus::SUSPENDED_BY_USER->value],
            late: '1',
        );
        $job_list = $this->jobQueueSQL->getFilteredJobList(20, 0, '', null, $filters);
        static::assertCount(1, $job_list);
        static::assertSame((int)$id_job_suspended, $job_list[0]->id_job);
        static::assertSame(1, $this->jobQueueSQL->getNbJob('', null, $filters));
    }

    public function testGetJobActif(): void
    {
        $id_worker = $this->launchWorker();
        $job_list = $this->jobQueueSQL->getFilteredJobList(20, 0, 'actif');
        static::assertSame($id_worker, $job_list[0]->worker->id_worker);
        static::assertSame(1, $this->jobQueueSQL->getNbJob('actif'));
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
        $this->assertSame([(int)$id_job_1], $id_job_list);

        $id_worker = $this->workerSQL->create(42);
        $this->workerSQL->attachJob($id_worker, $id_job_1);

        $all_verrou = $this->workerSQL->getVerrou();
        static::assertSame(['VERROU'], $all_verrou);

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
        $this->assertSame([(int)$id_job_1], $id_job_list);
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
        static::assertSame(['VERROU'], $all_verrou);
    }

    /**
     * @throws Exception
     */
    public function testGetFirstJobToLaunch(): void
    {
        $this->addJobWithVerrou();
        $job = $this->workerSQL->getJobsToLaunchByLock('VERROU', $this->globalDaemon->id_daemon);
        static::assertSame(1, $job[0]['id_job']);
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

        static::assertSame(
            (int)$id_worker,
            $this->workerSQL->getActionEnCoursForConnecteur(1, 'cible')
        );
    }
}
