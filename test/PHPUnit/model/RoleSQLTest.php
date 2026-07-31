<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class RoleSQLTest extends PastellTestCase
{
    /** @var  RoleSQL */
    private $roleSQL;

    private $role_droit = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleSQL = new RoleSQL($this->getSQLQuery());
        $this->createRole('role1', 'Rôle 1', [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION),
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
            DroitService::getDroitFor('test', DroitType::LECTURE),
            DroitService::getDroitFor('test', DroitType::EDITION),
        ]);
        $this->createRole('role2', 'Rôle 2', [DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION), DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE)]);
    }

    private function createRole($id, $libelle, array $droit_list)
    {
        $this->roleSQL->edit($id, $libelle);
        $this->role_droit[$id] = $droit_list;
        $this->roleSQL->updateDroit($id, $droit_list);
    }

    public function testGetRoleWithDroitRoleInferieur()
    {
        $this->assertContains('role2', $this->roleSQL->getAuthorizedRoleToDelegate($this->role_droit['role2']));
        $this->assertNotContains('role1', $this->roleSQL->getAuthorizedRoleToDelegate($this->role_droit['role2']));
    }

    public function testGetRoleWithDroitRoleSuperieur()
    {
        $this->assertContains('role2', $this->roleSQL->getAuthorizedRoleToDelegate($this->role_droit['role1']));
        $this->assertContains('role1', $this->roleSQL->getAuthorizedRoleToDelegate($this->role_droit['role1']));
    }
}
