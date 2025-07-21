<?php

declare(strict_types=1);

namespace Pastell\Updater\Major5\Minor0;

use Pastell\Updater\Version;
use PastellLogger;
use RoleDroit;
use RoleSQL;

final class AddDroitConnecteurAction implements Version
{
    public function __construct(
        private readonly RoleSQL $roleSQL,
        private readonly RoleDroit $roleDroit,
        private readonly ?PastellLogger $logger = null,
    ) {
    }


    public function update(): void
    {
        $roles = $this->roleSQL->getAllRole();
        $existing_connecteur_action_role = false;
        $roles_droits = [];
        foreach ($roles as $role) {
            $droit = $this->roleSQL->getDroit($this->roleDroit->getAllDroit(), $role['role']);
            if ($droit['connecteur:action']) {
                $existing_connecteur_action_role = true;
                $this->logger?->info(
                    sprintf(
                        'Nothing to do. There are already connector action permission for role: `%s`',
                        $role['role']
                    )
                );
                break;
            }
            $roles_droits[$role['role']] = $droit;
        }

        if (!$existing_connecteur_action_role) {
            foreach ($roles as $role) {
                if ($roles_droits[$role['role']]['connecteur:edition']) {
                    $this->roleSQL->addDroit($role['role'], 'connecteur:action');
                    $this->logger?->info(
                        sprintf(
                            'Added connecteur:action permission to role: `%s`',
                            $role['role']
                        )
                    );
                }
            }
        }
    }
}
