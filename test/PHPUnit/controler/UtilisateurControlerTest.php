<?php

use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UserTokenService;
use Pastell\Service\Entite\EntityCreationService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class UtilisateurControlerTest extends ControlerTestCase
{
    private function getUtilisateurControler(): UtilisateurControler
    {
        return $this->getControlerInstance(UtilisateurControler::class);
    }

    /**
     * @throws LastErrorException
     */
    public function testDoEditionAction(): void
    {
        $password = 'D@iw3DDf41Nl$DXzMJL!Uc2Yo';
        $this->setPostInfo([
            'login' => 'foo',
            'password' => $password,
            'password2' => $password,
            'nom' => 'baz',
            'prenom' => 'buz',
            'email' => 'boz@byz.fr',
        ]);

        try {
            $this->getUtilisateurControler()->doEditionAction();
        } catch (LastMessageException) {
            /** Nothing to do */
        }

        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        static::assertEquals('boz@byz.fr', $utilisateurSQL->getInfo(3)['email']);
        // Password cannot be set with web controller
        static::assertFalse($utilisateurSQL->verifPassword(3, $password));
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function testModifPasswordAction()
    {
        $this->getObjectInstancier()->setInstance('password_min_entropy', 0);
        $this->getUtilisateurControler()->_beforeAction();
        $this->getUtilisateurControler()->modifPasswordAction();
        $this->expectOutputRegex('#<h1>Modification de votre mot de passe</h1#');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function testsuppressionAction(): void
    {
        $this->setGetInfo(['id_u_list' => [2]]);
        $this->getUtilisateurControler()->suppressionAction();
        $this->expectOutputRegex('~<title>Suppression d&#039;utilisateur\(s\) - Pastell</title>~');
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws NotFoundException
     * @throws ConflictException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function testSuppressionActionBatch(): void
    {
        $id_u3 = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('tester', 'tester@example.org', 'tester', 'tester');
        $this->setGetInfo(['id_u_list' => [2, $id_u3]]);
        $this->getUtilisateurControler()->suppressionAction();
        $this->expectOutputRegex('#Eric Pommateau#');
        $this->expectOutputRegex('#tester tester#');
    }

    public function testDoSuppressionAction(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        self::assertTrue($utilisateurSQL->exists(2));
        $this->setPostInfo(['id_u_list' => [2]]);
        try {
            $this->getUtilisateurControler()->doSuppressionAction();
        } catch (LastMessageException $e) {
            self::assertStringContainsString("L'utilisateur a été supprimé", $e->getMessage());
        }
        self::assertFalse($utilisateurSQL->exists(2));
        $logMessages = array_column($this->getLogRecords(), 'message');
        self::assertNotEmpty(preg_grep('#Suppression de l\'utilisateur eric \(id_u=2\)#u', $logMessages));
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testDoSuppressionActionBatch(): void
    {
        $id_u3 = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('tester', 'tester@example.org', 'tester', 'tester');

        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        self::assertTrue($utilisateurSQL->exists(2));
        self::assertTrue($utilisateurSQL->exists($id_u3));

        $this->setPostInfo(['id_u_list' => [2, $id_u3]]);
        try {
            $this->getUtilisateurControler()->doSuppressionAction();
        } catch (LastMessageException $e) {
            self::assertStringContainsString('2 utilisateurs ont été supprimés', $e->getMessage());
        }

        self::assertFalse($utilisateurSQL->exists(2));
        self::assertFalse($utilisateurSQL->exists($id_u3));

        $logMessages = array_column($this->getLogRecords(), 'message');
        self::assertNotEmpty(preg_grep('#Suppression de l\'utilisateur eric \(id_u=2\)#u', $logMessages));
        self::assertNotEmpty(preg_grep("#Suppression de l'utilisateur tester \(id_u=$id_u3\)#u", $logMessages));
    }

    public function testDoSuppressionActionWhenSuicide(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        self::assertTrue($utilisateurSQL->exists(2));
        $this->setPostInfo(['id_u_list' => [1]]);
        try {
            $this->getUtilisateurControler()->doSuppressionAction();
        } catch (Exception $e) {
            self::assertStringContainsString(
                "Impossible de vous supprimer vous-même",
                $e->getMessage()
            );
        }
        self::assertTrue($utilisateurSQL->exists(2));
    }

    public function testDisable(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        self::assertTrue($utilisateurSQL->isEnabled(2));
        $this->setPostInfo(['id_u' => 2]);
        try {
            $this->getUtilisateurControler()->disableAction();
        } catch (Exception $e) {
            /* Nothing to do*/
        }
        self::assertFalse($utilisateurSQL->isEnabled(2));
        self::assertMatchesRegularExpression(
            "#L'utilisateur eric a été désactivé#",
            $this->getLogRecords()[0]['message']
        );
    }

    public function testEnable(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $utilisateurSQL->disable(2);
        self::assertFalse($utilisateurSQL->isEnabled(2));
        $this->setPostInfo(['id_u' => 2]);
        try {
            $this->getUtilisateurControler()->enableAction();
        } catch (Exception $e) {
            /* Nothing to do*/
        }
        self::assertTrue($utilisateurSQL->isEnabled(2));
        self::assertMatchesRegularExpression(
            "#L'utilisateur eric a été activé#",
            $this->getLogRecords()[0]['message']
        );
    }

    public function testCantDisableMyself(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        self::assertTrue($utilisateurSQL->isEnabled(1));
        $this->setPostInfo(['id_u' => 1]);
        try {
            $this->getUtilisateurControler()->disableAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression('#Impossible de vous désactiver vous-même#', $e->getMessage());
            /* Nothing to do*/
        }
        self::assertTrue($utilisateurSQL->isEnabled(1));
    }

    public function testAddToken(): void
    {
        $this->setPostInfo([
            'name' => 'token',
        ]);
        try {
            $this->getUtilisateurControler()->doAddTokenAction();
        } catch (Exception $e) {
            static::assertMatchesRegularExpression(
                '/La valeur de votre jeton est <strong>(.*)<\/strong>/',
                $e->getMessage()
            );
        }
    }

    public function testDeleteToken(): void
    {
        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $token = $userTokenService->createToken(self::ID_U_ADMIN, 'token');
        $this->setPostInfo(['id' => 1]);
        try {
            $this->getUtilisateurControler()->deleteTokenAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression(
                "/Le jeton a été supprimé/",
                $e->getMessage()
            );
        }
        static::assertNull($userTokenService->getUserFromToken($token));
    }

    public function testRenewToken(): void
    {
        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $tokenBefore = $userTokenService->createToken(self::ID_U_ADMIN, 'token');
        $this->setPostInfo(['id' => 1]);
        try {
            $this->getUtilisateurControler()->renewTokenAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression(
                "/Le jeton a été renouvelé/",
                $e->getMessage()
            );
        }
        $allTokens = $userTokenService->getTokens(self::ID_U_ADMIN);
        self::assertCount(1, $allTokens);
        self::assertNotContains($tokenBefore, $allTokens);
        self::assertEquals('token', $allTokens[0]['name']);
    }

    private int $api_user_id;
    private int $admin_inf;
    private UserTokenService $userTokenService;

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    private function setUpRemoteToken(): void
    {
        $entiteCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $id_e1 = $entiteCreationService->create('entite1', '000000000');
        $id_e2 = $entiteCreationService->create('entite2', '000000000');
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $this->api_user_id = $userCreationService->createAPI('api_user', $id_e1, 'api', 'user');
        $this->admin_inf = $userCreationService->create('admin_inferieur', 'admin@gmail.com', 'admin', 'admin', $id_e2);
        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUtilisateur->addRole($this->admin_inf, 'admin', $id_e2);
        $this->userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRemoteAddTokenNoRights(): void
    {
        $this->setUpRemoteToken();
        $userController = $this->getUtilisateurControler();
        $userController->getAuthentification()->connexion('admin_inferieur', $this->admin_inf);
        $this->setGetInfo(['id_u' => $this->api_user_id]);
        try {
            $userController->doAddTokenAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression(
                "/Vous n'avez pas les droits nécessaires pour éxecuter cette action/",
                $e->getMessage()
            );
        }
        static::assertCount(0, $this->userTokenService->getTokens($this->api_user_id));
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRemoteAddToken(): void
    {
        $this->setUpRemoteToken();
        $this->setGetInfo(['id_u' => $this->api_user_id]);
        try {
            $this->getUtilisateurControler()->doAddTokenAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression(
                '/La valeur de votre jeton est/',
                $e->getMessage()
            );
        }
        static::assertCount(1, $this->userTokenService->getTokens($this->api_user_id));
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRemoteRenewTokenNoRights(): void
    {
        $this->setUpRemoteToken();
        $this->userTokenService->createToken($this->api_user_id, 'token');
        $userController = $this->getUtilisateurControler();
        $userController->getAuthentification()->connexion('admin_inferieur', $this->admin_inf);
        $this->setPostInfo(['id' => 1]);
        try {
            $userController->renewTokenAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression(
                "/Vous n'avez pas les droits nécessaires pour éxecuter cette action/",
                $e->getMessage()
            );
        }
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRemoteRenewToken(): void
    {
        $this->setUpRemoteToken();
        $this->userTokenService->createToken($this->api_user_id, 'token');
        $this->setPostInfo(['id' => 1]);
        try {
            $this->getUtilisateurControler()->renewTokenAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression(
                '/Le jeton a été renouvelé/',
                $e->getMessage()
            );
        }
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRemoteDeleteTokenNoRights(): void
    {
        $this->setUpRemoteToken();
        $this->userTokenService->createToken($this->api_user_id, 'token');
        $userController = $this->getUtilisateurControler();
        $userController->getAuthentification()->connexion('admin_inferieur', $this->admin_inf);
        $this->setPostInfo(['id' => 1]);
        try {
            $userController->deleteTokenAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression(
                "/Vous n'avez pas les droits nécessaires pour éxecuter cette action/",
                $e->getMessage()
            );
        }
        static::assertCount(1, $this->userTokenService->getTokens($this->api_user_id));
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRemoteDeleteToken(): void
    {
        $this->setUpRemoteToken();
        $token = $this->userTokenService->createToken($this->api_user_id, 'token');
        $this->setPostInfo(['id' => 1]);
        try {
            $this->getUtilisateurControler()->deleteTokenAction();
        } catch (Exception $e) {
            self::assertMatchesRegularExpression(
                '/Le jeton a été supprimé/',
                $e->getMessage()
            );
        }
        static::assertNull($this->userTokenService->getUserFromToken($token));
    }

    public function testDeleteRole(): void
    {
        $this->setPostInfo([
            'id_u' => 2,
            'role' => 'aucun droit',
            'id_e' => 1,
        ]);
        try {
            $this->getUtilisateurControler()->supprimeRoleAction();
        } catch (LastMessageException $e) {
            self::assertMatchesRegularExpression(
                '/Le rôle <i>aucun droit<\/i> a été retiré/',
                $e->getMessage()
            );
        }
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws ConflictException
     * @throws LastMessageException
     */
    public function testAccesPageCreationFail(): void
    {
        $this->setGetInfo([
            'id_e' => 1,
        ]);
        $controller = $this->getUtilisateurControler();
        $user = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('tester', 'tester@example.org', 'tester', 'tester');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->edit('entiteLectureEdition', 'Droit utilisateur');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->addDroit('entiteLectureEdition', DroitService::getDroitFor(DroitService::DROIT_UTILISATEUR, DroitType::EDITION));
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)
            ->addRole('3', 'entiteLectureEdition', '1');

        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('tester', 3);

        $this->expectException(LastErrorException::class);
        $this->expectExceptionMessage("Vous n'avez pas les droits nécessaires (1:utilisateur:creation) pour accéder à cette page");
        $controller->editionAction();
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function testAccesPageCreation(): void
    {
        \ob_start();
        $this->getUtilisateurControler()->editionAction();
        \ob_get_clean();
        $pageTitle = $this->getUtilisateurControler()->getViewParameterByKey('page_title');
        static::assertSame($pageTitle, 'Nouvel utilisateur ');
    }

    public function testDoEditionActionNoPassword()
    {
        $this->setPostInfo([
            'login' => 'foo',
            'password' => 'D@iw3DDf41Nl$DXzMJL!Uc2Yo',
            'nom' => 'baz',
            'prenom' => 'buz',
            'email' => 'boz@byz.fr'
        ]);
        try {
            $this->getUtilisateurControler()->doEditionAction();
        } catch (LastMessageException $e) {
            /** Nothing to do */
        }
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $userCreated = $utilisateurSQL->getInfo(3);

        $this->setPostInfo([
            'id_u' => 3,
            'login' => 'foo',
            'nom' => 'baz',
            'prenom' => 'buz',
            'email' => 'boz@byz.fr'
        ]);
        try {
            $this->getUtilisateurControler()->doEditionAction();
        } catch (LastMessageException $e) {
            /** Nothing to do */
        }
        $userUpdated = $utilisateurSQL->getInfo(3);
        self::assertSame($userCreated['password'], $userUpdated['password']);
    }

    /**
     * @throws UnrecoverableException
     * @throws LastErrorException
     * @throws ConflictException|LastMessageException
     */
    public function testNotificationAjoutActionBySelf(): void
    {
        $utilisateurControler = $this->getUtilisateurControler();
        $id_u = $this->authenticateNewUserWithPermission(
            [
                DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
                DroitService::getDroitFor('actes-generique', DroitType::EDITION),
                DroitService::getDroitFor('actes-generique', DroitType::LECTURE),
            ],
            1
        );

        $this->setPostInfo([
            'id_u' => $id_u,
            'id_e' => 1,
            'type' => 'actes-generique',
            'source' => 'moi',
        ]);
        try {
            $utilisateurControler->notificationAjoutAction();
        } catch (LastMessageException $e) {
            static::assertSame(3, $id_u);
            static::assertStringContainsString(
                'Utilisateur/notification?id_u=3&id_e=1&type=actes-generique&source=moi',
                $e->getMessage()
            );
        }

        $this->setGetInfo(['id_u' => $id_u, 'source' => 'moi']);
        $utilisateurControler->_beforeAction();
    }

    /**
     * @throws LastMessageException
     */
    public function testNotificationAjoutActionByOther(): void
    {
        $this->setPostInfo([
            'id_u' => 2,
            'id_e' => 1,
            'type' => 'actes-generique',
        ]);

        $this->expectException(LastErrorException::class);
        $this->getUtilisateurControler()->notificationAjoutAction();
        static::assertLastMessage('Vous ne pouvez pas ajouter de notifications à un autre utilisateur',);
    }

    public function testNotificationAjoutActionWithoutType(): void
    {
        $this->setPostInfo([
            'id_u' => 1,
            'id_e' => 1,
            'type' => '',
        ]);
        try {
            $this->getUtilisateurControler()->notificationAjoutAction();
        } catch (LastErrorException $e) {
            static::assertStringContainsString("Vous n'avez sélectionné aucun type de dossier", $e->getMessage());
        }
    }

    /**
     * @throws LastErrorException
     */
    public function testNotificationSuppressionActionBySelf(): void
    {
        $utilisateurControler = $this->getUtilisateurControler();
        $utilisateurControler->getNotification()->add(
            1,
            1,
            'actes-generique',
            Notification::ALL_TYPE,
            false
        );

        $this->setPostInfo([
            'id_u' => 1,
            'id_n' => 1,
            'id_e' => 1,
            'type' => 'actes-generique',
        ]);

        try {
            $this->getUtilisateurControler()->notificationSuppressionAction();
        } catch (LastMessageException $e) {
            static::assertStringContainsString(
                'La notification a été supprimée',
                $e->getMessage()
            );
        }
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function testNotificationModifBySelf(): void
    {
        $utilisateurControler = $this->getUtilisateurControler();
        $utilisateurControler->getNotification()->add(
            1,
            1,
            'actes-generique',
            Notification::ALL_TYPE,
            false
        );

        $this->setPostInfo([
            'id_u' => 1,
            'id_e' => 1,
            'type' => 'actes-generique',
        ]);

        \ob_start();
        $this->getUtilisateurControler()->notificationAction();
        \ob_get_clean();
        static::assertSame(
            'UtilisateurNotification',
            $utilisateurControler->getViewParameterByKey('template_milieu')
        );
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function testNotificationModifByOther(): void
    {
        $utilisateurControler = $this->getUtilisateurControler();
        $utilisateurControler->getNotification()->add(
            2,
            1,
            'actes-generique',
            Notification::ALL_TYPE,
            false
        );

        $this->setPostInfo([
            'id_u' => 2,
            'id_e' => 1,
            'type' => 'actes-generique',
        ]);

        $this->expectException(LastErrorException::class);
        $this->getUtilisateurControler()->notificationAction();
        static::assertLastMessage("Vous ne pouvez pas modifer les notifications d'un autre utilisateur",);
    }

    /**
     * @throws UnrecoverableException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function testDoNotificationModifBySelf(): void
    {
        $utilisateurControler = $this->getUtilisateurControler();
        $utilisateurControler->getNotification()->add(
            1,
            1,
            'actes-generique',
            'creation',
            false
        );
        \ob_start();
        $utilisateurControler->moiAction();
        \ob_get_clean();
        $this->setPostInfo([
            'id_e' => 1,
            'type' => 'actes-generique',
            'id_u' => 1,
            'modification' => [
                'name' => 'modification',
                'type' => 'checkbox',
                'value' => 'on',
                'checked' => true,
            ]
        ]);

        try {
            $this->getUtilisateurControler()->doNotificationEditAction();
        } catch (LastMessageException $e) {
            static::assertStringContainsString(
                'Les notifications ont été modifiées',
                $e->getMessage()
            );
        }

        \ob_start();
        $utilisateurControler->moiAction();
        \ob_get_clean();
        static::assertEquals(
            [
                '1-actes-generique' => [
                'id_n' => 2,
                'id_u' => 1,
                'id_e' => 1,
                'type' => 'actes-generique',
                'action' => [
                  0 => 'En cours de rédaction'
                ],
                'daily_digest' => 0,
                'denomination' => 'Bourg-en-Bresse'
                ]
            ],
            $utilisateurControler->getViewParameterByKey('notification_list')
        );
    }

    public function testBeforeActionWithInvalidIdU(): void
    {
        $this->setGetInfo(['id_u' => 99999]);
        $this->expectException(LastErrorException::class);
        $this->expectExceptionMessage("L'utilisateur n'existe pas");
        $this->getUtilisateurControler()->_beforeAction();
    }
}
