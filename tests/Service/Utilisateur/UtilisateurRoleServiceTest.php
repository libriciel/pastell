<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use EntiteSQL;
use PastellTestCase;
use UtilisateurRoleSQL;

class UtilisateurRoleServiceTest extends PastellTestCase
{
    private UtilisateurRoleService $service;
    private UtilisateurRoleSQL $sql;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getObjectInstancier()->getInstance(UtilisateurRoleService::class);
        $this->sql = new UtilisateurRoleSQL($this->getSQLQuery());
    }

    private function createUser(): int
    {
        return $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('login', 'email@example.org', 'firstname', 'lastname');
    }

    private function createEntity(): int
    {
        return $this->getObjectInstancier()->getInstance(EntityCreationService::class)
            ->create('Entité', '000000000');
    }

    public function testAddRoleRemovesAucunDroit(): void
    {
        $id_u = $this->createUser();
        $id_e = $this->createEntity();
        $this->service->removeAllRolesForEntite($id_u, $id_e);

        $this->service->addRole($id_u, 'admin', $id_e);

        static::assertTrue($this->service->hasRole($id_u, 'admin', $id_e));
        static::assertSame(0, $this->sql->hasRole($id_u, DroitService::AUCUN_DROIT, $id_e));
    }

    public function testRemoveLastRoleKeepsAucun(): void
    {
        $id_u = $this->createUser();
        $id_e = $this->createEntity();
        $this->service->removeAllRoles($id_u);
        $this->service->addRole($id_u, 'admin', $id_e);

        $this->service->removeRole($id_u, 'admin', $id_e);

        $roles = $this->service->getRole($id_u);
        static::assertCount(1, $roles);
        static::assertSame(DroitService::AUCUN_DROIT, $roles[0]['role']);
    }

    public function testRemoveRoleDeletesWhenMany(): void
    {
        $id_u = $this->createUser();
        $id_e = $this->createEntity();
        $this->service->removeAllRoles($id_u);
        $this->service->addRole($id_u, 'admin', $id_e);
        $this->service->addRole($id_u, 'admin', EntiteSQL::ID_E_ENTITE_RACINE);

        $this->service->removeRole($id_u, 'admin', $id_e);

        static::assertFalse($this->service->hasRole($id_u, 'admin', $id_e));
        static::assertCount(1, $this->service->getRole($id_u));
    }

    public function testAnybodyHasRole(): void
    {
        $id_u = $this->createUser();
        $this->service->addRole($id_u, 'admin', $this->createEntity());

        static::assertTrue($this->service->anybodyHasRole('admin'));
        static::assertFalse($this->service->anybodyHasRole('role_inexistant'));
    }

    public function testRemoveAllRoles(): void
    {
        $id_u = $this->createUser();
        $this->service->addRole($id_u, 'admin', $this->createEntity());

        $this->service->removeAllRoles($id_u);

        static::assertSame([], $this->service->getRole($id_u));
    }
}
