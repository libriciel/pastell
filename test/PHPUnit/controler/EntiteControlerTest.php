<?php

use Pastell\Configuration\JobStatus;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;

class EntiteControlerTest extends ControlerTestCase
{
    /**
     * @var EntiteControler
     */
    private $entiteControler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entiteControler = $this->getControlerInstance(EntiteControler::class);
    }

    public function testConnecteurAction()
    {
        $this->expectOutputRegex('#Liste des connecteurs globaux#');
        $this->setGetInfo(['global' => 1]);
        $this->entiteControler->connecteurAction();
        $all_connecteur = $this->entiteControler->getViewParameter();
        $this->assertEquals('horodateur-interne', $all_connecteur['all_connecteur'][0]['id_connecteur']);
    }


    public function testUtilisateurAction()
    {
        $this->expectOutputRegex("#Liste des utilisateurs#");
        $this->entiteControler->utilisateurAction();
        $utilisateur_list = $this->entiteControler->getViewParameter()['liste_utilisateur'];
        $this->assertEquals('Pommateau', $utilisateur_list[0]['nom']);
    }

    /**
     * @throws Exception
     */
    public function testDetailEntite()
    {
        $this->expectOutputRegex("#Informations - Pastell#");

        $this->setGetInfo(['id_e' => 1]);
        $this->entiteControler->_beforeAction();
        $this->entiteControler->detailEntite();
        $info = $this->entiteControler->getViewParameter()['entiteExtendedInfo'];
        $this->assertEquals('Bourg-en-Bresse', $info['denomination']);
    }

    public function testExportUtilisateurAction()
    {
        $user = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $id_u = $user->create('other', 'other', 'other@other.other', 'other');

        $roleUser = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUser->addRole($id_u, 'autre', 0);

        $this->setGetInfo([
            'id_e' => 0,
            'descendance' => 'on',
            'role' => 'admin',
            'search' => ''
        ]);

        ob_start();
        $this->entiteControler->exportUtilisateurAction();
        $result = ob_get_contents();
        ob_end_clean();
        $this->assertMatchesRegularExpression('/3,other,,,other@other.other/', $result);

        $this->setGetInfo([
            'id_e' => 0,
            'descendance' => 'on',
            'role_selected' => 'admin',
            'search' => ''
        ]);

        ob_start();
        $this->entiteControler->exportUtilisateurAction();
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertDoesNotMatchRegularExpression('/3,other,,,other@other.other/', $result);
    }

    public function testNumberOfUsersIsCorrect()
    {
        $this->setGetInfo([
            'id_e' => 0,
            'descendance' => 'on',
            'role' => 'does not exist',
            'search' => 'eric'
        ]);

        ob_start();
        $this->entiteControler->utilisateurAction();
        ob_end_clean();

        $info = $this->entiteControler->getViewParameter();
        $this->assertEquals(
            0,
            $info['nb_utilisateur']
        );
        $this->assertCount(
            0,
            $info['liste_utilisateur']
        );
    }

    /**
     * @throws Exception
     */
    public function testDisplayEntiteWithRoleOnRootAndChild()
    {
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)
            ->addRole(self::ID_U_ADMIN, 'admin', self::ID_E_COL);
        $this->entiteControler->_beforeAction();
        ob_start();
        $this->entiteControler->detailAction();
        ob_end_clean();

        $info = $this->entiteControler->getViewParameter();

        $this->assertSame(1, $info['nbCollectivite']);
        $this->assertCount(1, $info['liste_collectivite']);
    }

    /**
     * @throws UnrecoverableException
     * @throws LastErrorException
     */
    public function testDoEditionAction(): void
    {
        $this->setPostInfo([
            'id_e' => 0,
            'siren' => '000000000',
            'denomination' => 'TEST ENTITIES',
            'type' => 'collectivite',
        ]);
        try {
            $this->entiteControler->_beforeAction();
            $this->entiteControler->doEditionAction();
        } catch (LastMessageException) {
        }

        $info = $this->getObjectInstancier()->getInstance(EntiteSQL::class)->getInfo(3);
        static::assertSame('TEST ENTITIES', $info['denomination']);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws JsonException
     */
    public function testDoExportConfigAction(): void
    {
        $id_e = 1;
        $this->setPostInfo([
            'id_e' => $id_e
        ]);
        $generator = new UriSafeTokenGenerator();
        $password = $generator->generateToken();
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->store(
            "export_configuration_password_$id_e",
            $password,
            60
        );

        $this->expectOutputRegex('/Content-type: application\/json;*/');
        $this->entiteControler->doExportConfigAction();
    }

    public function testDoEditionActionWhenModification(): void
    {
        $this->setPostInfo([
            'id_e' => 1,
            'siren' => '000000000',
            'denomination' => 'TEST ENTITIES',
            'type' => 'collectivite',
        ]);
        try {
            $this->entiteControler->_beforeAction();
            $this->entiteControler->doEditionAction();
        } catch (LastMessageException) {
        }

        $info = $this->getObjectInstancier()->getInstance(EntiteSQL::class)->getInfo(1);
        static::assertSame('TEST ENTITIES', $info['denomination']);
    }

    public function testExport(): void
    {
        ob_start();
        $this->entiteControler->exportAction();
        $result = ob_get_contents();
        ob_end_clean();
        static::assertMatchesRegularExpression('/1,000000000,Bourg-en-Bresse,collectivite,"0000-00-00 00:00:00",1,0/', $result);
    }

    /**
     * @throws Exception
     */
    public function testBulkDaemonScope(): void
    {
        $daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $id_daemon = $daemonSQL->insertDaemon(self::ID_E_SERVICE, 1, '', 1);
        $id_other_daemon = $daemonSQL->insertDaemon(self::ID_E_COL, 1, '', 1);
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job_service = $this->createJobForBulkAction($jobQueueSQL, self::ID_E_SERVICE, $id_daemon);
        $id_job_col = $this->createJobForBulkAction($jobQueueSQL, self::ID_E_COL, $id_other_daemon);

        $entiteControler = $this->getControlerInstance(EntiteControler::class);
        $this->authenticateNewUserWithPermission(
            [
                DroitService::getDroitFor(DroitService::DROIT_DAEMON, DroitType::LECTURE),
                DroitService::getDroitFor(DroitService::DROIT_DAEMON, DroitType::EDITION),
            ],
            self::ID_E_SERVICE
        );

        $entiteControler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        $this->setPostInfo([
            'id_e' => self::ID_E_SERVICE,
        ]);
        try {
            $entiteControler->daemonLockAllAction();
            static::fail('LastMessageException attendue');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('1 travail a été suspendu', $e->getMessage());
        }
        static::assertSame(JobStatus::SUSPENDED_BY_USER, $jobQueueSQL->getJob($id_job_service)->job_status);
        static::assertSame(JobStatus::WAITING, $jobQueueSQL->getJob($id_job_col)->job_status);

        $this->setPostInfo([
            'id_e' => self::ID_E_SERVICE,
        ]);
        try {
            $entiteControler->daemonUnlockAllAction();
            static::fail('LastMessageException attendue');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('1 travail a été repris', $e->getMessage());
        }
        static::assertSame(JobStatus::WAITING, $jobQueueSQL->getJob($id_job_service)->job_status);
    }

    /**
     * @throws Exception
     */
    public function testJobListBulkButtons(): void
    {
        $daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $id_daemon = $daemonSQL->insertDaemon(self::ID_E_SERVICE, 1, '', 1);
        $id_other_daemon = $daemonSQL->insertDaemon(self::ID_E_COL, 1, '', 1);
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job_service = $this->createJobForBulkAction($jobQueueSQL, self::ID_E_SERVICE, $id_daemon);
        $id_job_col = $this->createJobForBulkAction($jobQueueSQL, self::ID_E_COL, $id_other_daemon);

        $this->setGetInfo(['id_e' => self::ID_E_SERVICE]);
        $this->entiteControler->_beforeAction();
        ob_start();
        $this->entiteControler->jobAction();
        $output = ob_get_clean();

        static::assertStringContainsString(
            sprintf('<input type="hidden" name="id_e" value="%d">', self::ID_E_SERVICE),
            $output
        );
        static::assertStringContainsString('Suspendre les travaux en attente (1)', $output);
        static::assertStringContainsString('Reprendre les travaux suspendus (0)', $output);
        static::assertSame(
            [$id_job_service],
            array_map(
                static fn (Job $job) => (int)$job->id_job,
                $this->entiteControler->getViewParameter()['job_list']
            )
        );
    }

    /**
     * @throws Exception
     */
    public function testJobListRootEntity(): void
    {
        $daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        if ($daemonSQL->getGlobalDaemon() === null) {
            $this->getSQLQuery()->query(
                'INSERT INTO daemon (id_daemon, id_e, nb_workers, admin_emails, late_jobs_threshold)'
                . ' VALUES (?, NULL, 1, \'\', 1)',
                DaemonSQL::GLOBAL_DAEMON
            );
        }
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $id_job_root = $this->createJobForBulkAction(
            $jobQueueSQL,
            EntiteSQL::ID_E_ENTITE_RACINE,
            DaemonSQL::GLOBAL_DAEMON
        );

        $this->setGetInfo(['id_e' => EntiteSQL::ID_E_ENTITE_RACINE]);
        $this->entiteControler->_beforeAction();
        ob_start();
        $this->entiteControler->jobAction();
        ob_end_clean();

        static::assertContains(
            $id_job_root,
            array_map(
                static fn (Job $job) => (int)$job->id_job,
                $this->entiteControler->getViewParameter()['job_list']
            )
        );
    }

    /**
     * @throws Exception
     */
    private function createJobForBulkAction(JobQueueSQL $jobQueueSQL, int $id_e, int $id_daemon): int
    {
        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->id_e = $id_e;
        $job->etat_source = 'source';
        $job->etat_cible = 'cible';
        $job->id_daemon = $id_daemon;
        return (int)$jobQueueSQL->createJob($job);
    }
}
