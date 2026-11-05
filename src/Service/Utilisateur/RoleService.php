<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Pastell\Service\Notification\NotificationService;
use RoleDroit;
use RoleSQL;

final class RoleService
{
    public function __construct(
        private readonly RoleSQL $roleSQL,
        private readonly RoleDroit $roleDroit,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function updateDroit(string $role, array $droit): void
    {
        $droit = $this->roleDroit->filterExistingRolesDroits($droit);
        $oldDroit = $this->roleDroit->filterExistingRolesDroits(array_keys($this->roleSQL->getDroit([], $role)));
        $this->roleSQL->updateDroit($role, $droit);
        if (array_diff($oldDroit, $droit)) {
            $this->notificationService->revokeInvalidForRole($role);
        }
    }
}
