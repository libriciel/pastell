<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

use EntiteSQL;
use Pastell\Helpers\ArrayHelper;

final class EntityUtilitiesService
{
    public function buildEntityTreeselectOptions(array $flatList): array
    {
        return $this->toTreeselectOptions(ArrayHelper::buildNestedTree($flatList));
    }

    private function toTreeselectOptions(array $tree): array
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
