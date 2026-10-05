<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use EntiteSQL;
use Pastell\Service\Droit\DroitService;
use UtilisateurRoleCache;
use UtilisateurRoleSQL;

final class UtilisateurEntiteService
{
    public function __construct(
        private readonly UtilisateurRoleSQL $utilisateurRoleSQL,
        private readonly UtilisateurRoleCache $utilisateurRoleCache,
    ) {
    }

    public function getArbreFille(int $id_u, string $droit): array
    {
        $result = [];
        foreach ($this->utilisateurRoleSQL->getArbreFille($id_u, $droit) as $line) {
            $result[$line['entite_mere']][] = [
                'id_e' => $line['id_e'],
                'denomination' => $line['denomination'],
            ];
        }
        return $this->flattenTree($result);
    }

    public function getArbreFilleWithRacine(int $id_u, string $droit): array
    {
        $arbre = $this->getArbreFille($id_u, $droit);
        $droitsRacine = $this->utilisateurRoleCache->getAllDroitEntite($id_u, EntiteSQL::ID_E_ENTITE_RACINE);
        if (in_array($droit, $droitsRacine, true)) {
            array_unshift($arbre, [
                'id_e' => EntiteSQL::ID_E_ENTITE_RACINE,
                'denomination' => EntiteSQL::ENTITE_RACINE_DENOMINATION,
                'profondeur' => 0,
            ]);
        }
        return $arbre;
    }

    public function getAllEntiteWithFille(int $id_u, string $droit, ?string $active): array
    {
        return $this->utilisateurRoleSQL->getAllEntiteWithFille($id_u, $droit, $active);
    }

    public function getEntite(int $id_u, string $droit): array
    {
        return $this->utilisateurRoleSQL->getEntite($id_u, $droit);
    }

    public function getAllEntiteDroit($id_u, $id_e = false): array
    {
        return $this->utilisateurRoleSQL->getAllEntiteDroit($id_u, $id_e);
    }

    public function getEntiteWithDenomination(int $id_u, string $droit): array
    {
        return $this->utilisateurRoleSQL->getEntiteWithDenomination($id_u, $droit);
    }

    public function getEntiteWithAnyDroit(int $id_u): array
    {
        return $this->utilisateurRoleSQL->getEntiteWithAnyDroit($id_u);
    }

    public function getChildrenWithAnyDroit(int $id_e_parent, int $id_u): array
    {
        return $this->utilisateurRoleSQL->getChildrenWithAnyDroit($id_e_parent, $id_u, DroitService::AUCUN_DROIT);
    }

    private function flattenTree(array $all): array
    {
        $result = [];
        while (\count($all) > 0) {
            $result = array_merge($result, $this->flattenTreeRecursive(0, $all, 0));
        }
        return $result;
    }

    private function flattenTreeRecursive($id_e, array &$all, int $profondeur): array
    {
        $result = [];
        if (empty($all[$id_e])) {
            $id_e = array_keys($all)[0];
        }
        foreach ($all[$id_e] as $line) {
            $line['profondeur'] = $profondeur;
            $result[] = $line;
            if (isset($all[$line['id_e']])) {
                $result = array_merge($result, $this->flattenTreeRecursive($line['id_e'], $all, $profondeur + 1));
            }
        }
        unset($all[$id_e]);
        return $result;
    }
}
