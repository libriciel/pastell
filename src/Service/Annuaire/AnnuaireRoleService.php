<?php

declare(strict_types=1);

namespace Pastell\Service\Annuaire;

use AnnuaireRoleSQL;

final class AnnuaireRoleService
{
    public function __construct(
        private readonly AnnuaireRoleSQL $annuaireRoleSQL,
    ) {
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
