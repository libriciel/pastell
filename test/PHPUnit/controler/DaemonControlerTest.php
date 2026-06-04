<?php

declare(strict_types=1);

class DaemonControlerTest extends ControlerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $daemonSQL->setNbWorkers(10);
        $daemonSQL->insertGlobalDaemon('mail@libriciel.invalid');
    }

    /**
     * @throws LastMessageException
     * @throws UnrecoverableException
     */
    public function testDoCreateRejectsZeroWorkers(): void
    {
        /** @var DaemonControler $daemonControler */
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setPostInfo([
            'id_e' => 2,
            'nb_allocated_workers' => 0,
            'daemon_admin_email' => 'admin@example.invalid',
            'late_jobs_threshold' => 1,
        ]);
        $this->expectException(LastErrorException::class);
        $this->expectExceptionMessage('Le nombre de processus à allouer doit être supérieur ou égal à 1');
        $daemonControler->doCreateAction();
    }

    /**
     * @throws LastMessageException
     */
    public function testDoEditConfigurationRejectsZeroWorkers(): void
    {
        /** @var DaemonControler $daemonControler */
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setPostInfo(['nb_workers' => 0]);
        $this->expectException(LastErrorException::class);
        $this->expectExceptionMessage('Le nombre de processus doit être supérieur ou égal à 1');
        $daemonControler->doEditConfigurationAction();
    }

    /**
     * @throws UnrecoverableException
     * @throws LastMessageException
     */
    public function testAllocateRejectsZeroWorkersPerDaemon(): void
    {
        $daemonManager = $this->getObjectInstancier()->getInstance(DaemonManager::class);
        $daemon = $daemonManager->addDaemon(2, 3, 'admin@example.invalid', 1);

        /** @var DaemonControler $daemonControler */
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setPostInfo(['data' => [$daemon->id_daemon => 0]]);
        $this->expectException(LastErrorException::class);
        $this->expectExceptionMessage('Chaque gestionnaire de tâches doit avoir au moins 1 processus alloué');
        $daemonControler->allocateAction();
    }

    public function testVerrouAction()
    {
        $this->getInternalAPI()->post("/entite/1/connecteur/13/action/une_action_auto");
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->expectOutputRegex("#une_action_auto#");

        $daemonControler->_beforeAction();
        $daemonControler->verrouAction();
    }

    public function testLockAction()
    {
        $this->getInternalAPI()->post("/entite/1/connecteur/13/action/une_action_auto");

        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(Job::WAITING, $job->job_status);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_verrou' => 'DEFAULT_FREQUENCE','etat_source' => 'une_action_auto','etat_cible' => 'une_action_auto']);
        try {
            $daemonControler->lockAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(Job::SUSPENDED_BY_USER, $job->job_status);
    }

    public function testUnLockAction()
    {
        $this->getInternalAPI()->post("/entite/1/connecteur/13/action/une_action_auto");

        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $jobQueueSQL->lock($id_job);

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(Job::SUSPENDED_BY_USER, $job->job_status);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_verrou' => 'DEFAULT_FREQUENCE','etat_source' => 'une_action_auto','etat_cible' => 'une_action_auto']);
        try {
            $daemonControler->unlockAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(0, $job->job_status);
    }

    public function testLockSingleJob()
    {
        $this->getInternalAPI()->post("/entite/1/connecteur/13/action/une_action_auto");
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');
        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(Job::WAITING, $job->job_status);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_job' => $id_job]);
        try {
            $daemonControler->lockAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(Job::SUSPENDED_BY_USER, $job->job_status);
    }

    public function testUnlockSingleJob()
    {
        $this->getInternalAPI()->post("/entite/1/connecteur/13/action/une_action_auto");
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $jobQueueSQL->lock($id_job);

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(Job::SUSPENDED_BY_USER, $job->job_status);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_job' => $id_job]);
        try {
            $daemonControler->unlockAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(Job::WAITING, $job->job_status);
    }
}
