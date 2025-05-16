<?php

class JobQueueSQLTest extends PastellTestCase
{
    public const ID_D = 'foo';
    private JobQueueSQL $jobQueueSQL;

    /**
     * @var Job
     */
    private $job;

    protected function setUp(): void
    {
        parent::setUp();
        $daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $workerSQL = new WorkerSQL(static::getSQLQuery());
        $this->jobQueueSQL = new JobQueueSQL(
            static::getSQLQuery(),
            $workerSQL,
            $daemonSQL,
        );
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
}
