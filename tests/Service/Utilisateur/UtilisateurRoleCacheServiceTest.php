<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Utilisateur\UtilisateurRoleCacheService;
use PastellTestCase;
use StaticWrapper;
use UtilisateurRoleSQL;

final class UtilisateurRoleCacheServiceTest extends PastellTestCase
{
    private UtilisateurRoleSQL $utilisateurRoleSQL;
    private UtilisateurRoleCacheService $utilisateurRoleCacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $staticWrapper = new StaticWrapper();
        $staticWrapper->flushAll();
        $this->utilisateurRoleSQL = $this->getObjectInstancier()->getInstance(UtilisateurRoleSQL::class);
        $this->utilisateurRoleCacheService = new UtilisateurRoleCacheService(
            $this->utilisateurRoleSQL,
            $staticWrapper,
            10
        );
    }

    public function testDroitsForEntiteCached(): void
    {
        $droit_list = $this->utilisateurRoleCacheService->getDroitsForEntite(self::ID_U_ADMIN, self::ID_E_COL);
        $this->utilisateurRoleSQL->deleteAllRoles(self::ID_U_ADMIN);

        self::assertNotEmpty($droit_list);
        self::assertSame(
            $droit_list,
            $this->utilisateurRoleCacheService->getDroitsForEntite(self::ID_U_ADMIN, self::ID_E_COL)
        );
    }

    public function testAllDroitCached(): void
    {
        $droit_list = $this->utilisateurRoleCacheService->getAllDroit(self::ID_U_ADMIN);
        $this->utilisateurRoleSQL->deleteAllRoles(self::ID_U_ADMIN);

        self::assertNotEmpty($droit_list);
        self::assertSame($droit_list, $this->utilisateurRoleCacheService->getAllDroit(self::ID_U_ADMIN));
    }

    public function testInvalidate(): void
    {
        $this->utilisateurRoleCacheService->getDroitsForEntite(self::ID_U_ADMIN, self::ID_E_COL);
        $this->utilisateurRoleCacheService->getAllDroit(self::ID_U_ADMIN);
        $this->utilisateurRoleSQL->deleteAllRoles(self::ID_U_ADMIN);

        $this->utilisateurRoleCacheService->invalidate(self::ID_U_ADMIN, self::ID_E_COL);

        self::assertSame([], $this->utilisateurRoleCacheService->getDroitsForEntite(self::ID_U_ADMIN, self::ID_E_COL));
        self::assertSame([], $this->utilisateurRoleCacheService->getAllDroit(self::ID_U_ADMIN));
    }
}
