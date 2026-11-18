<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use RoleSQL;

final class RoleDelegationService
{
    public function __construct(
        private readonly UtilisateurRoleService $utilisateurRoleService,
        private readonly RoleSQL $roleSQL,
    ) {
    }

    public function getAuthorizedRoleToDelegate(int $id_u): array
    {
        return $this->getDelegableRoles($this->utilisateurRoleService->getAllDroit($id_u));
    }

    public function canDelegateRole(int $id_u, string $role, int $id_e): bool
    {
        $droit_delegant = $this->utilisateurRoleService->getDroitsForEntite($id_u, $id_e);
        foreach ($this->getDelegableRoles($droit_delegant) as $role_info) {
            if ($role_info['role'] === $role) {
                return true;
            }
        }
        return false;
    }

    public function getDelegableRoles(array $droit_list): array
    {
        $droit_set = array_flip($droit_list);
        $forbidden_role_list = [];
        foreach ($this->roleSQL->getAllRoleDroit() as $role_droit) {
            if (!isset($droit_set[$role_droit['droit']])) {
                $forbidden_role_list[$role_droit['role']] = true;
            }
        }

        $result = [];
        foreach ($this->roleSQL->getAllRole() as $role_info) {
            if (!isset($forbidden_role_list[$role_info['role']])) {
                $result[] = ['role' => $role_info['role'], 'libelle' => $role_info['libelle']];
            }
        }
        return $result;
    }
}
