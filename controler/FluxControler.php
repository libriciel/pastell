<?php

use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\ConnecteurAvailabilityService;
use Pastell\Service\Connecteur\FluxConnecteurListService;
use Pastell\Service\Connecteur\FluxInheritanceService;
use Pastell\Service\Menu\MenuGaucheService;
use Pastell\Service\Entite\EntityInfoService;
use Pastell\Service\Entite\RootEntity;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class FluxControler extends PastellControler
{
    public function _beforeAction()
    {
        parent::_beforeAction();
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');

        $this->checkDroitFor($id_e, DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
        $this->setNavigationInfo($id_e, 'Flux/index');
        $this->setEntiteMenuGauche($id_e);
        $this->setMenuGaucheSelect(MenuGaucheService::FLUX_INDEX);
        $this->setDroitViewParameter($id_e, DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @deprecated 4.1.21 Use checkDroitFor() instead
     */
    public function hasDroitEdition($id_e): void
    {
        $this->checkDroitFor($id_e, DroitService::DROIT_CONNECTEUR, DroitType::EDITION);
    }

    private function getConnecteurAssociationService(): ConnecteurAssociationService
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurAssociationService::class);
    }

    private function getFluxConnecteurListService(): FluxConnecteurListService
    {
        return $this->getObjectInstancier()->getInstance(FluxConnecteurListService::class);
    }

    /**
     * @throws NotFoundException
     */
    public function indexAction()
    {
        $id_e = $this->getGetInfo()->getInt('id_e', 0);
        $this->checkDroitFor($id_e, DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
        $this->setViewParameter('id_e', $id_e);

        if ($id_e) {
            /** @var FluxEntiteHeritageSQL $fluxEntiteHeritageSQL */
            $fluxEntiteHeritageSQL = $this->getInstance(FluxEntiteHeritageSQL::class);
            $this->setViewParameter('id_e_mere', $this->getEntiteSQL()->getEntiteMere($id_e));
            $this->setViewParameter('all_herited', $fluxEntiteHeritageSQL->hasInheritanceAllFlux($id_e));

            $fluxConnecteurListService = $this->getFluxConnecteurListService();
            $flux_list = $fluxConnecteurListService->getFluxListWithConnecteur($id_e);
            $possible_pack_list = $fluxConnecteurListService->getPossiblePackList($this->apiGet('/flux'));

            $this->setViewParameter('flux_list', $flux_list);
            $this->setViewParameter('possible_pack_list', $possible_pack_list);
            $this->setViewParameter('template_milieu', "FluxList");
        } else {
            $all_connecteur_type = $this->getConnecteurDefinitionFiles()->getAllGlobalType();
            $all_type = [];
            foreach ($all_connecteur_type as $connecteur_type) {
                try {
                    $global_connecteur = $this->getConnecteurFactory()->getGlobalConnecteur($connecteur_type);
                } catch (Throwable $e) {
                    $global_connecteur =  false;
                }
                $all_type[$connecteur_type] = $global_connecteur;
            }

            $this->setViewParameter('all_connecteur_type', $all_type);
            $this->setViewParameter('all_flux_entite', $this->getFluxEntiteSQL()->getAllWithSameType($id_e));
            if (isset($this->getViewParameterByKey('all_flux_entite')['global'])) {
                $this->setViewParameter('all_flux_global', $this->getViewParameterByKey('all_flux_entite')['global']);
            } else {
                $this->setViewParameter('all_flux_global', []);
            }
            $this->setViewParameter('template_milieu', "FluxGlobalList");
        }
        $this->setDroitViewParameter($id_e, DroitService::DROIT_CONNECTEUR, DroitType::EDITION);
        $this->setViewParameter('entite_denomination', $this->getInstance(EntityInfoService::class)->getDenomination($id_e));
        $this->setViewParameter('page_title', "{$this->getViewParameterByKey('entite_denomination')} : " . ($id_e ? 'Liste des types de dossier' : 'Associations connecteurs globaux'));

        $this->renderDefault();
    }

    public function detailAction(): void
    {
        $id_e = $this->getGetInfo()->getInt('id_e', 0);
        if ($id_e === RootEntity::ID_E) {
            $this->redirect("/Flux/index?id_e=0");
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
        $this->setViewParameter('id_e', $id_e);

        $flux = $this->getGetInfo()->get('flux', '');
        if ($flux === '') {
            $this->redirect("/Flux/index?id_e=$id_e");
        }

        $fluxEntiteHeritageSQL = $this->getInstance(FluxEntiteHeritageSQL::class);
        $this->setViewParameter('id_e_mere', $this->getEntiteSQL()->getEntiteMere($id_e));
        $this->setViewParameter('all_herited', $fluxEntiteHeritageSQL->hasInheritanceAllFlux($id_e));
        $this->setViewParameter(
            'flux_connecteur_list',
            $this->getFluxConnecteurListService()->getConnecteurList($id_e, $flux)
        );
        $this->setViewParameter('template_milieu', "FluxDetail");
        $this->setDroitViewParameter($id_e, DroitService::DROIT_CONNECTEUR, DroitType::EDITION);
        $this->setViewParameter('entite_denomination', $this->getInstance(EntityInfoService::class)->getDenomination($id_e));

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($flux);

        $this->setViewParameter('subtitle', sprintf("Type de dossier « %s » (%s)", $documentType->getName(), $documentType->getModuleId()));

        $this->setViewParameter(
            'page_title',
            sprintf(
                '%s : Association pour le type de dossier %s',
                $this->getViewParameterByKey('entite_denomination'),
                $documentType->getName()
            )
        );

        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function editionAction(): void
    {
        $id_e = $this->getGetInfo()->getInt('id_e', 0);
        $flux = $this->getGetInfo()->get('flux', '');
        $type_connecteur = $this->getGetInfo()->get('type');
        $num_same_type = $this->getGetInfo()->getInt('num_same_type');

        $this->checkDroitFor($id_e, DroitService::DROIT_CONNECTEUR, DroitType::EDITION);

        $available_connecteur_list = $this->getInstance(ConnecteurAvailabilityService::class)->getListByType(
            $this->getId_u(),
            $id_e,
            $type_connecteur,
            $id_e === RootEntity::ID_E
        );
        if ($available_connecteur_list === []) {
            $this->setLastError("Aucun connecteur « $type_connecteur » disponible !");
            $this->redirect("/Flux/index?id_e=$id_e");
        }

        $entite_denomination = $this->getInstance(EntityInfoService::class)->getDenomination($id_e);
        $connecteur_info = $this->getFluxEntiteSQL()->getConnecteur($id_e, $flux, $type_connecteur, $num_same_type);
        if ($connecteur_info) {
            $connecteur_info = RootEntity::addDenomination([$connecteur_info])[0];
        }
        $fluxDocumentType = $this->getDocumentTypeFactory()->getFluxDocumentType($flux);
        $flux_name = $flux ? $fluxDocumentType->getName() : 'global';

        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('flux', $flux);
        $this->setViewParameter('type_connecteur', $type_connecteur);
        $this->setViewParameter('num_same_type', $num_same_type);
        $this->setViewParameter('entite_denomination', $entite_denomination);
        $this->setViewParameter('connecteur_disponible', $available_connecteur_list);
        $this->setViewParameter('connecteur_info', $connecteur_info);
        $this->setViewParameter(
            'type_connecteur_info',
            $fluxDocumentType->getConnecteurInfo($type_connecteur, $num_same_type)
        );
        $this->setViewParameter('flux_name', $flux_name);
        $this->setViewParameter(
            'lien_retour',
            $flux ? "/Flux/detail?id_e=$id_e&flux=$flux" : "/Flux/index?id_e=$id_e"
        );
        $this->setViewParameter(
            'page_title',
            sprintf(
                "%s : Association d'un connecteur %s",
                $entite_denomination,
                $flux ? 'au type de dossier ' . $flux_name : 'global'
            )
        );
        $this->setViewParameter('template_milieu', 'FluxEdition');
        $this->renderDefault();
    }

    public function doEditionAction()
    {
        $id_e = $this->getPostInfo()->getInt('id_e');
        $flux = $this->getPostInfo()->get('flux');
        $type = $this->getPostInfo()->get('type');
        $id_ce = $this->getPostInfo()->getInt('id_ce');
        $num_same_type = $this->getPostInfo()->getInt('num_same_type');

        $this->checkDroitFor($id_e, DroitService::DROIT_CONNECTEUR, DroitType::EDITION);
        try {
            if ($id_ce) {
                $this->getConnecteurAssociationService()->addConnecteurAssociation(
                    $id_e,
                    $id_ce,
                    $type,
                    $this->getId_u(),
                    $flux,
                    $num_same_type
                );
                $this->setLastMessage("Connecteur associé avec succès");
            } else {
                $this->getConnecteurAssociationService()->deleteConnecteurAssociation(
                    $id_e,
                    $type,
                    $this->getId_u(),
                    $flux,
                    $num_same_type
                );
                $this->setLastMessage("Connecteur dissocié avec succès");
            }
        } catch (Exception $ex) {
            $this->setLastError($ex->getMessage());
        }
        if ($id_e) {
            $this->redirect("/Flux/detail?id_e=$id_e&flux=$flux");
        } else {
            $this->redirect("/Flux/index?id_e=$id_e");
        }
    }

    /**
     * @deprecated 4.1.26 Use FluxConnecteurListService::getConnecteurList() instead
     */
    public function getConnectorForFlux(int $id_e, string $id_flux): array
    {
        return $this->getFluxConnecteurListService()->getConnecteurList($id_e, $id_flux);
    }

    public function toogleHeritageAction()
    {
        $id_e = $this->getPostInfo()->getInt('id_e');
        $flux = $this->getPostInfo()->get('flux');
        $this->checkDroitFor($id_e, DroitService::DROIT_CONNECTEUR, DroitType::EDITION);
        $this->getInstance(FluxInheritanceService::class)->toggleInheritance($id_e, (string)$flux);
        $this->setLastMessage("L'héritage a été modifié");
        if ($flux === FluxEntiteHeritageSQL::ALL_FLUX) {
            $this->redirect("/Flux/index?id_e=$id_e");
        }
        $this->redirect("/Flux/detail?id_e=$id_e&flux=$flux");
    }
}
