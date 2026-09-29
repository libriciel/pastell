<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Pastell\Service\Notification\NotificationService;
use RoleUtilisateur;

final class UtilisateurRoleService
{
    public function __construct(
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function addRole(int $id_u, string $role, int $id_e): void
    {
        $this->roleUtilisateur->addRole($id_u, $role, $id_e);
    }

    public function removeRole(int $id_u, string $role, int $id_e): void
    {
        $this->roleUtilisateur->removeRole($id_u, $role, $id_e);
        $this->notificationService->purgeIfNoAccess($id_u);
    }

    public function removeAllRolesEntite(int $id_u, int $id_e): void
    {
        $this->roleUtilisateur->removeAllRolesEntite($id_u, $id_e);
        $this->notificationService->purgeIfNoAccess($id_u);
    }

    public function purgeNotificationsForRole(string $role): void
    {
        foreach ($this->roleUtilisateur->getAllUtilisateurIdByRole($role) as $id_u) {
            $this->notificationService->purgeIfNoAccess((int) $id_u);
        }
    }
}
