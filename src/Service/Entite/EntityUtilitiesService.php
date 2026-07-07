<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

use EntiteSQL;

final class EntityUtilitiesService
{
    public function buildEntityTree(array $flatList): array
    {
        $hierarchy = [];

        foreach ($flatList as $entry) {
            $depth = $entry['profondeur'];
            $node = ['id_e' => $entry['id_e'], 'denomination' => $entry['denomination'], 'profondeur' => $depth];

            if ($depth === 0) {
                $hierarchy[] = $node;
            } else {
                $parent = &$hierarchy;
                for ($i = 0; $i < $depth; ++$i) {
                    $parent = &$parent[\count($parent) - 1]['children'];
                }
                $parent[] = $node;
                unset($parent);
            }
        }

        return $hierarchy;
    }

    public function toTreeselectOptions(array $tree): array
    {
        return array_map(function (array $node): array {
            $option = ['name' => $node['denomination'], 'value' => (string) $node['id_e']];
            if (isset($node['children'])) {
                $option['children'] = $this->toTreeselectOptions($node['children']);
            }
            return $option;
        }, $tree);
    }

    public function addDenominationForEntiteRacine(
        array $listWithEntities
    ): array {
        $arrayFixed = [];
        foreach ($listWithEntities as $elementWithEntity) {
            if (isset($elementWithEntity['id_e']) && $elementWithEntity['id_e'] === EntiteSQL::ID_E_ENTITE_RACINE) {
                $elementWithEntity['denomination'] = EntiteSQL::ENTITE_RACINE_DENOMINATION;
            }
            $arrayFixed[] = $elementWithEntity;
        }
        return $arrayFixed;
    }
}
