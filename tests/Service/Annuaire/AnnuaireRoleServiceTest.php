<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Annuaire;

use AnnuaireRoleSQL;
use Pastell\Service\Annuaire\AnnuaireRoleService;
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

    public function testGetInheritedRoleLabel(): void
    {
        self::assertSame(
            'rôle hérité de Bourg-en-Bresse: "admin"',
            $this->getAnnuaireRoleService()->getInheritedRoleLabel(['denomination' => 'Bourg-en-Bresse', 'nom' => 'admin'])
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

    public function testFindNotSharedRoleId(): void
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
