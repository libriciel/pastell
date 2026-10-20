<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use EntiteSQL;
use Pastell\Service\Utilisateur\RoleDelegationService;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use PastellTestCase;

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
            $this->service->canDelegateRole(self::ADMIN_USER_ID, 'admin', EntiteSQL::ID_E_ENTITE_RACINE)
        );
    }

    public function testCannotDelegateWithoutDroit(): void
    {
        $this->getObjectInstancier()->getInstance(UtilisateurRoleService::class)->removeAllRoles(2);

        static::assertFalse(
            $this->service->canDelegateRole(2, 'admin', EntiteSQL::ID_E_ENTITE_RACINE)
        );
    }
}
