<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Notification\NotificationService;
use RoleDroit;
use RoleSQL;

final class RoleService
{
    public function __construct(
        private readonly RoleSQL $roleSQL,
        private readonly RoleDroit $roleDroit,
        private readonly NotificationService $notificationService,
        private readonly DroitService $droitService,
    ) {
    }

    public function saveRole(string $role, string $libelle): void
    {
        if ($this->roleSQL->exists($role)) {
            $this->roleSQL->updateLibelle($role, $libelle);
        } else {
            $this->roleSQL->insert($role, $libelle);
        }
    }

    public function createRole(string $role, string $libelle): void
    {
        $this->saveRole($role, $libelle);
        $this->addDroit($role, DroitService::getDroitFor(DroitService::DROIT_JOURNAL, DroitType::LECTURE));
        $this->addDroit($role, DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE));
    }

    public function addDroit(string $role, string $droit): void
    {
        if (!$this->roleSQL->hasDroit($role, $droit)) {
            $this->roleSQL->insertDroit($role, $droit);
        }
    }

    public function getDroitMap(array $all_droit, string $role): array
    {
        $result = array_fill_keys($all_droit, false);
        foreach ($this->roleSQL->getDroitList($role) as $droit) {
            $result[$droit] = true;
        }
        return $result;
    }

    public function getUnrestrictedDroitList(string $role): array
    {
        $result = [];
        foreach ($this->roleSQL->getDroitList($role) as $droit) {
            if (!$this->droitService->isRestrictedDroit($droit)) {
                $result[] = $droit;
            }
        }
        return $result;
    }

    public function updateDroit(string $role, array $droit): void
    {
        $droit = $this->roleDroit->filterExistingRolesDroits($droit);
        $oldDroit = $this->roleDroit->filterExistingRolesDroits($this->roleSQL->getDroitList($role));
        $this->roleSQL->deleteAllDroit($role);
        foreach ($droit as $droit_name) {
            $this->roleSQL->insertDroit($role, $droit_name);
        }
        if (array_diff($oldDroit, $droit)) {
            $this->notificationService->revokeInvalidForRole($role);
        }
    }
}
