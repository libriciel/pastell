<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

/**
 * @deprecated 4.1.26 Use RootEntity instead
 */
final class EntityUtilitiesService
{
    /**
     * @deprecated 4.1.26 Use RootEntity::addDenomination() instead
     */
    public function addDenominationForEntiteRacine(
        array $listWithEntities
    ): array {
        return RootEntity::addDenomination($listWithEntities);
    }
}
