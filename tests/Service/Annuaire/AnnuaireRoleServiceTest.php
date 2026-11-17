<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Annuaire;

use AnnuaireRoleSQL;
use NotFoundException;
use Pastell\Service\Annuaire\AnnuaireRoleService;
use Pastell\Service\Entite\RootEntity;
use PastellTestCase;

final class AnnuaireRoleServiceTest extends PastellTestCase
{
    private const ID_E = 1;
    private const ID_E_CHILD = 2;

    private function getAnnuaireRoleService(): AnnuaireRoleService
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireRoleService::class);
    }

    private function getAnnuaireRoleSQL(): AnnuaireRoleSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireRoleSQL::class);
    }

    public function testGetRoleName(): void
    {
        self::assertSame('admin - Bourg-en-Bresse', $this->getAnnuaireRoleService()->getRoleName(self::ID_E, 'admin'));
    }

    public function testGetRootRoleName(): void
    {
        self::assertSame(
            'admin - toutes les collectivités',
            $this->getAnnuaireRoleService()->getRoleName(RootEntity::ID_E, 'admin')
        );
    }

    public function testCreateRole(): void
    {
        $role_name = $this->getAnnuaireRoleService()->createRole(self::ID_E, self::ID_E_CHILD, 'admin');

        $role_info = $this->getAnnuaireRoleSQL()->getAll(self::ID_E)[0];
        self::assertSame('admin - CCAS', $role_name);
        self::assertSame('admin - CCAS', $role_info['nom']);
        self::assertSame(self::ID_E, (int)$role_info['id_e_owner']);
        self::assertSame(self::ID_E_CHILD, (int)$role_info['id_e']);
        self::assertSame('admin', $role_info['role']);
    }

    /**
     * @throws NotFoundException
     */
    public function testFindRole(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E, self::ID_E, 'admin');

        self::assertSame('admin - Bourg-en-Bresse', $this->getAnnuaireRoleService()->findRole($id_r)['nom']);
    }

    public function testFindUnknownRole(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getAnnuaireRoleService()->findRole(999);
    }

    public function testDeleteRole(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E, self::ID_E, 'admin');

        $this->getAnnuaireRoleService()->deleteRole($id_r);

        self::assertFalse($this->getAnnuaireRoleSQL()->getInfo($id_r));
    }

    /**
     * @throws NotFoundException
     */
    public function testShareRole(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E, self::ID_E, 'admin');

        $this->getAnnuaireRoleService()->shareRole($id_r);

        self::assertSame(1, (int)$this->getAnnuaireRoleService()->findRole($id_r)['partage']);
    }

    /**
     * @throws NotFoundException
     */
    public function testUnshareRole(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E, self::ID_E, 'admin');
        $this->getAnnuaireRoleSQL()->partage($id_r);

        $this->getAnnuaireRoleService()->unshareRole($id_r);

        self::assertSame(0, (int)$this->getAnnuaireRoleService()->findRole($id_r)['partage']);
    }

    public function testGetInheritedRoleLabel(): void
    {
        self::assertSame(
            'rôle hérité de Bourg-en-Bresse: "admin"',
            $this->getAnnuaireRoleService()->getInheritedRoleLabel(
                ['denomination' => 'Bourg-en-Bresse', 'nom' => 'admin']
            )
        );
    }

    public function testGetGlobalRoleLabel(): void
    {
        self::assertSame(
            'rôle global: "admin"',
            $this->getAnnuaireRoleService()->getInheritedRoleLabel(['denomination' => null, 'nom' => 'admin'])
        );
    }

    public function testFindInheritedRoleId(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E, self::ID_E, 'admin');
        $this->getAnnuaireRoleSQL()->partage($id_r);

        self::assertSame(
            $id_r,
            $this->getAnnuaireRoleService()->findInheritedRoleId(
                [self::ID_E, self::ID_E_CHILD],
                'rôle hérité de Bourg-en-Bresse: "admin - Bourg-en-Bresse"'
            )
        );
    }

    public function testFindUnsharedRoleId(): void
    {
        $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E, self::ID_E, 'admin');

        self::assertNull(
            $this->getAnnuaireRoleService()->findInheritedRoleId(
                [self::ID_E],
                'rôle hérité de Bourg-en-Bresse: "admin - Bourg-en-Bresse"'
            )
        );
    }

    public function testFindGlobalRoleId(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - toutes les collectivités', 0, 0, 'admin');
        $this->getAnnuaireRoleSQL()->partage($id_r);

        self::assertSame(
            $id_r,
            $this->getAnnuaireRoleService()->findInheritedRoleId(
                [0, self::ID_E],
                'rôle global: "admin - toutes les collectivités"'
            )
        );
    }
}
