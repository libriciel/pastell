<?php

declare(strict_types=1);

namespace Pastell\Updater\Major5\Minor0;

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
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
        $this->logger?->info('Start');

        $droit_connecteur_edition = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::EDITION);
        $droit_connecteur_action = DroitService::getDroitFor(DroitService::DROIT_CONNECTEUR, DroitType::ACTION);

        $roles = $this->roleSQL->getAllRole();
        $existing_connecteur_action_role = false;
        $roles_droits = [];
        foreach ($roles as $role) {
            $droit = $this->roleSQL->getDroit($this->roleDroit->getAllDroit(), $role['role']);
            if ($droit[$droit_connecteur_action]) {
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
                if ($roles_droits[$role['role']][$droit_connecteur_edition]) {
                    $this->roleSQL->addDroit($role['role'], $droit_connecteur_action);
                    $this->logger?->info(
                        sprintf(
                            'Added %s permission to role: `%s`',
                            $droit_connecteur_action,
                            $role['role']
                        )
                    );
                }
            }
        }
    }
}
