<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use RoleSQL;
use UtilisateurRoleCache;

final class RoleDelegationService
{
    public function __construct(
        private readonly UtilisateurRoleCache $utilisateurRoleCache,
        private readonly RoleSQL $roleSQL,
    ) {
    }

    public function getAuthorizedRoleToDelegate(int $id_u): array
    {
        $role_list = $this->roleSQL->getAuthorizedRoleToDelegate($this->utilisateurRoleCache->getAllDroit($id_u));
        return $this->roleSQL->getRoleLibelle($role_list);
    }

    public function canDelegateRole(int $id_u, string $role, int $id_e): bool
    {
        $droit_delegant = $this->utilisateurRoleCache->getAllDroitEntite($id_u, $id_e);
        return \in_array($role, $this->roleSQL->getAuthorizedRoleToDelegate($droit_delegant), true);
    }
}
