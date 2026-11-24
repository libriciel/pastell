<?php

declare(strict_types=1);

namespace Pastell\Service\Annuaire;

use AnnuaireRoleSQL;
use NotFoundException;
use Pastell\Service\Entite\EntityInfoService;
use Pastell\Service\Entite\RootEntity;

final class AnnuaireRoleService
{
    public function __construct(
        private readonly AnnuaireRoleSQL $annuaireRoleSQL,
        private readonly EntityInfoService $entityInfoService,
    ) {
    }

    public function getRoleName(int $id_e, string $role): string
    {
        if ($id_e === RootEntity::ID_E) {
            return $role . ' - toutes les collectivités';
        }
        return $role . ' - ' . $this->entityInfoService->getDenomination($id_e);
    }

    public function createRole(int $id_e_owner, int $id_e, string $role): string
    {
        $role_name = $this->getRoleName($id_e, $role);
        $this->annuaireRoleSQL->add($role_name, $id_e_owner, $id_e, $role);
        return $role_name;
    }

    /**
     * @throws NotFoundException
     */
    public function findRole(int $id_r): array
    {
        $role_info = $this->annuaireRoleSQL->getInfo($id_r);
        if ($role_info === false) {
            throw new NotFoundException("Le groupe de rôle id_r=$id_r n'existe pas");
        }
        return $role_info;
    }

    public function deleteRole(int $id_r): void
    {
        $this->annuaireRoleSQL->delete($id_r);
    }

    public function shareRole(int $id_r): void
    {
        $this->annuaireRoleSQL->partage($id_r);
    }

    public function unshareRole(int $id_r): void
    {
        $this->annuaireRoleSQL->unpartage($id_r);
    }

    public function getInheritedRoleLabel(array $role_info): string
    {
        if ($role_info['denomination']) {
            $prefix = 'rôle hérité de ' . $role_info['denomination'];
        } else {
            $prefix = 'rôle global';
        }
        return $prefix . ': "' . $role_info['nom'] . '"';
    }

    public function findInheritedRoleId(array $ancestor_ids, string $label): ?int
    {
        foreach ($this->annuaireRoleSQL->getGroupeHerite($ancestor_ids) as $role_info) {
            if ($label === $this->getInheritedRoleLabel($role_info)) {
                return (int)$role_info['id_r'];
            }
        }
        return null;
    }
}
