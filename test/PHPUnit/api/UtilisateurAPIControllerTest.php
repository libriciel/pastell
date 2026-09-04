<?php

declare(strict_types=1);

use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UserTokenService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class UtilisateurAPIControllerTest extends PastellTestCase
{
    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    private function createApiUser(): int
    {
        return $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->createAPI('api-bot', 0, 'Bot', 'Api');
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    private function createUnprivilegedUser(): int
    {
        return $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('unprivileged', 'unprivileged@example.org', 'Unprivileged', 'User');
    }

    private function createTokenForApiUser(int $apiUser): array
    {
        return $this->getInternalAPI()->post("/utilisateur/$apiUser/token", ['name' => 'token-for-other']);
    }

    public function testCreate(): void
    {
        $info = $this->getInternalAPI()->post(
            '/utilisateur',
            [
                'email' => 'foo@bar.baz',
                'login' => 'foo',
                'password' => 'D@iw3DDf41Nl$DXzMJL!Uc2Yo',
                'nom' => 'foo',
                'prenom' => 'bar',
            ]
        );
        static::assertSame(
            [
                'id_u' => '3',
                'login' => 'foo',
                'nom' => 'foo',
                'prenom' => 'bar',
                'email' => 'foo@bar.baz',
                'certificat' => '',
                'id_e' => '0',
                'active' => true,
            ],
            $info
        );
    }

    public function testCreateWithoutNom(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');
        $this->getInternalAPI()->post(
            '/utilisateur',
            [
                'email' => 'foo@bar.baz',
                'login' => 'foo',
                'password' => 'bar',
                'prenom' => 'bar',
            ]
        );
    }

    public function testCreateWithoutPrenom(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Le prénom est obligatoire');
        $this->getInternalAPI()->post(
            '/utilisateur',
            [
                'email' => 'foo@bar.baz',
                'login' => 'foo',
                'password' => 'bar',
                'nom' => 'bar',
            ]
        );
    }

    public function testCreateWithoutLogin(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Le login est obligatoire');
        $this->getInternalAPI()->post(
            '/utilisateur',
            [
                'email' => 'foo@bar.baz',
                'prenom' => 'foo',
                'password' => 'bar',
                'nom' => 'bar',
            ]
        );
    }

    public function testCreateWithoutEmail(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Votre adresse email ne semble pas valide');
        $this->getInternalAPI()->post(
            '/utilisateur',
            [
                'login' => 'foo@bar.baz',
                'prenom' => 'foo',
                'password' => 'bar',
                'nom' => 'bar',
            ]
        );
    }

    public function testCreateSameUser()
    {
        $info =  [
            'login' => 'foo',
            'prenom' => 'foo',
            'password' => 'D@iw3DDf41Nl$DXzMJL!Uc2Yo',
            'nom' => 'bar',
            'email' => 'foo@bar.baz',
        ];
        $info = $this->getInternalAPI()->post("utilisateur", $info);
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage("Un utilisateur avec le même login existe déjà.");
        $this->getInternalAPI()->patch("utilisateur/{$info['id_u']}", ['login' => 'admin']);
    }

    public function testSetCertificateKO(): void
    {
        $fileUploader = new FileUploaderMock();
        $fileUploader->setFiles(['certificat' => 'toto']);
        $this->getInternalAPI()->setFileUploader($fileUploader);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Le certificat ne semble pas être valide');
        $this->getInternalAPI()->patch('/utilisateur/1');
    }

    public function testSetCertificate(): void
    {
        $cert_content = file_get_contents(__DIR__ . '/../fixtures/autorite-cert.pem');
        $fileUploader = new FileUploaderMock();
        $fileUploader->setFiles(['certificat' => $cert_content]);
        $this->getInternalAPI()->setFileUploader($fileUploader);

        $info = $this->getInternalAPI()->patch('/utilisateur/1');
        static::assertSame($cert_content, $info['certificat']);
    }

    public function testList(): void
    {
        $info = $this->getInternalAPI()->get('/utilisateur');
        static::assertSame(
            [
                'id_u' => '1',
                'login' => 'admin',
                'email' => 'eric@sigmalis.com',
                'active' => true,
            ],
            $info[0]
        );
    }

    public function testListV1()
    {
        $this->expectOutputRegex('#admin#');
        $this->getV1('list-utilisateur.php');
    }

    public function testDetail(): void
    {
        $info = $this->getInternalAPI()->get('/utilisateur/1');
        static::assertSame(
            [
                'id_u' => '1',
                'login' => 'admin',
                'nom' => 'Pommateau',
                'prenom' => 'Eric',
                'email' => 'eric@sigmalis.com',
                'certificat' => '',
                'id_e' => '0',
                'active' => true,
            ],
            $info
        );
    }

    public function testDetailNotExist(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("L'utilisateur n'existe pas : {id_u=42}");
        $this->getInternalAPI()->get('/utilisateur/42');
    }

    public function testDetailV1()
    {
        $this->expectOutputRegex("#Pommateau#");
        $this->getV1("detail-utilisateur.php?id_u=1");
    }

    public function testEdit(): void
    {
        $info = $this->getInternalAPI()->patch('/utilisateur/1', ['login' => 'toto']);
        static::assertSame(
            [
                'id_u' => '1',
                'login' => 'toto',
                'nom' => 'Pommateau',
                'prenom' => 'Eric',
                'email' => 'eric@sigmalis.com',
                'certificat' => '',
                'id_e' => '0',
                'active' => true,
                'result' => 'ok',
            ],
            $info
        );
    }

    public function testEditEntiteDeBase(): void
    {
        $info = $this->getInternalAPI()->patch('/utilisateur/1', ['id_e' => '2']);
        static::assertSame('2', $info['id_e']);
        $info = $this->getInternalAPI()->patch('/utilisateur/1', ['id_e' => '0']);
        static::assertSame('0', $info['id_e']);
        $info = $this->getInternalAPI()->patch('/utilisateur/1', ['id_e' => '2']);
        static::assertSame('2', $info['id_e']);
        $info = $this->getInternalAPI()->patch('/utilisateur/1', ['login' => 'toto']);
        static::assertSame('2', $info['id_e']);
    }

    public function testEditWithCreate(): void
    {
        $info = $this->getInternalAPI()->patch(
            '/utilisateur',
            [
                'email' => 'foo@bar.baz',
                'login' => 'foo',
                'password' => 'D@iw3DDf41Nl$DXzMJL!Uc2Yo',
                'nom' => 'foo',
                'prenom' => 'bar',
                'create' => true,
            ]
        );
        static::assertSame(
            [
                'id_u' => '3',
                'login' => 'foo',
                'nom' => 'foo',
                'prenom' => 'bar',
                'email' => 'foo@bar.baz',
                'certificat' => '',
                'id_e' => '0',
                'active' => true,
            ],
            $info
        );
    }

    public function testDelete(): void
    {
        $this->getInternalAPI()->delete('/utilisateur/1');
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("L'utilisateur n'existe pas : {id_u=1}");
        $this->getInternalAPI()->get('/utilisateur/1');
    }

    public function testPostActivateDeactivate(): void
    {
        $utilisateurActivated = $this->getInternalAPI()->get('utilisateur/2');
        $utilisateurDeactivated = $this->getInternalAPI()->post('/utilisateur/2/deactivate');
        $this->assertNotEquals($utilisateurActivated, $utilisateurDeactivated);

        $utilisateurReactivated = $this->getInternalAPI()->post('/utilisateur/2/activate');
        $this->assertEquals($utilisateurActivated, $utilisateurReactivated);
    }

    public function testPostDeactivateMyselfFail(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Vous ne pouvez pas désactiver votre compte utilisateur.');
        $this->getInternalAPI()->post('/utilisateur/1/deactivate');
    }

    public function testPostDeactivateFailDroit(): void
    {
        $user = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('tester', 'tester@example.org', 'tester', 'tester');
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=0, droit=utilisateur:edition,id_u=3');
        $this->getInternalAPIAsUser($user)->post('/utilisateur/1/deactivate');
    }

    public function testCreateUserFail(): void
    {
        $user = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('tester', 'tester@example.org', 'tester', 'tester');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->edit('utilisateurLectureEdition', 'Droit utilisateur');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->addDroit('utilisateurLectureEdition', DroitService::getDroitFor(DroitService::DROIT_UTILISATEUR, DroitType::EDITION));
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)
            ->addRole('3', 'utilisateurLectureEdition', '1');

        $userInfo = [
            'id_e' => '1',
            'login' => 'foo',
            'password' => 'D@iw3DDf41Nl$DXzMJL!Uc2Yo',
            'password2' => 'D@iw3DDf41Nl$DXzMJL!Uc2Yo',
            'nom' => 'baz',
            'prenom' => 'buz',
            'email' => 'boz@byz.fr'
        ];

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=1, droit=utilisateur:creation,id_u=3');
        $this->getInternalAPIAsUser($user)->post('/utilisateur', $userInfo);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    private function createUserWithEditionPermissionsOn(string $entityId): int
    {
        $user = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('tester', 'tester@example.invalid', 'tester', 'tester');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->edit('utilisateurEdition', 'User');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->addDroit(
                'utilisateurEdition',
                DroitService::getDroitFor(DroitService::DROIT_UTILISATEUR, DroitType::EDITION)
            );
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)
            ->addRole((string)$user, 'utilisateurEdition', $entityId);
        return $user;
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testPatchUserOfAnotherEntityFail(): void
    {
        $user = $this->createUserWithEditionPermissionsOn('1');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("Acces interdit id_e=0, droit=utilisateur:edition,id_u=$user");
        $this->getInternalAPIAsUser($user)->patch(
            '/utilisateur/1',
            ['id_e' => '1', 'prenom' => 'test2']
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testPatchMoveUserToAnotherEntityFail(): void
    {
        // The entity 2 is a child of the entity 1, so the move is tested the other way around:
        // a right on the child entity must not allow moving a user to the parent entity.
        $user = $this->createUserWithEditionPermissionsOn('2');
        $victim = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('victim', 'victim@example.invalid', 'Victim', 'User', 2);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("Acces interdit id_e=1, droit=utilisateur:edition,id_u=$user");
        $this->getInternalAPIAsUser($user)->patch("/utilisateur/$victim", ['id_e' => '1']);
    }

    public function testPasswordPreservedPatchWithNoPassword(): void
    {
        $info =  [
            'login' => 'foo',
            'prenom' => 'foo',
            'password' => 'D@iw3DDf41Nl$DXzMJL!Uc2Yo',
            'nom' => 'bar',
            'email' => 'foo@bar.baz',
        ];
        $info = $this->getInternalAPI()->post("utilisateur", $info);
        $userCreated = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->getInfo($info['id_u']);
        $info2 = $this->getInternalAPI()->patch("utilisateur/3", ['nom' => 'faa']);
        $userUpdated = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->getInfo($info2['id_u']);
        self::assertSame($userCreated['password'], $userUpdated['password']);
    }

    public function testCreateToken(): void
    {
        $this->getInternalAPI()->post('utilisateur/token', ['name' => 'test']);
        self::assertSame(
            [
                [
                    'id' => '1',
                    'id_u' => '1',
                    'name' => 'test',
                    'created_at' => date('Y-m-d H:i:s'),
                    'expired_at' => null,
                    'is_expired' => false,
                ]
            ],
            $this->getInternalAPI()->get('utilisateur/token')
        );
    }

    public function testCreateTokenWithoutName(): void
    {
        $this->expectExceptionMessage('Le nom du token est obligatoire');
        $this->getInternalAPI()->post('utilisateur/token');
    }

    public function testCreateTokenWithWrongDate(): void
    {
        $this->expectExceptionMessage("La date d'expiration est fausse, format attendu : 2020-03-31");
        $this->getInternalAPI()->post('utilisateur/token', ['name' => 'test', 'expiration' => '2024']);
    }

    public function testCreateTokenWithPriorDate(): void
    {
        $this->expectExceptionMessage("La date d'expiration est antérieure à la date d'aujourd'hui");
        $this->getInternalAPI()->post('utilisateur/token', ['name' => 'test', 'expiration' => '2022-04-23']);
    }

    public function testDeleteToken(): void
    {
        $this->getInternalAPI()->post('utilisateur/token', ['name' => 'test']);
        $token = $this->getInternalAPI()->get('utilisateur/token');
        $this->getInternalAPI()->delete('utilisateur/token/1');
        self::assertNotEquals($token[0], $this->getInternalAPI()->get('utilisateur/token'));
        $this->expectOutputRegex('/HTTP\/1.1 204 No Content/');
    }

    public function testDeleteTokenFail(): void
    {
        self::expectExceptionMessage('Impossible de supprimer ce jeton');
        $this->getInternalAPI()->delete('utilisateur/token/1');
    }

    public function testRenewToken(): void
    {
        $this->getInternalAPI()->post('utilisateur/token', ['name' => 'test']);
        $token = $this->getInternalAPI()->post('utilisateur/token/1/renew');
        self::assertSame(43, strlen($token['token']));
    }

    public function testRenewTokenFail(): void
    {
        self::expectExceptionMessage('Impossible de renouveler ce jeton');
        $this->getInternalAPI()->post('utilisateur/token/1/renew');
    }

    public function testCreateApiUser(): void
    {
        $info = $this->getInternalAPI()->post(
            '/utilisateur',
            [
                'is_api' => true,
                'login' => 'api-bot',
                'nom' => 'Bot',
                'prenom' => 'Api',
            ]
        );
        static::assertSame(
            [
                'id_u' => '3',
                'login' => 'api-bot',
                'nom' => 'Bot',
                'prenom' => 'Api',
                'email' => '',
                'certificat' => '',
                'id_e' => '0',
                'active' => true,
            ],
            $info
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testCreateTokenForOther(): void
    {
        $apiUser = $this->createApiUser();
        $token = $this->createTokenForApiUser($apiUser);

        static::assertSame('token-for-other', $token['name']);
        static::assertSame((string)$apiUser, $token['id_u']);
        static::assertSame(43, strlen($token['token']));
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testCreateTokenForWebUser(): void
    {
        $classicUser = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('other', 'other@example.org', 'Other', 'User');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Les jetons ne peuvent être créés que pour des utilisateurs de type API');
        $this->getInternalAPI()->post("/utilisateur/$classicUser/token", ['name' => 'token-for-other']);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testCreateTokenForOtherForbidden(): void
    {
        $apiUser = $this->createApiUser();
        $unprivilegedUser = $this->createUnprivilegedUser();

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("Acces interdit id_e=0, droit=utilisateur:edition,id_u=$unprivilegedUser");
        $this->getInternalAPIAsUser($unprivilegedUser)->post("/utilisateur/$apiUser/token", ['name' => 'token-for-other']);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRenewTokenForOther(): void
    {
        $apiUser = $this->createApiUser();
        $token = $this->createTokenForApiUser($apiUser);
        $tokenId = $token['id'];

        $renewed = $this->getInternalAPI()->post("/utilisateur/$apiUser/token/$tokenId/renew");

        static::assertSame(43, strlen($renewed['token']));
        static::assertNotSame($token['token'], $renewed['token']);
        static::assertSame((string)$apiUser, $renewed['id_u']);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRenewTokenForOtherForbidden(): void
    {
        $apiUser = $this->createApiUser();
        $unprivilegedUser = $this->createUnprivilegedUser();
        $tokenId = $this->createTokenForApiUser($apiUser)['id'];

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("Acces interdit id_e=0, droit=utilisateur:edition,id_u=$unprivilegedUser");
        $this->getInternalAPIAsUser($unprivilegedUser)->post("/utilisateur/$apiUser/token/$tokenId/renew");
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRenewTokenForWebUser(): void
    {
        $classicUser = $this->createUnprivilegedUser();
        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $token = $userTokenService->createToken($classicUser, 'token-for-other');
        $tokenId = $userTokenService->getTokenInfo($token)['id'];

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Les jetons ne peuvent être créés que pour des utilisateurs de type API');
        $this->getInternalAPI()->post("/utilisateur/$classicUser/token/$tokenId/renew");
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testRenewTokenForOtherWrongToken(): void
    {
        $apiUser = $this->createApiUser();
        $adminTokenId = $this->getInternalAPI()->post('utilisateur/token', ['name' => 'admin-token'])['id'];

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Impossible de renouveler ce jeton');
        $this->getInternalAPI()->post("/utilisateur/$apiUser/token/$adminTokenId/renew");
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testDeleteTokenForOther(): void
    {
        $apiUser = $this->createApiUser();
        $tokenId = $this->createTokenForApiUser($apiUser)['id'];

        $this->getInternalAPI()->delete("/utilisateur/$apiUser/token/$tokenId");
        $this->expectOutputRegex('/HTTP\/1.1 204 No Content/');
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testDeleteTokenForWebUser(): void
    {
        $classicUser = $this->createUnprivilegedUser();
        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $token = $userTokenService->createToken($classicUser, 'token-for-other');
        $tokenId = $userTokenService->getTokenInfo($token)['id'];

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Les jetons ne peuvent être supprimés que pour des utilisateurs de type API');
        $this->getInternalAPI()->delete("/utilisateur/$classicUser/token/$tokenId");
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testDeleteTokenForOtherForbidden(): void
    {
        $apiUser = $this->createApiUser();
        $unprivilegedUser = $this->createUnprivilegedUser();
        $tokenId = $this->createTokenForApiUser($apiUser)['id'];

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("Acces interdit id_e=0, droit=utilisateur:edition,id_u=$unprivilegedUser");
        $this->getInternalAPIAsUser($unprivilegedUser)->delete("/utilisateur/$apiUser/token/$tokenId");
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testDeleteTokenForOtherWrongToken(): void
    {
        $apiUser = $this->createApiUser();
        $adminTokenId = $this->getInternalAPI()->post('utilisateur/token', ['name' => 'admin-token'])['id'];

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Impossible de supprimer ce jeton');
        $this->getInternalAPI()->delete("/utilisateur/$apiUser/token/$adminTokenId");
    }
}
