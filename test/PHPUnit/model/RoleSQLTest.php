<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class RoleSQLTest extends PastellTestCase
{
    private RoleSQL $roleSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleSQL = new RoleSQL($this->getSQLQuery());
    }

    private function createRole(string $role, string $libelle, array $droit_list): void
    {
        $this->roleSQL->insert($role, $libelle);
        foreach ($droit_list as $droit) {
            $this->roleSQL->insertDroit($role, $droit);
        }
    }

    private function getEntiteDroitList(): array
    {
        return [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION),
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
        ];
    }

    private function getRole1DroitList(): array
    {
        return array_merge($this->getEntiteDroitList(), [
            DroitService::getDroitFor('test', DroitType::LECTURE),
            DroitService::getDroitFor('test', DroitType::EDITION),
        ]);
    }

    /**
     * @deprecated 4.1.26
     */
    public function testDelegateWithFewerDroits(): void
    {
        $this->createRole('role1', 'Rôle 1', $this->getRole1DroitList());
        $this->createRole('role2', 'Rôle 2', $this->getEntiteDroitList());

        $role_list = $this->roleSQL->getAuthorizedRoleToDelegate($this->getEntiteDroitList());

        self::assertContains('role2', $role_list);
        self::assertNotContains('role1', $role_list);
    }

    /**
     * @deprecated 4.1.26
     */
    public function testDelegateWithMoreDroits(): void
    {
        $this->createRole('role1', 'Rôle 1', $this->getRole1DroitList());
        $this->createRole('role2', 'Rôle 2', $this->getEntiteDroitList());

        $role_list = $this->roleSQL->getAuthorizedRoleToDelegate($this->getRole1DroitList());

        self::assertContains('role2', $role_list);
        self::assertContains('role1', $role_list);
    }

    public function testInsert(): void
    {
        $this->roleSQL->insert('role1', 'Rôle 1');

        self::assertTrue($this->roleSQL->exists('role1'));
        self::assertSame('Rôle 1', $this->roleSQL->getInfo('role1')['libelle']);
    }

    public function testExistsUnknownRole(): void
    {
        self::assertFalse($this->roleSQL->exists('role-inexistant'));
    }

    public function testUpdateLibelle(): void
    {
        $this->roleSQL->updateLibelle('autre', 'Autre libellé');

        self::assertSame('Autre libellé', $this->roleSQL->getInfo('autre')['libelle']);
    }

    public function testGetDroitList(): void
    {
        $this->roleSQL->insertDroit('autre', DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE));
        $this->roleSQL->insertDroit('autre', DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION));

        self::assertSame(
            [
                DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION),
                DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
            ],
            $this->roleSQL->getDroitList('autre')
        );
    }

    public function testGetAllRoleDroit(): void
    {
        $droit = DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE);
        $this->roleSQL->insertDroit('autre', $droit);

        self::assertContains(['role' => 'autre', 'droit' => $droit], $this->roleSQL->getAllRoleDroit());
    }

    public function testHasDroit(): void
    {
        $this->roleSQL->insertDroit('autre', 'entite:lecture');

        self::assertTrue($this->roleSQL->hasDroit('autre', 'entite:lecture'));
        self::assertFalse($this->roleSQL->hasDroit('autre', 'entite:edition'));
    }

    public function testDeleteAllDroit(): void
    {
        $this->roleSQL->deleteAllDroit('admin');

        self::assertSame([], $this->roleSQL->getDroitList('admin'));
    }
}
