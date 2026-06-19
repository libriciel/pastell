<?php

use Pastell\Service\Droit\DroitService;

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
     */
    public function display()
    {
        $this->setViewParameter('entityList', $this->objectInstancier
            ->getInstance(RoleUtilisateur::class)
            ->getArbreFilleWithRacine($this->id_u, DroitService::getDroitEdition(DroitService::DROIT_ENTITE)));

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
