<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use UtilisateurRoleSQL;
use UtilisateurSQL;

final class UtilisateurListService
{
    public const NB_UTILISATEUR_DISPLAY = 50;

    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly UtilisateurRoleSQL $utilisateurRoleSQL,
    ) {
    }

    public function getAllWithRoles(
        int $id_e,
        bool $with_fille = false,
        string $role = '',
        string $search = '',
        int $offset = 0,
        ?int $limit = self::NB_UTILISATEUR_DISPLAY,
    ): array {
        $all = $this->utilisateurSQL->getAllByEntite($id_e, $with_fille, $role, $search, $offset, $limit);
        $rolesByUtilisateur = [];
        foreach ($this->utilisateurRoleSQL->getRoleWithLibelle(array_column($all, 'id_u')) as $userRole) {
            $rolesByUtilisateur[$userRole['id_u']][] = $userRole;
        }
        foreach ($all as $i => $utilisateur) {
            $all[$i]['all_role'] = $rolesByUtilisateur[$utilisateur['id_u']] ?? [];
        }
        return $all;
    }
}
