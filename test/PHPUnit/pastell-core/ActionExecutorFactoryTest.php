<?php

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class ActionExecutorFactoryTest extends PastellTestCase
{
    /** @return ActionExecutorFactory */
    private function getActionExcecutorFactory()
    {
        return $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
    }

    /** @return JobManager */
    private function getJobManager()
    {
        return $this->getObjectInstancier()->getInstance(JobManager::class);
    }

    /** @return JobQueueSQL */
    private function getJobQueueSQL()
    {
        return $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
    }

    /** @return WorkerSQL */
    private function getWorkerSQL()
    {
        return $this->getObjectInstancier()->getInstance(WorkerSQL::class);
    }

    public function testAccessOnConnecteur()
    {

        $id_ce = 13;

        $this->getJobManager()->setJobForConnecteur(
            $id_ce,
            "ok",
            "test"
        );

        $this->assertTrue(
            $this->getActionExcecutorFactory()->executeOnConnecteur($id_ce, 0, "ok")
        );
        $this->assertEquals(
            "OK !",
            $this->getActionExcecutorFactory()->getLastMessage()
        );
    }

    public function testConcurentConnecteurAccess()
    {

        $id_ce = 13;

        $id_job = $this->getJobManager()->setJobForConnecteur(
            $id_ce,
            "une_action_long_auto",
            "test"
        );

        $id_worker = $this->getWorkerSQL()->create(42);
        $this->getWorkerSQL()->attachJob($id_worker, $id_job);

        $this->assertFalse(
            $this->getActionExcecutorFactory()->executeOnConnecteur(
                $id_ce,
                0,
                "une_action_long_auto"
            )
        );
        $this->assertEquals(
            "Une action est déjà en cours de réalisation sur ce connecteur",
            $this->getActionExcecutorFactory()->getLastMessage()
        );
    }

    /**
     * @throws Exception
     */
    public function testExecuteOnDocumentWithoutConnecteur()
    {

        $document = $this->createDocument('pdf-generique');
        $id_d = $document['id_d'];
        $this->getInternalAPI()->patch(
            "/entite/1/document/$id_d",
            [
            'libelle' => 'Test pdf générique',
                'envoi_ged_1' => '1',
            ]
        );

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->addFileFromCopy(
            'document',
            'Délib Libriciel.pdf',
            __DIR__ . "/../module/pdf-generique/fixtures/Délib Libriciel.pdf"
        );

        $this->triggerActionOnDocument($id_d, 'orientation');

        $action = $this->triggerActionOnDocument($id_d, 'send-ged-1');
        $this->assertFalse($action);

        $this->assertLastMessage("Aucun connecteur de type GED n'est associé au type de dossier pdf-generique");

        $id_job  = $this->getJobQueueSQL()->getJobIdForDocument(1, $id_d);
        $job = $this->getJobQueueSQL()->getJob($id_job);

        static::assertSame(1, $job->is_lock);
    }

    //Une fuite mémoire existait sur les processeur Monolog
    public function testNoLoggerProcessorLeftOnConnecteur()
    {
        $id_ce = 13;
        $this->getActionExcecutorFactory()->executeOnConnecteur($id_ce, 0, "ok");
        $this->getActionExcecutorFactory()->executeOnConnecteur($id_ce, 0, "ok");
        $this->assertCount(0, $this->getLogger()->getProcessors());
    }

    public function testNoLoggerProcessorLeftOnDocument()
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->assertTrue($this->getActionExcecutorFactory()->executeOnDocument(1, 0, $id_d, 'ok'));
        $this->assertCount(0, $this->getLogger()->getProcessors());
    }

    public function testExecuteOnConnecteurWhenLock()
    {
        $this->mockLockFactory();
        $this->getActionExcecutorFactory()->executeOnConnecteur(13, 0, "ok");
        $this->assertLastLog('executeOnConnecteur : unable to lock action on connecteur (id_ce=13, id_u=0, action_name=ok)');
        $this->assertLastMessage("Une action est déjà en cours de réalisation sur ce connecteur");
    }

    public function testExecuteOnDocumentWhenLock()
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->mockLockFactory();
        $this->getActionExcecutorFactory()->executeOnDocument(1, 0, $id_d, 'ok');
        $this->assertLastLog("executeOnDocument : unable to lock action on document (id_e=1, id_u=0, id_d=$id_d, action_name=ok)");
        $this->assertLastMessage("Une action est déjà en cours de réalisation sur ce document");
    }

    private function mockLockFactory(): void
    {
        $lockInterface = $this->createMock(LockInterface::class);
        $lockFactory = $this->createMock(LockFactory::class);

        $lockFactory->expects($this->any())
            ->method('createLock')
            ->willReturn($lockInterface);

        $this->getObjectInstancier()->setInstance(LockFactory::class, $lockFactory);
    }
}
