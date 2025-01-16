<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

use EntiteSQL;

final class EntityUtilitiesService
{
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
