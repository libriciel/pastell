<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

/**
 * @deprecated 4.1.26 Use EntityInfoService instead
 */
final class EntityUtilitiesService
{
    public function __construct(
        private readonly EntityInfoService $entityInfoService,
    ) {
    }

    /**
     * @deprecated 4.1.26 Use EntityInfoService::addRootEntityDenomination() instead
     */
    public function addDenominationForEntiteRacine(
        array $listWithEntities
    ): array {
        return $this->entityInfoService->addRootEntityDenomination($listWithEntities);
    }
}
