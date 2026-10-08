<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Pastell\Service\Droit\DroitService;
use UtilisateurRoleCache;
use UtilisateurRoleSQL;

final class UtilisateurRoleService
{
    public function __construct(
        private readonly UtilisateurRoleSQL $utilisateurRoleSQL,
        private readonly UtilisateurRoleCache $utilisateurRoleCache,
    ) {
    }

    public function addRole(int $id_u, string $role, int $id_e): void
    {
        $this->utilisateurRoleSQL->insertRole($id_u, $role, $id_e);
        if ($role !== DroitService::AUCUN_DROIT) {
            $this->utilisateurRoleSQL->deleteRole($id_u, DroitService::AUCUN_DROIT, $id_e);
        }
        $this->utilisateurRoleCache->invalidate($id_u, $id_e);
    }

    public function hasRole(int $id_u, string $role, int $id_e): bool
    {
        return $this->utilisateurRoleSQL->hasRole($id_u, $role, $id_e) > 0;
    }

    public function getRole(int $id_u): array
    {
        return $this->utilisateurRoleSQL->getRole($id_u);
    }

    public function getDroitsForEntite($id_u, int $id_e): array
    {
        return $this->utilisateurRoleCache->getDroitsForEntite((int) $id_u, $id_e);
    }

    public function getAllDroit(int $id_u): array
    {
        return $this->utilisateurRoleCache->getAllDroit($id_u);
    }

    public function anybodyHasRole(string $role): bool
    {
        return $this->utilisateurRoleSQL->anybodyHasRole($role) > 0;
    }

    public function removeRole(int $id_u, string $role, int $id_e): void
    {
        if ($this->utilisateurRoleSQL->countRoles($id_u) === 1) {
            $this->utilisateurRoleSQL->replaceRole($id_u, $role, $id_e, DroitService::AUCUN_DROIT);
        } else {
            $this->utilisateurRoleSQL->deleteRole($id_u, $role, $id_e);
        }
        $this->utilisateurRoleCache->invalidate($id_u, $id_e);
    }

    public function removeAllRoles(int $id_u): void
    {
        $this->utilisateurRoleSQL->deleteAllRoles($id_u);
        // Incomplete: only id_e=0 and 'all' keys are invalidated,
        // other entities stay stale until TTL (remains from deprecated RoleUtilisateur class #420)
        $this->utilisateurRoleCache->invalidate($id_u, 0);
    }

    public function removeAllRolesForEntite(int $id_u, int $id_e): void
    {
        $this->utilisateurRoleSQL->deleteRolesForEntite($id_u, $id_e);
        $this->utilisateurRoleSQL->insertRole($id_u, DroitService::AUCUN_DROIT, $id_e);
        $this->utilisateurRoleCache->invalidate($id_u, $id_e);
    }
}
