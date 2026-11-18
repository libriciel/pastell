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

    public function testInsert(): void
    {
        $this->roleSQL->insert('role1', 'Rôle 1');

        static::assertTrue($this->roleSQL->exists('role1'));
        static::assertSame('Rôle 1', $this->roleSQL->getInfo('role1')['libelle']);
    }

    public function testExistsUnknownRole(): void
    {
        static::assertFalse($this->roleSQL->exists('role-inexistant'));
    }

    public function testUpdateLibelle(): void
    {
        $this->roleSQL->updateLibelle('autre', 'Autre libellé');

        static::assertSame('Autre libellé', $this->roleSQL->getInfo('autre')['libelle']);
    }

    public function testGetDroitList(): void
    {
        $droit_list = [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION),
        ];
        $this->roleSQL->updateDroit('autre', $droit_list);

        static::assertSame(
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
        $this->roleSQL->updateDroit('autre', [$droit]);

        static::assertContains(['role' => 'autre', 'droit' => $droit], $this->roleSQL->getAllRoleDroit());
    }
}
