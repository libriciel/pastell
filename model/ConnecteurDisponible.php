<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Entite\EntityUtilitiesService;

class ConnecteurDisponible
{
    public function __construct(
        private readonly EntiteSQL $entiteSQL,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly DroitService $droitService,
        private readonly EntityUtilitiesService $entityUtilitiesService,
    ) {
    }

    /**
     * Liste des connecteurs disponibles de type globaux ou d'entité pour id_e avec les droits de id_u
     * @throws NotFoundException
     */
    public function getListByType(int $id_u, int $id_e, string $type, bool $global = false): array
    {
        $ancetre = $this->entiteSQL->getAncetreId($id_e);
        if ($id_e === 0) {
            array_shift($ancetre);
        }
        $ancetre[] = $id_e;
        $ancetre = array_reverse($ancetre);
        $result = [];

        foreach ($ancetre as $entite_id_e) {
            if ($this->droitService->hasDroitFor($id_u, $entite_id_e, DroitService::DROIT_CONNECTEUR, DroitType::EDITION)) {
                $listDisponible = $this->entityUtilitiesService->addDenominationForEntiteRacine(
                    $this->connecteurEntiteSQL->getDisponible($entite_id_e, $type, $global)
                );
                $result = array_merge($result, $listDisponible);
            }
        }
        return $this->droitService->clearRestrictedConnecteur($result);
    }
}
