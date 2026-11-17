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

    public function getAncestorIds(int $id_e): array
    {
        $ancestor_list = $this->getStrictAncestors($id_e);
        $ancestor_ids = [RootEntity::ID_E];
        foreach ($ancestor_list as $ancestor_info) {
            $ancestor_ids[] = $ancestor_info['id_e'];
        }
        return $ancestor_ids;
    }

    public function getAllChildren(int $id_e): array
    {
        $children = $this->collectChildren($id_e);
        usort($children, static fn (array $a, array $b): int => strcasecmp($a['denomination'], $b['denomination']));
        return $children;
    }

    public function getAncestorDenominations(int $id_e): array
    {
        $denomination_list = [];
        while ($id_e !== RootEntity::ID_E) {
            $entity_info = $this->entiteSQL->getInfo($id_e);
            if (!$entity_info) {
                break;
            }
            $denomination_list[] = $entity_info['denomination'];
            $id_e = (int)$entity_info['entite_mere'];
        }
        $denomination_list[] = RootEntity::DENOMINATION;
        return array_reverse($denomination_list);
    }

    public function getNavigationAncestors(int $id_e, array $allowed_id_e_list): array
    {
        $ancestor_list = $this->getStrictAncestors($id_e);

        if (in_array(RootEntity::ID_E, $allowed_id_e_list)) {
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
            $id_e !== RootEntity::ID_E
            || !$allowed_id_e_list
            || $allowed_id_e_list[0] == RootEntity::ID_E
        ) {
            return $this->entiteSQL->getActiveChildrenByType(
                $id_e,
                [EntityType::COLLECTIVITE->value, EntityType::CENTRE_DE_GESTION->value]
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

    public function updateAncestors(int $id_e, int $parent_id_e = RootEntity::ID_E): void
    {
        $this->entiteSQL->deleteAncestors($id_e);
        $level = 0;
        $this->entiteSQL->insertAncestor($id_e, $id_e, $level++);
        while ($parent_id_e !== RootEntity::ID_E) {
            $this->entiteSQL->insertAncestor($parent_id_e, $id_e, $level++);
            $parent_id_e = (int)$this->entiteSQL->getEntiteMere($parent_id_e);
        }
        $this->entiteSQL->insertAncestor(RootEntity::ID_E, $id_e, $level);
    }

    public function updateAllAncestors(): void
    {
        $this->entiteSQL->deleteAllAncestors();
        $this->entiteSQL->insertAncestor(RootEntity::ID_E, RootEntity::ID_E, 0);
        foreach ($this->entiteSQL->getAll() as $entity_info) {
            $this->updateAncestors((int)$entity_info['id_e'], (int)$entity_info['entite_mere']);
        }
    }

    private function collectChildren(int $id_e): array
    {
        $children = $this->entiteSQL->getFille($id_e);
        foreach ($children as $child_info) {
            $children = array_merge($children, $this->collectChildren((int)$child_info['id_e']));
        }
        return $children;
    }

    private function getStrictAncestors(int $id_e): array
    {
        $ancestor_list = $this->entiteSQL->getAncetre($id_e);
        array_pop($ancestor_list);
        return $ancestor_list;
    }
}
