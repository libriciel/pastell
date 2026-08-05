<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class FamilleConnecteurAPIController extends BaseAPIController
{
    private $connecteurDefinitionFiles;

    public function __construct(ConnecteurDefinitionFiles $connecteurDefinitionFiles)
    {
        $this->connecteurDefinitionFiles = $connecteurDefinitionFiles;
    }

    /**
     * @throws NotFoundException
     * @throws ForbiddenException
     */
    public function get()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::LECTURE);

        $famille_connecteur = $this->getFromQueryArgs(0);
        if ($famille_connecteur) {
            return $this->detail($famille_connecteur);
        }

        $global = $this->getFromRequest('global');
        if ($global) {
            return $this->connecteurDefinitionFiles->getAllGlobalType();
        }
        return $this->connecteurDefinitionFiles->getAllType();
    }

    private function detail($famille_connecteur)
    {
        $id_connecteur = $this->getFromQueryArgs(1);

        if ($id_connecteur) {
            return $this->detailConnecteur($id_connecteur);
        }

        $global = $this->getFromRequest('global');
        return $this->connecteurDefinitionFiles->getAllByFamille($famille_connecteur, $global);
    }

    private function detailConnecteur($id_connecteur)
    {
        $global = $this->getFromRequest('global');
        return $this->connecteurDefinitionFiles->getInfo($id_connecteur, $global) ?: [];
    }
}
