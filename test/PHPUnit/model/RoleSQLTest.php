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
