<?php

declare(strict_types=1);

namespace Pastell\Updater\Major5\Minor0;

use Pastell\Updater\Version;
use PastellLogger;
use RoleDroit;
use RoleSQL;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

final class AddDroitDaemon implements Version
{
    public function __construct(
        private readonly RoleSQL $roleSQL,
        private readonly RoleDroit $roleDroit,
        private readonly ?PastellLogger $logger = null,
    ) {
    }


    public function update(): void
    {
        $this->logger?->info('Start');

        $daemon_lecture = DroitService::getDroitFor(DroitService::DROIT_DAEMON, DroitType::LECTURE);
        $daemon_edition = DroitService::getDroitFor(DroitService::DROIT_DAEMON, DroitType::EDITION);
        $system_lecture = DroitService::getDroitFor(DroitService::DROIT_SYSTEM, DroitType::LECTURE);
        $system_edition = DroitService::getDroitFor(DroitService::DROIT_SYSTEM, DroitType::EDITION);

        $roles = $this->roleSQL->getAllRole();
        $existing_daemon_role = false;
        $roles_droits = [];
        foreach ($roles as $role) {
            $droit = $this->roleSQL->getDroit($this->roleDroit->getAllDroit(), $role['role']);
            if ($droit[$daemon_lecture] || $droit[$daemon_edition]) {
                $existing_daemon_role = true;
            }
            $roles_droits[$role['role']] = $droit;
        }

        if (!$existing_daemon_role) {
            foreach ($roles as $role) {
                if ($roles_droits[$role['role']][$system_lecture]) {
                    $this->roleSQL->addDroit($role['role'], $daemon_lecture);
                    $this->logger?->info(
                        sprintf(
                            'Added %s permission to role: `%s`',
                            $daemon_lecture,
                            $role['role']
                        )
                    );
                }
                if ($roles_droits[$role['role']][$system_edition]) {
                    $this->roleSQL->addDroit($role['role'], $daemon_edition);
                    $this->logger?->info(
                        sprintf(
                            'Added %s permission to role: `%s`',
                            $daemon_edition,
                            $role['role']
                        )
                    );
                }
            }
        }
    }
}
