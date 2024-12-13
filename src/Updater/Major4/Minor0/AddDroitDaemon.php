<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor0;

use Pastell\Updater\Version;
use RoleDroit;
use RoleSQL;

final class AddDroitDaemon implements Version
{
    public function __construct(
        private readonly RoleSQL $roleSQL,
        private readonly RoleDroit $roleDroit,
    ) {
    }


    public function update(): void
    {
        $roles = $this->roleSQL->getAllRole();
        $existing_daemon_role = false;
        $roles_droits = [];
        foreach ($roles as $role) {
            $droit = $this->roleSQL->getDroit($this->roleDroit->getAllDroit(), $role['role']);
            if ($droit['daemon:lecture'] || $droit['daemon:edition']) {
                $existing_daemon_role = true;
            }
            $roles_droits[$role['role']] = $droit;
        }

        if (!$existing_daemon_role) {
            foreach ($roles as $role) {
                if ($roles_droits[$role['role']]['system:lecture']) {
                    $this->roleSQL->addDroit($role['role'], 'daemon:lecture');
                }
                if ($roles_droits[$role['role']]['system:edition']) {
                    $this->roleSQL->addDroit($role['role'], 'daemon:edition');
                }
            }
        }
    }
}
