<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Entite\EntityUtilitiesService;

class GetEntityList extends ConnecteurTypeChoiceActionExecutor
{
    private const ENTITY_ID = 'entity_id';
    private const ENTITY_LABEL = 'entity_label';
    private const PAGE_TITLE = 'page_title';

    /**
     * @return bool
     */
    public function go()
    {
        $entityId = $this->getRecuperateur()->getInt(self::ENTITY_ID);
        if ($entityId === EntiteSQL::ID_E_ENTITE_RACINE) {
            $entityLabel = EntiteSQL::ENTITE_RACINE_DENOMINATION;
        } else {
            $entityLabel = $this->objectInstancier->getInstance(EntiteSQL::class)->getDenomination($entityId);
        }
        $this->getConnecteurProperties()->setData($this->getMappingValue(self::ENTITY_LABEL), $entityLabel);
        $this->getConnecteurProperties()->setData($this->getMappingValue(self::ENTITY_ID), $entityId);
        return true;
    }

    /**
     * @throws NotFoundException
     * @throws JsonException
     */
    public function display()
    {
        $entityUtilitiesService = $this->objectInstancier->getInstance(EntityUtilitiesService::class);
        $arbreFille = $this->objectInstancier->getInstance(RoleUtilisateur::class)->getArbreFilleWithRacine(
            $this->id_u,
            DroitService::getDroitEdition(DroitService::DROIT_ENTITE)
        );
        $tree = $entityUtilitiesService->toTreeselectOptions($entityUtilitiesService->buildEntityTree($arbreFille));
        $this->setViewParameter('entity_treeselect_data', json_encode($tree, JSON_THROW_ON_ERROR));

        $this->setViewParameter(
            'selectedEntity',
            $this->getConnecteurProperties()->get($this->getMappingValue(self::ENTITY_ID))
        );
        $this->renderPage(
            $this->getMappingValue(self::PAGE_TITLE),
            'connectorType/utilities/GetEntityList'
        );
        return true;
    }

    public function displayAPI()
    {
        return [];
    }
}
