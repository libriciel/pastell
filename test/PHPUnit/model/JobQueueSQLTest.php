<?php

class JobQueueSQLTest extends PastellTestCase
{
    public const ID_D = 'foo';

    /**
     * @var JobQueueSQL
     */
    private $jobQueueSQL;

    /**
     * @var Job
     */
    private $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobQueueSQL = new JobQueueSQL($this->getSQLQuery());
        $this->job = new Job();
    }

    /**
     * @throws Exception
     */
    public function testAddJobNoJobConfigured()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Type de job non pris en charge");
        $this->jobQueueSQL->createJob($this->job);
    }

    /**
     * @throws Exception
     */
    public function testAddJobNoCible()
    {
        $this->job->type = Job::TYPE_DOCUMENT;
        $this->job->etat_cible = "cible";
        $this->job->etat_source = "source";
        $id_job = $this->jobQueueSQL->createJob($this->job);
        $this->assertNotEquals(0, $id_job);
    }

    /**
     * @throws Exception
     */
    public function testAddJob()
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_cible = "cible";
        $job->etat_source = "source";
        $job->id_verrou = "VERROU";
        $id_job = $this->jobQueueSQL->createJob($job);
        $job_result = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals("VERROU", $job_result->id_verrou);
    }

    /**
     * @throws Exception
     */
    public function testDeleteDocument()
    {
        $this->job->type = Job::TYPE_DOCUMENT;
        $this->job->etat_cible = "cible";
        $this->job->etat_source = "source";
        $this->job->id_e = PastellTestCase::ID_E_COL;
        $this->job->id_d = self::ID_D;
        $id_job = $this->jobQueueSQL->createJob($this->job);
        $this->assertNotEmpty($id_job);
        $this->assertEquals(
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
        $this->assertFalse(
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
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_cible = 'cible';
        $job->etat_source = 'source';
        $job->id_daemon = DaemonSQL::GLOBAL_DAEMON;
        $this->jobQueueSQL->createJob($job);
        static::assertCount(1, $this->jobQueueSQL->getJobsByDaemon(DaemonSQL::GLOBAL_DAEMON));
    }

    /**
     * @throws Exception
     */
    public function testUpdateDaemon(): void
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_cible = 'cible';
        $job->etat_source = 'source';
        $job->id_daemon = DaemonSQL::GLOBAL_DAEMON;
        $job_id = $this->jobQueueSQL->createJob($job);
        static::assertCount(1, $this->jobQueueSQL->getJobsByDaemon(DaemonSQL::GLOBAL_DAEMON));
        $this->jobQueueSQL->updateDaemon((int)$job_id, 2);
        static::assertCount(0, $this->jobQueueSQL->getJobsByDaemon(DaemonSQL::GLOBAL_DAEMON));
    }
}
