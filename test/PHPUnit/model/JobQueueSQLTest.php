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

    public function testAddJobNoCible(): void
    {
        $this->job->type = Job::TYPE_DOCUMENT;
        $this->job->etat_cible = 'cible';
        $this->job->etat_source = 'source';
        $id_job = $this->jobQueueSQL->insert($this->job);
        self::assertNotSame(0, $id_job);
    }

    /**
     * @throws Exception
     */
    public function testDeleteJobAlsoClearsWorker(): void
    {
        $this->job->type = Job::TYPE_DOCUMENT;
        $this->job->etat_cible = 'cible';
        $this->job->etat_source = 'source';
        $id_job = $this->jobQueueSQL->insert($this->job);

        $workerSQL = new WorkerSQL(self::getSQLQuery());
        $id_worker = $workerSQL->create(1234);
        $workerSQL->attachJob($id_worker, $id_job);

        $this->jobQueueSQL->deleteJob($id_job);

        $this->assertEmpty($workerSQL->getInfo($id_worker));
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
        $id_job = $this->jobQueueSQL->insert($this->job);
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

    public function testInsert(): void
    {
        $this->job->type = Job::TYPE_CONNECTEUR;
        $this->job->id_e = PastellTestCase::ID_E_COL;
        $this->job->id_ce = 13;
        $this->job->etat_source = 'source';
        $this->job->etat_cible = 'cible';
        $this->job->id_verrou = 'VERROU';

        $id_job = $this->jobQueueSQL->insert($this->job);

        $job = $this->jobQueueSQL->getJob($id_job);
        self::assertSame($id_job, (int)$job->id_job);
        self::assertSame(Job::TYPE_CONNECTEUR, (int)$job->type);
        self::assertSame(13, (int)$job->id_ce);
        self::assertSame('cible', $job->etat_cible);
        self::assertSame('VERROU', $job->id_verrou);
    }

    public function testGetStatInfo(): void
    {
        $id_job = $this->insertLateJob();
        $this->insertLateJob();
        $this->jobQueueSQL->lock($id_job);

        $stat_info = $this->jobQueueSQL->getStatInfo();
        self::assertSame(2, $stat_info['nb_job']);
        self::assertSame(1, $stat_info['nb_lock']);
        self::assertSame(2, $stat_info['nb_wait']);
        self::assertSame(0, $stat_info['nb_lock_one_hour']);

        $sql = <<<SQL
UPDATE job_queue SET lock_since = ? WHERE id_job = ?
SQL;
        self::getSQLQuery()->query(
            $sql,
            date('Y-m-d H:i:s', strtotime('-2 hours')),
            $id_job
        );
        self::assertSame(1, $this->jobQueueSQL->getNbLockSinceOneHour());
    }

    public function testGetMaxLastTry(): void
    {
        self::assertNull($this->jobQueueSQL->getMaxLastTryOneHourLate());

        $job = $this->jobQueueSQL->getJob($this->insertLateJob());
        $job->nb_try = 1;
        $job->last_try = date('Y-m-d H:i:s', strtotime('-3 hours'));
        $this->jobQueueSQL->updateJob($job);

        self::assertSame($job->last_try, $this->jobQueueSQL->getMaxLastTryOneHourLate());
    }

    private function insertLateJob(): int
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->next_try = date('Y-m-d H:i:s', strtotime('-2 hours'));
        return $this->jobQueueSQL->insert($job);
    }
}
