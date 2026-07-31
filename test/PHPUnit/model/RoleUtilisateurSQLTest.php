<?php

use Pastell\Helpers\ArrayHelper;
use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class RoleUtilisateurSQLTest extends PastellTestCase
{
    private RoleUtilisateur $roleUtilisateurSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleUtilisateurSQL = new RoleUtilisateur(
            $this->getSQLQuery(),
            $this->getObjectInstancier()->getInstance(RoleSQL::class),
            //On utilise un cache !
            new StaticWrapper(),
            10
        );
    }

    public function testGetRole()
    {
        $role_list = $this->roleUtilisateurSQL->getRole(1);
        $this->assertEquals("admin", $role_list[0]['role']);
    }

    public function testgetAuthorizedRoleToDelegate()
    {
        $role_list = $this->roleUtilisateurSQL->getAuthorizedRoleToDelegate(1);
        $this->assertEquals("admin", $role_list[0]['role']);
    }

    public function testgetAuthorizedRoleToDelegateOtherRole()
    {
        $this->roleUtilisateurSQL->removeAllRole(2);
        $role_list = $this->roleUtilisateurSQL->getAuthorizedRoleToDelegate(2);
        $result = [];
        foreach ($role_list as $role_info) {
            $result[] = $role_info['role'];
        }
        $this->assertNotContains('admin', $result);
    }

    public function testgetAllDroitEntite()
    {
        //FROM DATABASE
        $all = $this->roleUtilisateurSQL->getAllDroitEntite(2, 1);
        $this->assertDroit($all);
        //FROM CACHE
        $all = $this->roleUtilisateurSQL->getAllDroitEntite(2, 1);
        $this->assertDroit($all);
        //CLEANING CACHE
        $this->roleUtilisateurSQL->removeAllRolesEntite(2, 1);
        $all = $this->roleUtilisateurSQL->getAllDroitEntite(2, 1);
        $this->assertEmpty($all);
    }

    public function testAddRole()
    {
        $this->roleUtilisateurSQL->removeAllRole(2);
        $this->assertEquals(
            [],
            $this->roleUtilisateurSQL->getAllDroitEntite(2, 1)
        );
        $this->roleUtilisateurSQL->addRole(
            2,
            'admin',
            1
        );
        $all = $this->roleUtilisateurSQL->getAllDroitEntite(2, 1);
        $this->assertDroit($all);
    }


    public function testAddRoleAll()
    {
        $this->roleUtilisateurSQL->removeAllRole(2);

        $this->roleUtilisateurSQL->addRole(
            2,
            'admin',
            1
        );
        $all = $this->roleUtilisateurSQL->getAllDroit(2);
        $this->assertDroit($all);
    }


    private function assertDroit($all_droit)
    {
        $this->assertEquals([
            'actes-automatique:edition',
            'actes-automatique:lecture',
            'actes-generique:edition',
            'actes-generique:lecture',
            'actes-preversement-seda:edition',
            'actes-preversement-seda:lecture',
            'actes-reponse-prefecture:edition',
            'actes-reponse-prefecture:lecture',
            'annuaire:edition',
            'annuaire:lecture',
            'commande-generique:edition',
            'commande-generique:lecture',
            'connecteur:edition',
            'connecteur:lecture',
            'document-a-signer:edition',
            'document-a-signer:lecture',
            'entite:edition',
            'entite:lecture',
            'fournisseur-invitation:edition',
            'fournisseur-invitation:lecture',
            'helios-automatique:edition',
            'helios-automatique:lecture',
            'helios-generique:edition',
            'helios-generique:lecture',
            'journal:lecture',
            'ls-document-pdf:edition',
            'ls-document-pdf:lecture',
            'mailsec-bidir:edition',
            'mailsec-bidir:lecture',
            'mailsec:edition',
            'mailsec:lecture',
            'message-service:edition',
            'message-service:lecture',
            'pdf-generique:edition',
            'pdf-generique:lecture',
            'role:edition',
            'role:lecture',
            'system:edition',
            'system:lecture',
            'test:edition',
            'test:lecture',
            'utilisateur:creation',
            'utilisateur:edition',
            'utilisateur:lecture',
        ], $all_droit);
    }

    public function testRoleNameIsTooLong()
    {
        //Ca bug si la taille maximum des champs utilisateur_role:role, role:role et role_droit:role n'est pas identique
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $role_id = "mon_super_role_qui_depasse_allegrement_les_soixante_quatre_caracteres";
        $roleSQL->edit($role_id, "Mon role très long");
        $roleSQL->addDroit($role_id, "foo:bar");

        $this->roleUtilisateurSQL->addRole(1, $role_id, 1);

        $this->assertTrue($this->roleUtilisateurSQL->hasDroit(1, "foo:bar", 1));
    }

    /**
     * @throws ConflictException
     * @throws UnrecoverableException
     */
    public function testGetArbreFille(): void
    {
        /**
         * Tree structure
         * 1
         * - 11
         * - - 111
         * - 12
         * - - 121
         * 2
         * 3
         * - 31
         * - - 311
         * - 32
         * - - 321
         */
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $entity1 = $entityCreationService->create('Entité 1', '000000000');
        $entity11 = $entityCreationService->create('Entité 11', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $entity1);
        $entity111 = $entityCreationService->create('Entité 111', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $entity11);
        $entity12 = $entityCreationService->create('Entité 12', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $entity1);
        $entity121 = $entityCreationService->create('Entité 121', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $entity12);
        $entity2 = $entityCreationService->create('Entité 2', '000000000');
        $entity3 = $entityCreationService->create('Entité 3', '000000000');
        $entity31 = $entityCreationService->create('Entité 31', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $entity3);
        $entity311 = $entityCreationService->create('Entité 311', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $entity31);
        $entity32 = $entityCreationService->create('Entité 32', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $entity3);
        $entity321 = $entityCreationService->create('Entité 321', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $entity32);

        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('test_get_arbre_fille', 'aa@aa.fr', 'user', 'user');

        $this->roleUtilisateurSQL->addRole($id_u, 'admin', $entity1);
        $this->roleUtilisateurSQL->addRole($id_u, 'admin', $entity31);
        $this->roleUtilisateurSQL->addRole($id_u, 'admin', $entity321);

        $arbre_fille = $this->roleUtilisateurSQL->getArbreFille($id_u, DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE));

        static::assertSame(
            [
                0 =>
                    [
                        'id_e' => $entity1,
                        'denomination' => 'Entité 1',
                        'profondeur' => 0,
                    ],
                1 =>
                    [
                        'id_e' => $entity11,
                        'denomination' => 'Entité 11',
                        'profondeur' => 1,
                    ],
                2 =>
                    [
                        'id_e' => $entity111,
                        'denomination' => 'Entité 111',
                        'profondeur' => 2,
                    ],
                3 =>
                    [
                        'id_e' => $entity12,
                        'denomination' => 'Entité 12',
                        'profondeur' => 1,
                    ],
                4 =>
                    [
                        'id_e' => $entity121,
                        'denomination' => 'Entité 121',
                        'profondeur' => 2,
                    ],
                5 =>
                    [
                        'id_e' => $entity31,
                        'denomination' => 'Entité 31',
                        'profondeur' => 0,
                    ],
                6 =>
                    [
                        'id_e' => $entity311,
                        'denomination' => 'Entité 311',
                        'profondeur' => 1,
                    ],
                7 =>
                    [
                        'id_e' => $entity321,
                        'denomination' => 'Entité 321',
                        'profondeur' => 0,
                    ],
            ],
            $arbre_fille
        );

        $tree = ArrayHelper::buildNestedTree($arbre_fille);
        self::assertSame(
            [
                [
                    'id_e' => $entity1,
                    'denomination' => 'Entité 1',
                    'profondeur' => 0,
                    'children' =>
                        [
                            0 =>
                                [
                                    'id_e' => $entity11,
                                    'denomination' => 'Entité 11',
                                    'profondeur' => 1,
                                    'children' =>
                                        [
                                            0 =>
                                                [
                                                    'id_e' => $entity111,
                                                    'denomination' => 'Entité 111',
                                                    'profondeur' => 2,
                                                ],
                                        ],
                                ],
                            1 =>
                                [
                                    'id_e' => $entity12,
                                    'denomination' => 'Entité 12',
                                    'profondeur' => 1,
                                    'children' =>
                                        [
                                            0 =>
                                                [
                                                    'id_e' => $entity121,
                                                    'denomination' => 'Entité 121',
                                                    'profondeur' => 2,
                                                ],
                                        ],
                                ],
                        ],
                ],
                1 =>
                    [
                        'id_e' => $entity31,
                        'denomination' => 'Entité 31',
                        'profondeur' => 0,
                        'children' =>
                            [
                                0 =>
                                    [
                                        'id_e' => $entity311,
                                        'denomination' => 'Entité 311',
                                        'profondeur' => 1,
                                    ],
                            ],
                    ],
                2 =>
                    [
                        'id_e' => $entity321,
                        'denomination' => 'Entité 321',
                        'profondeur' => 0,
                    ],
            ],
            $tree
        );
    }

    /**
     * @throws ConflictException
     * @throws UnrecoverableException
     */
    public function testGetArbreFille2(): void
    {
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $id_e_1 = $entityCreationService->create('Entité 1', '000000000');
        $id_e_2 = $entityCreationService->create('Entité 2', '000000000');
        $id_e_3 = $entityCreationService->create('Entité 3', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_2);

        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('test_get_arbre_fille', 'aa@aa.fr', 'user', 'user');

        $this->roleUtilisateurSQL->addRole($id_u, 'admin', 0);

        $arbre_fille = $this->roleUtilisateurSQL->getArbreFille($id_u, DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE));

        static::assertSame(
            [
                0 =>
                    [
                        'id_e' => 1,
                        'denomination' => 'Bourg-en-Bresse',
                        'profondeur' => 0,
                    ],
                1 =>
                    [
                        'id_e' => 2,
                        'denomination' => 'CCAS',
                        'profondeur' => 1,
                    ],
                2 =>
                    [
                        'id_e' => 3,
                        'denomination' => 'Entité 1',
                        'profondeur' => 0,
                    ],
                3 =>
                    [
                        'id_e' => 4,
                        'denomination' => 'Entité 2',
                        'profondeur' => 0,
                    ],
                4 =>
                    [
                        'id_e' => 5,
                        'denomination' => 'Entité 3',
                        'profondeur' => 1,
                    ],
            ],
            $arbre_fille
        );

        $tree = ArrayHelper::buildNestedTree($arbre_fille);
        self::assertSame(
            [
                [
                    'id_e' => 1,
                    'denomination' => 'Bourg-en-Bresse',
                    'profondeur' => 0,
                    'children' => [
                        [
                            'id_e' => 2,
                            'denomination' => 'CCAS',
                            'profondeur' => 1,
                        ],
                    ],
                ],
                [
                    'id_e' => 3,
                    'denomination' => 'Entité 1',
                    'profondeur' => 0,
                ],
                [
                    'id_e' => 4,
                    'denomination' => 'Entité 2',
                    'profondeur' => 0,
                    'children' => [
                        [
                            'id_e' => 5,
                            'denomination' => 'Entité 3',
                            'profondeur' => 1,
                        ],
                    ],
                ],
            ],
            $tree
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetChildrenWithPermission(): void
    {
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $id_e_2 = $entityCreationService->create('Entité 2', '000000000');
        $id_e_3 = $entityCreationService->create('Entité 3', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_2);

        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('test', 'aa@aa.fr', 'user', 'user');

        $this->roleUtilisateurSQL->addRole($id_u, 'admin', $id_e_2);
        $this->roleUtilisateurSQL->addRole($id_u, 'admin', $id_e_3);

        $childrenWithPermissions = $this->roleUtilisateurSQL->getChildrenWithPermission($id_e_2, $id_u);

        static::assertCount(1, $childrenWithPermissions);
        static::assertSame($id_e_3, $childrenWithPermissions[0]['id_e']);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testGetArbreFilleNumericSort(): void
    {
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);

        $id_e_1 = $entityCreationService->create('Entité 1', '000000000');
        $id_e_1_1 = $entityCreationService->create('Entité 1-1', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_1);
        $entityCreationService->create('Entité 1-1-1', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_1_1);
        $id_e_1_2 = $entityCreationService->create('Entité 1-2', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_1);
        $entityCreationService->create('Entité 1-2-1', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_1_2);
        $entityCreationService->create('Entité 2', '000000000');

        $id_e_3 = $entityCreationService->create('Entité 3', '000000000');
        $id_e_3_1 = $entityCreationService->create('Entité 3-1', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_3);
        $id_e_3_1_1 = $entityCreationService->create('Entité 3-1-1', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_3_1);
        $id_e_3_2 = $entityCreationService->create('Entité 3-2', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_3);
        $entityCreationService->create('Entité 3-2-1', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_3_2);

        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('test_bug', 'test@test.fr', 'user', 'user');

        $this->roleUtilisateurSQL->addRole($id_u, 'admin', $id_e_3_1);

        $arbreFille = $this->roleUtilisateurSQL->getArbreFille($id_u, DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE));
        static::assertSame(
            [
                0 => [
                    'id_e' => $id_e_3_1,
                    'denomination' => 'Entité 3-1',
                    'profondeur' => 0,
                ],
                1 => [
                    'id_e' => $id_e_3_1_1,
                    'denomination' => 'Entité 3-1-1',
                    'profondeur' => 1,
                ],
            ],
            $arbreFille
        );
    }
}
