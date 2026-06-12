<?php

use Pastell\Configuration\JobStatus;

class JobManagerTest extends PastellTestCase
{
    /** @var  JobManager */
    private $jobManager;

    /** @var  JobQueueSQL */
    private $jobQueueSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobManager = $this->getObjectInstancier()->getInstance(JobManager::class);
        $this->jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
    }

    public function testSetJobForConnecteur()
    {
        $id_job = $this->jobManager->setJobForConnecteur(13, "une_action_auto", "message");
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertTrue($job->isTypeOK());
        $this->assertEquals(Job::TYPE_CONNECTEUR, $job->type);
        $this->assertEquals(13, $job->id_ce);
        $this->assertEquals(1, $job->id_e);
    }

    public function testSetJobForConnecteurUpdate()
    {
        $id_job = $this->jobManager->setJobForConnecteur(13, "une_action_auto", "message");
        $this->jobQueueSQL->getJob($id_job);
        $id_job_2 = $this->jobManager->setJobForConnecteur(13, "une_action_auto", "message 2");
        $this->assertEquals($id_job, $id_job_2);
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertTrue($job->isTypeOK());
        $this->assertEquals(Job::TYPE_CONNECTEUR, $job->type);
        $this->assertEquals(13, $job->id_ce);
        $this->assertEquals(1, $job->id_e);
        $this->assertEquals("message 2", $job->last_message);
        $this->assertEquals(1, $job->nb_try);
    }

    public function testSetJobForDocument()
    {
        $info = $this->getInternalAPI()->post("Entite/1/Document", ['type' => 'test']);
        $id_d = $info['info']['id_d'];
        $this->getInternalAPI()->post("Entite/1/Document/$id_d/action/action-auto");
        $id_job = $this->jobQueueSQL->getJobIdForDocument(1, $id_d);
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals(0, $job->nb_try);
        $this->assertEquals('DEFAULT_FREQUENCE', $job->id_verrou);

        $id_job = $this->jobManager->setJobForDocument(1, $id_d, "test");
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals($id_d, $job->id_d);
        $this->assertEquals('action-auto', $job->etat_source);
        $this->assertEquals(1, $job->nb_try);
        $this->assertEquals('DEFAULT_FREQUENCE', $job->id_verrou);
        $this->assertLessThan(1, strtotime("+2 minutes") - strtotime($job->next_try));
    }

    public function testSetJobForTraitementLot()
    {
        $info = $this->getInternalAPI()->post("Entite/1/Document", ['type' => 'test']);
        $id_d = $info['info']['id_d'];
        $id_job = $this->jobManager->setTraitementLot(1, $id_d, 0, 'ok');
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals($id_d, $job->id_d);
        $this->assertEquals('ok', $job->etat_cible);
    }

    public function testJobEnding()
    {
        $info = $this->getInternalAPI()->post("Entite/1/Document", ['type' => 'test']);
        $id_d = $info['info']['id_d'];
        $this->getInternalAPI()->post("Entite/1/Document/$id_d/action/action-auto");
        $this->getInternalAPI()->post("Entite/1/Document/$id_d/action/action-auto-end");
        $this->assertFalse($this->jobQueueSQL->getJobIdForDocument(1, $id_d));
    }

    public function testDisableJobQueue()
    {
        $this->jobManager->setDisableJobQueue(true);
        $this->assertTrue($this->jobManager->setJobForConnecteur(42, 'toto', 'blutrepoi'));
        $this->assertTrue($this->jobManager->setJobForDocument(42, 42, 'blutrepoi'));
        $this->assertTrue($this->jobManager->setTraitementLot(42, 42, 42, 'blutrepoi'));
    }

    public function testHasActionProgramme()
    {
        $info = $this->getInternalAPI()->post("Entite/1/Document", ['type' => 'test']);
        $id_d = $info['info']['id_d'];
        $this->jobManager->setTraitementLot(1, $id_d, 0, 'ok');
        $this->assertTrue($this->jobManager->hasActionProgramme(1, $id_d));
    }

    public function testDeleteConnecteur()
    {
        $id_job = $this->jobManager->setJobForConnecteur(13, "une_action_auto", "message");
        $this->assertNotNull($this->jobQueueSQL->getJob($id_job));
        $this->jobManager->deleteConnecteur(13);
        $this->assertNull($this->jobQueueSQL->getJob($id_job));
    }

    public function testChainedAction()
    {
        $info = $this->getInternalAPI()->post("Entite/1/Document", ['type' => 'test']);
        $id_d = $info['info']['id_d'];
        $this->getInternalAPI()->post("Entite/1/Document/$id_d/action/chained-action-1");
        $this->getInternalAPI()->post("Entite/1/Document/$id_d/action/chained-action-2");
        $id_job = $this->jobQueueSQL->getJobIdForDocument(1, $id_d);
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals("chained-action-3", $job->etat_cible);
        $this->assertEquals("chained-action-2", $job->etat_source);
        $this->assertEquals(0, $job->nb_try);
    }

    public function testNoConnecteurFrequence()
    {
        $connecteurFrequenceSQL = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);
        foreach ($connecteurFrequenceSQL->getAll() as $connecteurFrequence) {
            $connecteurFrequenceSQL->delete($connecteurFrequence->id_cf);
        }
        $info = $this->getInternalAPI()->post("Entite/1/Document", ['type' => 'test']);
        $id_d = $info['info']['id_d'];
        $this->jobManager->setTraitementLot(1, $id_d, 0, 'ok');
        $id_job = $this->jobQueueSQL->getJobIdForDocument(1, $id_d);
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals($job->id_verrou, JobManager::DEFAULT_ID_VERROU);
    }

    public function testDocumentFrequence()
    {

        $connecteurFrequenceSQL = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);

        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = 'GED';
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence->type_document = 'actes-generique';
        $connecteurFrequence->id_verrou = 'FREQUENCE_FakeGED';
        $connecteurFrequenceSQL->edit($connecteurFrequence);

        $connecteurFrequence_list = $this->jobManager->getNearestConnecteurForDocument(3);
        $this->assertEquals("DEFAULT_FREQUENCE", $connecteurFrequence_list['actes-generique']->id_verrou);

        $connecteurFrequence_list = $this->jobManager->getNearestConnecteurForDocument(5);
        $this->assertEquals("FREQUENCE_FakeGED", $connecteurFrequence_list['actes-generique']->id_verrou);
    }


    public function testConnecteurTerminated()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = 'test';
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence->type_document = 'test';
        $connecteurFrequence->action = 'never-ending-action';
        $connecteurFrequence->id_verrou = 'TEST_FREQUENCE';
        $connecteurFrequence->expression = "1 X 1";
        $connecteurFrequenceSQL = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);
        $connecteurFrequenceSQL->edit($connecteurFrequence);

        $info = $this->createDocument('test');
        $id_d = $info['info']['id_d'];
        $this->triggerActionOnDocument($id_d, 'to-never-ending-action');
        $this->triggerActionOnDocument($id_d, 'never-ending-action');
        $id_job = $this->jobQueueSQL->getJobIdForDocument(1, $id_d);
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals(JobStatus::WAITING, $job->job_status);
        $this->assertEquals(1, $job->nb_try);
        $this->triggerActionOnDocument($id_d, 'never-ending-action');
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals(JobStatus::SUSPENDED_TRY_MAX, $job->job_status);
        $this->assertEquals(1, $job->nb_try);
    }

    public function testNoJobWithoutActionAutomatiqueOnConnecteur()
    {
        $id_job = $this->jobManager->setJobForConnecteur(13, "ok", "message");
        $this->assertFalse($id_job);
    }

    public function testJobWithActionAutomatiqueOnConnecteur()
    {
        $id_job = $this->jobManager->setJobForConnecteur(13, "une_action_auto", "message");
        $this->assertNotFalse($id_job);
    }

    public function testGetNearestConnecteurFrequence()
    {
        $connecteurFrequence = $this->jobManager->getNearestConnecteurFrequence(13);
        $this->assertEquals(1, $connecteurFrequence->id_cf);
        $this->assertEquals("DEFAULT_FREQUENCE", $connecteurFrequence->id_verrou);
    }

    public function testGetNearestConnecteurForDocument()
    {
        $connecteurFrequence_list = $this->jobManager->getNearestConnecteurForDocument(13);
        $this->assertEquals("DEFAULT_FREQUENCE", $connecteurFrequence_list['test']->id_verrou);
    }

    public function testsetTraitementParLotBulk()
    {
        $info = $this->getInternalAPI()->post("Entite/1/Document", ['type' => 'test']);

        $id_d = $info['info']['id_d'];
        $this->getInternalAPI()->post("Entite/1/Document/$id_d/action/to-never-ending-action");

        $this->jobManager->setTraitementParLotBulk(1, 'test', 'to-never-ending-action', 'fatal-error');

        $id_job = $this->jobQueueSQL->getJobIdForDocument(1, $id_d);
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertEquals('fatal-error', $job->etat_cible);
    }

    public function testWrongActionDoesNotLockExistingJob()
    {
        $document = $this->createDocument('test');
        $id_d = $document['id_d'];
        $this->triggerActionOnDocument($id_d, 'action-auto');
        $id_job = $this->jobQueueSQL->getJobIdForDocument(self::ID_E_COL, $id_d);
        $auto_job = $this->jobQueueSQL->getJob($id_job);
        $originalDateNextTry = $auto_job->next_try;
        $dateNextTry = DateTime::createFromFormat('Y-m-d H:i:s', $originalDateNextTry);
        $auto_job->next_try = $dateNextTry->modify('-1 min')->format('Y-m-d H:i:s');
        $this->jobQueueSQL->updateJob($auto_job);
        $this->triggerActionOnDocument($id_d, 'does-not-exist');
        $id_job = $this->jobQueueSQL->getJobIdForDocument(self::ID_E_COL, $id_d);
        $job = $this->jobQueueSQL->getJob($id_job);
        $this->assertSame(JobStatus::WAITING, $job->job_status);
        $this->assertSame($originalDateNextTry, $job->next_try);
    }
}
