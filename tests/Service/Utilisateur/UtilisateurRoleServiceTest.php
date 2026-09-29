<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Notification;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use Pastell\Service\Utilisateur\UserCreationService;
use PastellTestCase;
use RoleSQL;

class UtilisateurRoleServiceTest extends PastellTestCase
{
    private const TYPE = 'actes-generique';

    private function getService(): UtilisateurRoleService
    {
        return $this->getObjectInstancier()->getInstance(UtilisateurRoleService::class);
    }

    private function getNotification(): Notification
    {
        return $this->getObjectInstancier()->getInstance(Notification::class);
    }

    private function createUser(string $login = 'lecteur'): int
    {
        return $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create($login, "$login@example.org", $login, $login, 1);
    }

    private function grantTypeAccess(string $role): void
    {
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->updateDroit($role, [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
            DroitService::getDroitFor(self::TYPE, DroitType::LECTURE),
        ]);
    }

    public function testRemovePurgesNotification(): void
    {
        $id_u = $this->createUser();
        $this->grantTypeAccess('lecteur');
        $this->getService()->addRole($id_u, 'lecteur', 1);
        $this->getNotification()->add($id_u, 1, self::TYPE, Notification::ALL_TYPE, false);

        $this->getService()->removeRole($id_u, 'lecteur', 1);

        static::assertEmpty($this->getNotification()->getAll($id_u));
    }

    public function testRemoveKeepsAccessible(): void
    {
        $id_u = $this->createUser();
        $this->grantTypeAccess('lecteur');
        $this->getService()->addRole($id_u, 'lecteur', 0);
        $this->getService()->addRole($id_u, 'lecteur', 1);
        $this->getNotification()->add($id_u, 1, self::TYPE, Notification::ALL_TYPE, false);

        $this->getService()->removeRole($id_u, 'lecteur', 1);

        static::assertCount(1, $this->getNotification()->getAll($id_u));
    }

    public function testRoleUpdatePurgesHolders(): void
    {
        $this->grantTypeAccess('lecteur');
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $id_u1 = $this->createUser('lecteur_un');
        $id_u2 = $this->createUser('lecteur_deux');
        foreach ([$id_u1, $id_u2] as $id_u) {
            $this->getService()->addRole($id_u, 'lecteur', 1);
            $this->getNotification()->add($id_u, 1, self::TYPE, Notification::ALL_TYPE, false);
        }

        $roleSQL->updateDroit('lecteur', [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
        ]);
        $this->getService()->purgeNotificationsForRole('lecteur');

        static::assertEmpty($this->getNotification()->getAll($id_u1));
        static::assertEmpty($this->getNotification()->getAll($id_u2));
    }
}
