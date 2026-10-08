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
        $role_list = $this->roleSQL->getAuthorizedRoleToDelegate($this->utilisateurRoleService->getAllDroit($id_u));
        return $this->roleSQL->getRoleLibelle($role_list);
    }

    public function canDelegateRole(int $id_u, string $role, int $id_e): bool
    {
        $droit_delegant = $this->utilisateurRoleService->getDroitsForEntite($id_u, $id_e);
        return \in_array($role, $this->roleSQL->getAuthorizedRoleToDelegate($droit_delegant), true);
    }
}
