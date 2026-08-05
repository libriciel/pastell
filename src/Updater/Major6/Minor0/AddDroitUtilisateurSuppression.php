<?php

declare(strict_types=1);

namespace Pastell\Updater\Major6\Minor0;

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Updater\Version;
use PastellLogger;
use RoleDroit;
use RoleSQL;

final class AddDroitUtilisateurSuppression implements Version
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

        $roles = $this->roleSQL->getAllRole();
        $existing_utilisateur_suppression_role = false;
        $roles_droits = [];
        foreach ($roles as $role) {
            $droit = $this->roleSQL->getDroit($this->roleDroit->getAllDroit(), $role['role']);
            if ($droit['utilisateur:suppression']) {
                $existing_utilisateur_suppression_role = true;
                $this->logger?->info(
                    \sprintf(
                        'Nothing to do. There are already utilisateur:suppression permission for role: `%s`',
                        $role['role']
                    )
                );
                break;
            }
            $roles_droits[$role['role']] = $droit;
        }

        if (!$existing_utilisateur_suppression_role) {
            foreach ($roles as $role) {
                if ($roles_droits[$role['role']][DroitService::getDroitFor(DroitService::DROIT_UTILISATEUR, DroitType::EDITION)]) {
                    $this->roleSQL->addDroit($role['role'], DroitService::getDroitFor(DroitService::DROIT_UTILISATEUR, DroitType::SUPPRESSION));
                    $this->logger?->info(
                        \sprintf(
                            'Added utilisateur:suppression permission to role: `%s`',
                            $role['role']
                        )
                    );
                }
            }
        }
    }
}
