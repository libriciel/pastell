<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Entite\RootEntity;
use Pastell\Service\Utilisateur\RoleDelegationService;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use PastellTestCase;
use RoleSQL;

class RoleDelegationServiceTest extends PastellTestCase
{
    private const ADMIN_USER_ID = 1;

    private RoleDelegationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getObjectInstancier()->getInstance(RoleDelegationService::class);
    }

    public function testGetAuthorizedRoleToDelegate(): void
    {
        $roles = $this->service->getAuthorizedRoleToDelegate(self::ADMIN_USER_ID);
        static::assertSame('admin', $roles[0]['role']);
    }

    public function testAdminCanDelegate(): void
    {
        static::assertTrue(
            $this->service->canDelegateRole(self::ADMIN_USER_ID, 'admin', RootEntity::ID_E)
        );
    }

    public function testCannotDelegateWithoutDroit(): void
    {
        $this->getObjectInstancier()->getInstance(UtilisateurRoleService::class)->removeAllRoles(2);

        static::assertFalse(
            $this->service->canDelegateRole(2, 'admin', RootEntity::ID_E)
        );
    }

    public function testDelegableRolesIncludesSubset(): void
    {
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->insertDroit('autre', 'entite:lecture');

        $role_list = $this->service->getDelegableRoles(['entite:lecture', 'entite:edition']);

        self::assertContains(['role' => 'autre', 'libelle' => 'autre rôle'], $role_list);
        self::assertNotContains('admin', array_column($role_list, 'role'));
    }

    public function testDelegableRolesExcludesSuperset(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSQL->insertDroit('autre', 'entite:lecture');
        $roleSQL->insertDroit('autre', 'entite:edition');

        $role_list = $this->service->getDelegableRoles(['entite:lecture']);

        self::assertNotContains('autre', array_column($role_list, 'role'));
    }

    public function testDelegableRolesOrder(): void
    {
        self::assertSame(
            [
                ['role' => 'autre', 'libelle' => 'autre rôle'],
                ['role' => 'utilisateur', 'libelle' => 'utilisateur sans rôle'],
            ],
            $this->service->getDelegableRoles([])
        );
    }
}
