<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

use EntiteSQL;

final class EntityHierarchyService
{
    public function __construct(
        private readonly EntiteSQL $entiteSQL,
    ) {
    }

    /**
     * Root entity first, the entity itself excluded.
     */
    public function getAncestorIds(int $id_e): array
    {
        $ancestor_list = $this->getStrictAncestors($id_e);
        $ancestor_ids = [EntiteSQL::ID_E_ENTITE_RACINE];
        foreach ($ancestor_list as $ancestor_info) {
            $ancestor_ids[] = $ancestor_info['id_e'];
        }
        return $ancestor_ids;
    }

    public function getAllChildren(int $id_e): array
    {
        $children = $this->entiteSQL->getFille($id_e);
        foreach ($children as $child_info) {
            $children = array_merge($children, $this->getAllChildren((int)$child_info['id_e']));
        }

        usort($children, static function (array $a, array $b): int {
            return strcasecmp($a['denomination'], $b['denomination']);
        });

        return $children;
    }

    /**
     * Root entity first, down to the given entity.
     */
    public function getAncestorDenominations(int $id_e): array
    {
        $denomination_list = [];
        while ($id_e !== EntiteSQL::ID_E_ENTITE_RACINE) {
            $entity_info = $this->entiteSQL->getInfo($id_e);
            if (!$entity_info) {
                break;
            }
            $denomination_list[] = $entity_info['denomination'];
            $id_e = (int)$entity_info['entite_mere'];
        }
        $denomination_list[] = EntiteSQL::ENTITE_RACINE_DENOMINATION;
        return array_reverse($denomination_list);
    }

    /**
     * Ancestors displayed in the navigation, starting from the highest one the user has access to.
     */
    public function getNavigationAncestors(int $id_e, array $allowed_id_e_list): array
    {
        $ancestor_list = $this->getStrictAncestors($id_e);

        if (in_array(EntiteSQL::ID_E_ENTITE_RACINE, $allowed_id_e_list)) {
            return $ancestor_list;
        }

        while ($ancestor_list && !in_array($ancestor_list[0]['id_e'], $allowed_id_e_list)) {
            array_shift($ancestor_list);
        }
        return $ancestor_list;
    }

    public function getNavigationChildren(int $id_e, array $allowed_id_e_list = []): array
    {
        if (
            $id_e !== EntiteSQL::ID_E_ENTITE_RACINE
            || !$allowed_id_e_list
            || $allowed_id_e_list[0] == EntiteSQL::ID_E_ENTITE_RACINE
        ) {
            return $this->entiteSQL->getActiveChildrenByType(
                $id_e,
                [EntiteSQL::TYPE_COLLECTIVITE, EntiteSQL::TYPE_CENTRE_DE_GESTION]
            );
        }

        $children = [];
        foreach ($allowed_id_e_list as $allowed_id_e) {
            $child_info = $this->entiteSQL->getInfo($allowed_id_e);
            if ($child_info['is_active']) {
                $children[] = $child_info;
            }
        }
        return $children;
    }

    public function updateAncestors(int $id_e, int $parent_id_e = EntiteSQL::ID_E_ENTITE_RACINE): void
    {
        $this->entiteSQL->deleteAncestors($id_e);
        $niveau = 0;
        $this->entiteSQL->insertAncestor($id_e, $id_e, $niveau++);
        while ($parent_id_e !== EntiteSQL::ID_E_ENTITE_RACINE) {
            $this->entiteSQL->insertAncestor($parent_id_e, $id_e, $niveau++);
            $parent_id_e = (int)$this->entiteSQL->getEntiteMere($parent_id_e);
        }
        $this->entiteSQL->insertAncestor(EntiteSQL::ID_E_ENTITE_RACINE, $id_e, $niveau);
    }

    public function updateAllAncestors(): void
    {
        $this->entiteSQL->deleteAllAncestors();
        $this->entiteSQL->insertAncestor(EntiteSQL::ID_E_ENTITE_RACINE, EntiteSQL::ID_E_ENTITE_RACINE, 0);
        foreach ($this->entiteSQL->getAll() as $entity_info) {
            $this->updateAncestors((int)$entity_info['id_e'], (int)$entity_info['entite_mere']);
        }
    }

    /**
     * Ancestors from the root, the entity itself excluded.
     */
    private function getStrictAncestors(int $id_e): array
    {
        $ancestor_list = $this->entiteSQL->getAncetre($id_e);
        array_pop($ancestor_list);
        return $ancestor_list;
    }
}
