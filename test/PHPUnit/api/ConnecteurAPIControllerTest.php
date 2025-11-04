<?php

use Pastell\Service\Connecteur\ConnecteurActionService;
use Pastell\Service\Utilisateur\UserCreationService;

class ConnecteurAPIControllerTest extends PastellTestCase
{
    public function testListAction(): void
    {
        $list = $this->getInternalAPI()->get('/entite/0/connecteur');
        static::assertSame([
            'id_ce' => '10',
            'id_e' => '0',
            'libelle' => 'Horodateur interne par défaut',
            'id_connecteur' => 'horodateur-interne',
            'type' => 'horodateur',
            'frequence_en_minute' => '1',
            'id_verrou' => '',
            'global' => 1,
        ], $list[0]);
    }

    public function testGetBadEntiteConnecteur()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le connecteur 12 n'appartient pas à l'entité 2");
        $this->getInternalAPI()->get("/entite/2/connecteur/12");
    }

    public function testGetBadEntite()
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("L'entité 42 n'existe pas");
        $this->getInternalAPI()->get("/entite/42/connecteur");
    }

    public function testCreate(): void
    {
        $info = $this->getInternalAPI()->post(
            '/entite/1/connecteur',
            ['libelle' => 'Connecteur de test', 'id_connecteur' => 'test']
        );
        static::assertSame(
            [
                'id_ce' => '14',
                'id_e' => '1',
                'libelle' => 'Connecteur de test',
                'id_connecteur' => 'test',
                'type' => 'test',
                'frequence_en_minute' => '1',
                'id_verrou' => '',
                'global' => 0,
                'data' => [],
                'action-possible' => [
                    'ok',
                    'fail',
                    'une_action_auto',
                    'une_action_long_auto',
                    'une_action_auto_fail',
                ],
            ],
            $info
        );

        $connecteurActionService = $this->getObjectInstancier()->getInstance(ConnecteurActionService::class);
        $connecteur_action_message = $connecteurActionService->getByIdCe($info['id_ce'])[0]['message'];
        static::assertSame('Le connecteur test « Connecteur de test » a été créé', $connecteur_action_message);
    }

    public function testCreateWithoutLibelle()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le libellé est obligatoire.");
        $this->getInternalAPI()->post(
            "/entite/1/connecteur",
            ['libelle' => '', 'id_connecteur' => 'test']
        );
    }

    public function testCreateGlobal(): void
    {
        $info = $this->getInternalAPI()->post(
            '/entite/0/connecteur',
            ['libelle' => 'Test', 'id_connecteur' => 'test']
        );
        static::assertSame(
            [
                'id_ce' => '14',
                'id_e' => '0',
                'libelle' => 'Test',
                'id_connecteur' => 'test',
                'type' => 'test',
                'frequence_en_minute' => '1',
                'id_verrou' => '',
                'global' => 1,
                'data' => [],
                'action-possible' => [
                    'ok',
                    'fail',
                ],
            ],
            $info
        );
    }

    public function testCreateNotExist()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Aucun connecteur du type « foo »");
        $this->getInternalAPI()->post(
            "/entite/1/connecteur",
            ['libelle' => 'Connecteur de test', 'id_connecteur' => 'foo']
        );
    }

    public function testDelete(): void
    {
        $info = $this->getInternalAPI()->delete('/entite/1/connecteur/12');
        static::assertSame(['result' => 'ok'], $info);
    }

    public function testDeleteNotExist()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("Ce connecteur n'existe pas.");
        $this->getInternalAPI()->delete("/entite/1/connecteur/42");
    }

    public function testDeleteUsed()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("Ce connecteur est utilisé par des flux :  actes-generique");
        $this->getInternalAPI()->delete("/entite/1/connecteur/1");
    }

    public function testEdit(): void
    {
        $info = $this->getInternalAPI()->post(
            '/entite/1/connecteur',
            ['libelle' => 'Connecteur de test', 'id_connecteur' => 'test']
        );
        static::assertSame('Connecteur de test', $info['libelle']);
        $id_ce = $info['id_ce'];
        $info = $this->getInternalAPI()->patch("/entite/1/connecteur/$id_ce", ['libelle' => 'bar']);
        static::assertSame(
            [
                'id_ce' => '14',
                'id_e' => '1',
                'libelle' => 'bar',
                'id_connecteur' => 'test',
                'type' => 'test',
                'frequence_en_minute' => '1',
                'id_verrou' => '',
                'global' => 0,
                'data' => [],
                'action-possible' => [
                    'ok',
                    'fail',
                    'une_action_auto',
                    'une_action_long_auto',
                    'une_action_auto_fail',
                ],
            ],
            $info
        );

        $connecteurActionService = $this->getObjectInstancier()->getInstance(ConnecteurActionService::class);
        $connecteur_action_message = $connecteurActionService->getByIdCe($id_ce)[0]['message'];
        static::assertSame('Le libellé a été modifié en « bar »', $connecteur_action_message);
    }

    public function testEditNotExist()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("Ce connecteur n'existe pas.");
        $this->getInternalAPI()->patch("/entite/1/connecteur/42", ['libelle' => 'bar']);
    }

    public function testEditNotLibelle()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("Le libellé est obligatoire.");
        $this->getInternalAPI()->patch("/entite/1/connecteur/12", ['libelle' => '']);
    }

    public function testEditContentAction(): void
    {
        $info = $this->getInternalAPI()->patch('/entite/1/connecteur/12/content', ['champs1' => 'foo']);
        static::assertSame(
            [
                'id_ce' => '12',
                'id_e' => '1',
                'libelle' => 'connecteur non associé',
                'id_connecteur' => 'test',
                'type' => 'test',
                'frequence_en_minute' => '1',
                'id_verrou' => '',
                'global' => 0,
                'data' => [
                    'champs1' => 'foo',
                ],
                'action-possible' => [
                    'ok',
                    'fail',
                    'une_action_auto',
                    'une_action_long_auto',
                    'une_action_auto_fail',
                ],
                'result' => 'ok',
            ],
            $info
        );
        $id_ce = $info['id_ce'];
        $connecteurActionService = $this->getObjectInstancier()->getInstance(ConnecteurActionService::class);
        $connecteur_action_message = $connecteurActionService->getByIdCe($id_ce)[0]['message'];
        static::assertSame("Modification du connecteur via l'API", $connecteur_action_message);
    }

    public function testEditContentOnChangeAction(): void
    {
        $info = $this->getInternalAPI()->patch('/entite/1/connecteur/12/content', ['champs3' => 'foo']);
        static::assertSame('foo', $info['data']['champs4']);
    }

    public function testPostFile()
    {
        $result = $this->getInternalAPI()->post(
            "/entite/1/connecteur/12/file/champs5",
            [
                'file_name' => 'test.txt',
                'file_content' => 'test...'
            ]
        );
        $this->assertEquals("test.txt", $result['data']['champs5'][0]);
        $this->expectOutputRegex("#test...#");
        $this->expectException("Exception");
        $this->expectExceptionMessage("Exit called with code 0");
        $this->getInternalAPI()->get("/entite/1/connecteur/12/file/champs5");
    }

    public function testAction(): void
    {
        $result = $this->getInternalAPI()->post('/entite/1/connecteur/12/action/ok');
        static::assertSame(['result' => true, 'last_message' => 'OK !'], $result);
    }

    public function testActionBadConnecteurID()
    {
        $internalAPI = $this->getInternalAPI();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Ce connecteur n'existe pas.");
        $internalAPI->post("/entite/1/connecteur/foo/action/ok");
    }

    public function testActionForbiddenAction()
    {
        $internalAPI = $this->getInternalAPI();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "L'action « not_possible »  n'est pas permise : internal-action n'est pas vérifiée"
        );
        $internalAPI->post("/entite/1/connecteur/12/action/not_possible");
    }

    public function testActionBadActionName()
    {
        $internalAPI = $this->getInternalAPI();
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("L'action foo n'existe pas");
        $internalAPI->post("/entite/1/connecteur/12/action/foo");
    }

    /**
     * @throws Exception
     */
    public function testGetConnecteur(): void
    {
        $id_ce = $this->createConnector('iParapheur', 'Connecteur i-Parapheur')['id_ce'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($id_ce);

        $donneesFormulaire->setTabData([
            'iparapheur_wsdl' => 'https://iparapheur.test',
            'iparapheur_login' => 'admin@pastell',
            'iparapheur_password' => 'Xoo7kiey',
            'iparapheur_type' => 'PES',
            'not_existing_element' => "I don't exist",
        ]);
        $info = $this->getInternalAPI()->get("/entite/1/connecteur/$id_ce");
        static::assertSame([
            'iparapheur_nb_jour_max' => '30',
            'iparapheur_wsdl' => 'https://iparapheur.test',
            'iparapheur_login' => 'admin@pastell',
            'iparapheur_password' => 'MOT DE PASSE NON RECUPERABLE',
            'iparapheur_type' => 'PES',
            'not_existing_element' => "I don't exist",
        ], $info['data']);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testGetContentWithoutRight(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create(
            'badguy',
            'test@bar.baz',
            'user',
            'user',
            0,
            null,
            false
        );

        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUtilisateur->addRole($id_u, 'admin', 2);

        $internalAPI = $this->getInternalAPI();
        $internalAPI->setUtilisateurId($id_u);

        try {
            $internalAPI->patch('/entite/2/connecteur/12/content', ['champs1' => 'bar']);
        } catch (Exception) {
            /* Nothing to do  */
        }

        $internalAPI->setUtilisateurId(1);
        $result = $internalAPI->get('/entite/1/connecteur/12/');

        static::assertSame([], $result['data']);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testActionWithoutRight(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create(
            'badguy',
            'test@bar.baz',
            'user',
            'user',
            0,
            null,
            false
        );

        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUtilisateur->addRole($id_u, 'admin', 2);

        $internalAPI = $this->getInternalAPI();
        $internalAPI->setUtilisateurId($id_u);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le connecteur 12 n'appartient pas à l'entité 2");
        $internalAPI->post('/entite/2/connecteur/12/action/ok');
    }

    public function testGetAll(): void
    {
        static::assertSame(
            [
                [
                    'id_ce' => '1',
                    'id_e' => '1',
                    'libelle' => 'Fake iParapheur',
                    'id_connecteur' => 'fakeIparapheur',
                    'type' => 'signature',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '2',
                    'id_e' => '1',
                    'libelle' => 'Fake Tdt',
                    'id_connecteur' => 'fakeTdt',
                    'type' => 'TdT',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '3',
                    'id_e' => '1',
                    'libelle' => 'SEDA Standard',
                    'id_connecteur' => 'actes-seda-standard',
                    'type' => 'Bordereau SEDA',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '4',
                    'id_e' => '1',
                    'libelle' => 'Fake SAE',
                    'id_connecteur' => 'fakeSAE',
                    'type' => 'SAE',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '5',
                    'id_e' => '1',
                    'libelle' => 'Fake GED',
                    'id_connecteur' => 'FakeGED',
                    'type' => 'GED',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '6',
                    'id_e' => '1',
                    'libelle' => 'SEDA CG86',
                    'id_connecteur' => 'actes-seda-cg86',
                    'type' => 'Bordereau SEDA',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '7',
                    'id_e' => '1',
                    'libelle' => 'SEDA locarchive',
                    'id_connecteur' => 'actes-seda-locarchive',
                    'type' => 'Bordereau SEDA',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '8',
                    'id_e' => '1',
                    'libelle' => 'SEDA parametrable',
                    'id_connecteur' => 'actes-seda-parametrable',
                    'type' => 'Bordereau SEDA',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '9',
                    'id_e' => '1',
                    'libelle' => 'mail-fournisseur-invitation',
                    'id_connecteur' => 'mail-fournisseur-invitation',
                    'type' => 'mail-fournisseur-invitation',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '10',
                    'id_e' => '0',
                    'libelle' => 'Horodateur interne par défaut',
                    'id_connecteur' => 'horodateur-interne',
                    'type' => 'horodateur',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 1,
                    'denomination' => null,
                ],
                [
                    'id_ce' => '11',
                    'id_e' => '1',
                    'libelle' => 'Mail securise',
                    'id_connecteur' => 'mailsec',
                    'type' => 'mailsec',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '12',
                    'id_e' => '1',
                    'libelle' => 'connecteur non associé',
                    'id_connecteur' => 'test',
                    'type' => 'test',
                    'frequence_en_minute' => '1',
                    'id_verrou' => '',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],
                [
                    'id_ce' => '13',
                    'id_e' => '1',
                    'libelle' => 'Connecteur de test',
                    'id_connecteur' => 'test',
                    'type' => 'test',
                    'frequence_en_minute' => '1',
                    'id_verrou' => 'toto',
                    'global' => 0,
                    'denomination' => 'Bourg-en-Bresse',
                ],

            ],
            $this->getInternalAPI()->get('/connecteur/all')
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testGetAllWithoutPermission(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $userId = $userCreationService->create(
            'test',
            'test@bar.baz',
            'user',
            'user',
            0,
            null,
            false
        );
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("Acces interdit id_e=0, droit=connecteur:lecture,id_u=$userId");

        $this->getInternalAPIAsUser($userId)->get('/connecteur/all');
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testGetFileFromAnotherEntite(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create(
            'badguy',
            'test@bar.baz',
            'user',
            'user',
            0,
            null,
            false
        );
        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUtilisateur->addRole($id_u, 'admin', 2);

        $this->getInternalAPI()->post(
            '/entite/1/connecteur/12/file/champs5',
            [
                'file_name' => 'test.txt',
                'file_content' => 'test file content'
            ]
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le connecteur 12 n'appartient pas à l'entité 2");

        $this->getInternalAPIAsUser($id_u)->get('/entite/2/connecteur/12/file/champs5');
    }

    public function testGetExternalData(): void
    {
        static::assertSame(
            ['pierre', 'feuille', 'ciseaux', 'lézard', 'Spock'],
            $this->getInternalAPI()->get('/entite/1/connecteur/12/externalData/external_data')
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testGetExternalDataFromAnotherEntite(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create(
            'badguy',
            'test@bar.baz',
            'user',
            'user',
            0,
            null,
            false
        );
        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUtilisateur->addRole($id_u, 'admin', 2);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le connecteur 12 n'appartient pas à l'entité 2");
        $this->getInternalAPI()->get('/entite/2/connecteur/12/externalData/external_data');
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testPostWithReadPermission(): void
    {
        $roleSql = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSql->edit('readonly', 'readonly');
        $roleSql->addDroit('readonly', 'entite:lecture');
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $userId = $userCreationService->create(
            'readonly',
            'readonly@example.org',
            'user',
            'user',
            0,
            null,
            false
        );
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->addRole($userId, 'readonly', self::ID_E_COL);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=1, droit=connecteur:edition,id_u=3');

        $this->getInternalAPIAsUser($userId)->post('/entite/1/connecteur', [
            'libelle' => 'Connecteur de test',
            'id_connecteur' => 'test'
        ]);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testPostFileWithReadPermission(): void
    {
        $roleSql = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSql->edit('readonly', 'readonly');
        $roleSql->addDroit('readonly', 'entite:lecture');
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $userId = $userCreationService->create(
            'readonly',
            'readonly@example.org',
            'user',
            'user',
            0,
            null,
            false
        );
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->addRole($userId, 'readonly', self::ID_E_COL);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=1, droit=connecteur:edition,id_u=3');

        $this->getInternalAPIAsUser($userId)->post('/entite/1/connecteur/12/file/champs5', [
            'file_name' => 'test.txt',
            'file_content' => 'test file content'
        ]);
    }

    public function testDeleteFile(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/12/file/champs5', [
            'file_name' => 'test.txt',
            'file_content' => 'test file content'
        ]);
        $result = $this->getInternalAPI()->delete('/entite/1/connecteur/12/file/champs5');
        static::assertEquals('ok', $result['result']);
    }

    public function testDeleteMultipleFile(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur', [
            'libelle' => 'Connecteur de test',
            'id_connecteur' => 'mailsec'
        ]);
        $this->getInternalAPI()->post('/entite/1/connecteur/14/file/embeded_image/0', [
            'file_name' => 'img_test_1',
            'file_content' => 'ExifMM*0100'
        ]);
        $this->getInternalAPI()->post('/entite/1/connecteur/14/file/embeded_image/1', [
            'file_name' => 'img_test_2',
            'file_content' => 'ExifMM*0100'
        ]);
        $result = $this->getInternalAPI()->delete('/entite/1/connecteur/14/file/embeded_image/1');
        static::assertEquals('ok', $result['result']);
        $result = $this->getInternalAPI()->delete('/entite/1/connecteur/14/file/embeded_image');
        static::assertEquals('ok', $result['result']);
    }

    public function testDeleteMissingFile(): void
    {
        $result = $this->getInternalAPI()->delete('/entite/1/connecteur/12/file/champs5');
        static::assertSame('ok', $result['result']);
    }

    public function testDeleteMissingParameter(): void
    {
        $this->expectException(Exception::class);
        $this->getInternalAPI()->delete('/entite/1/connecteur/12/file/');
        $this->expectExceptionMessage('Paramètre manquant');
    }

    public function testDeleteFileWithoutPerm(): void
    {
        $this->getInternalAPI()->post('/entite/1/connecteur/12/file/champs5', [
            'file_name' => 'test.txt',
            'file_content' => 'test file content'
        ]);

        $result = $this->getInternalAPI()->delete('/entite/1/connecteur/12/file/champs5');
        static::assertEquals('ok', $result['result']);

        $roleSql = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSql->edit('readonly', 'readonly');
        $roleSql->addDroit('readonly', 'entite:lecture');
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $userId = $userCreationService->create(
            'readonly',
            'readonly@example.org',
            'user',
            'user',
            0,
            null,
            false
        );
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->addRole($userId, 'readonly', self::ID_E_COL);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=1, droit=connecteur:edition,id_u=3');
        $this->getInternalAPIAsUser($userId)->delete('/entite/1/connecteur/12/file/champs5');
    }
}
