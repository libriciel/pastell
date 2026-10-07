<?php

declare(strict_types=1);

use Pastell\Configuration\JobStatus;

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
        $daemonControler->setServerInfo(['REQUEST_METHOD' => 'POST']);
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
        $daemonControler->setServerInfo(['REQUEST_METHOD' => 'POST']);
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
        $daemonControler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        $this->setPostInfo(['data' => [$daemon->id_daemon => 0]]);
        $this->expectException(LastErrorException::class);
        $this->expectExceptionMessage('Chaque gestionnaire de tâches doit avoir au moins 1 processus alloué');
        $daemonControler->allocateAction();
    }

    public function testVerrouAction(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->expectOutputRegex('#une_action_auto#');

        $daemonControler->_beforeAction();
        $daemonControler->verrouAction();
    }

    public function testLockAction(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');

        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $job = $jobQueueSQL->getJob($id_job);
        static::assertSame(JobStatus::WAITING, $job->job_status);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_verrou' => 'DEFAULT_FREQUENCE','etat_source' => 'une_action_auto','etat_cible' => 'une_action_auto']);
        try {
            $daemonControler->lockAction();
        } catch (Exception) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $job->job_status);
    }

    public function testUnLockAction(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');

        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $jobQueueSQL->lock($id_job, JobStatus::SUSPENDED_BY_USER);

        $job = $jobQueueSQL->getJob($id_job);
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $job->job_status);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_verrou' => 'DEFAULT_FREQUENCE','etat_source' => 'une_action_auto','etat_cible' => 'une_action_auto']);
        try {
            $daemonControler->unlockAction();
        } catch (Exception) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        static::assertSame(JobStatus::WAITING, $job->job_status);
    }

    public function testLockSingleJob(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');
        $job = $jobQueueSQL->getJob($id_job);
        static::assertSame(JobStatus::WAITING, $job->job_status);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_job' => $id_job]);
        try {
            $daemonControler->lockAction();
        } catch (Exception) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $job->job_status);
    }

    public function testUnlockSingleJob(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $jobQueueSQL->lock($id_job, JobStatus::SUSPENDED_BY_USER);

        $job = $jobQueueSQL->getJob($id_job);
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $job->job_status);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_job' => $id_job]);
        try {
            $daemonControler->unlockAction();
        } catch (Exception) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        static::assertSame(JobStatus::WAITING, $job->job_status);
    }

    public function testJobActionShowsSearchAndSort(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['job_status' => array_map('strval', JobStatus::suspendedValues())]);
        $daemonControler->_beforeAction();

        ob_start();
        $daemonControler->jobAction();
        $output = ob_get_clean();

        static::assertStringContainsString('Recherche avancée', $output);
        static::assertStringContainsString('Trier par', $output);
    }

    public function testBulkButtons(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $daemonControler->_beforeAction();

        ob_start();
        $daemonControler->jobAction();
        $output = ob_get_clean();

        static::assertStringNotContainsString('id_job_list', $output);
        static::assertStringContainsString('Suspendre les travaux en attente (1)', $output);
        static::assertStringContainsString('Reprendre les travaux suspendus (0)', $output);
    }

    public function testBulkLockAndUnlock(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $daemonControler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        $this->setPostInfo([]);
        try {
            $daemonControler->lockAllAction();
        } catch (LastMessageException) {
            /* Nothing to do */
        }
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $jobQueueSQL->getJob($id_job)->job_status);

        $this->setPostInfo([]);
        try {
            $daemonControler->unlockAllAction();
        } catch (LastMessageException) {
            /* Nothing to do */
        }
        static::assertSame(JobStatus::WAITING, $jobQueueSQL->getJob($id_job)->job_status);
    }

    public function testBulkActionKeepsFinishedWorkers(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');
        $workerSQL = $this->getObjectInstancier()->getInstance(WorkerSQL::class);
        $id_worker = $workerSQL->create(1234);
        $workerSQL->attachJob($id_worker, $id_job);
        $this->getSQLQuery()->query('UPDATE worker SET termine = 1 WHERE id_worker = ?', $id_worker);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $daemonControler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        $this->setPostInfo([]);
        try {
            $daemonControler->lockAllAction();
        } catch (LastMessageException) {
            /* Nothing to do */
        }
        static::assertSame(
            1,
            (int)$this->getSQLQuery()->queryOne('SELECT count(*) FROM worker WHERE id_worker = ?', $id_worker)
        );
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $jobQueueSQL->getJob($id_job)->job_status);
    }

    public function testBulkActionRequiresPost(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $daemonControler->setServerInfo(['REQUEST_METHOD' => 'GET']);
        try {
            $daemonControler->lockAllAction();
            static::fail('LastErrorException attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('Cette action doit être soumise par un formulaire', $e->getMessage());
        }
        static::assertSame(JobStatus::WAITING, $jobQueueSQL->getJob($id_job)->job_status);
    }

    public function testBulkLockSearch(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/13/action/une_action_auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $daemonControler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        $this->setPostInfo([
            'id_verrou' => ['AUTRE_VERROU'],
        ]);
        try {
            $daemonControler->lockAllAction();
            static::fail('LastErrorException attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString("Aucun travail n'a pu être suspendu", $e->getMessage());
        }
        static::assertSame(JobStatus::WAITING, $jobQueueSQL->getJob($id_job)->job_status);

        $this->setPostInfo([
            'search_id_e' => '1',
        ]);
        try {
            $daemonControler->lockAllAction();
            static::fail('LastMessageException attendue');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('1 travail a été suspendu', $e->getMessage());
        }
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $jobQueueSQL->getJob($id_job)->job_status);
    }

    public function testActifJobsHaveNoSearch(): void
    {
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['filtre' => 'actif']);
        $daemonControler->_beforeAction();

        ob_start();
        $daemonControler->jobAction();
        $output = ob_get_clean();

        static::assertStringContainsString('Liste des travaux actifs', $output);
        static::assertStringNotContainsString('Recherche avancée', $output);
        static::assertStringNotContainsString('Trier par', $output);
    }

    public function testIndexActionHasNoSearchNorSort(): void
    {
        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $daemonControler->_beforeAction();

        ob_start();
        $daemonControler->indexAction();
        $output = ob_get_clean();

        static::assertStringNotContainsString('Recherche avancée', $output);
        static::assertStringNotContainsString('Trier par', $output);
    }
}
