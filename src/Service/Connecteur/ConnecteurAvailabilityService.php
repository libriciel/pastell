<?php

declare(strict_types=1);

namespace Pastell\Service\Connecteur;

use ConnecteurEntiteSQL;
use NotFoundException;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Entite\EntityHierarchyService;
use Pastell\Service\Entite\EntityInfoService;

final class ConnecteurAvailabilityService
{
    public function __construct(
        private readonly EntityHierarchyService $entityHierarchyService,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly DroitService $droitService,
        private readonly EntityInfoService $entityInfoService,
    ) {
    }

    /**
     * @throws NotFoundException
     */
    public function getListByType(int $id_u, int $id_e, string $type, bool $global = false): array
    {
        $ancestor_list = $this->entityHierarchyService->getAncestorIds($id_e);
        if ($id_e === 0) {
            array_shift($ancestor_list);
        }
        $ancestor_list[] = $id_e;
        $result = [];

        foreach (array_reverse($ancestor_list) as $ancestor_id_e) {
            if (
                $this->droitService->hasDroitFor(
                    $id_u,
                    $ancestor_id_e,
                    DroitService::DROIT_CONNECTEUR,
                    DroitType::EDITION
                )
            ) {
                $result = array_merge(
                    $result,
                    $this->entityInfoService->addRootEntityDenomination(
                        $this->connecteurEntiteSQL->getDisponible($ancestor_id_e, $type, $global)
                    )
                );
            }
        }
        return $this->droitService->clearRestrictedConnecteur($result);
    }
}
