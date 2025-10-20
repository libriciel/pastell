<?php

class DaemonControlerTest extends ControlerTestCase
{
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
        $this->assertEquals(0, $job->is_lock);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_verrou' => 'DEFAULT_FREQUENCE','etat_source' => 'une_action_auto','etat_cible' => 'une_action_auto']);
        try {
            $daemonControler->lockAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(1, $job->is_lock);
    }

    public function testUnLockAction()
    {
        $this->getInternalAPI()->post("/entite/1/connecteur/13/action/une_action_auto");

        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $jobQueueSQL->lock($id_job);

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(1, $job->is_lock);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_verrou' => 'DEFAULT_FREQUENCE','etat_source' => 'une_action_auto','etat_cible' => 'une_action_auto']);
        try {
            $daemonControler->unlockAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(0, $job->is_lock);
    }

    public function testLockSingleJob()
    {
        $this->getInternalAPI()->post("/entite/1/connecteur/13/action/une_action_auto");
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');
        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(0, $job->is_lock);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_job' => $id_job]);
        try {
            $daemonControler->lockAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(1, $job->is_lock);
    }

    public function testUnlockSingleJob()
    {
        $this->getInternalAPI()->post("/entite/1/connecteur/13/action/une_action_auto");
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job = $jobQueueSQL->getJobIdForConnecteur(13, 'une_action_auto');

        $jobQueueSQL->lock($id_job);

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(1, $job->is_lock);

        $daemonControler = $this->getControlerInstance(DaemonControler::class);
        $this->setGetInfo(['id_job' => $id_job]);
        try {
            $daemonControler->unlockAction();
        } catch (Exception $e) {
            /* Nothing to do */
        }

        $job = $jobQueueSQL->getJob($id_job);
        $this->assertEquals(0, $job->is_lock);
    }
}
