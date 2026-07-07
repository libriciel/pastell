<?php

use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Menu\MenuGaucheService;
use Pastell\Service\Entite\EntityUtilitiesService;
use Pastell\Service\Module\ModuleListService;

class FluxControler extends PastellControler
{
    public function _beforeAction()
    {
        parent::_beforeAction();
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');

        $this->hasConnecteurDroitLecture($id_e);
        $this->setNavigationInfo($id_e, 'Flux/index');
        $this->setEntiteMenuGauche($id_e);
        $this->setMenuGaucheSelect(MenuGaucheService::FLUX_INDEX);
        $this->setDroitLectureOnConnecteur($id_e);
        $this->setDroitsDaemon($id_e);
    }

    public function hasDroitEdition($id_e): void
    {
        $this->hasConnecteurDroitEdition($id_e);
    }

    private function getConnecteurAssociationService(): ConnecteurAssociationService
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurAssociationService::class);
    }
    private function getEntityUtilitiesService(): EntityUtilitiesService
    {
        return $this->getObjectInstancier()->getInstance(EntityUtilitiesService::class);
    }

    /**
     * @throws NotFoundException
     * @throws JsonException
     */
    public function indexAction()
    {
        $id_e = $this->getGetInfo()->getInt('id_e', 0);
        $this->hasConnecteurDroitLecture($id_e);
        $this->setViewParameter('id_e', $id_e);

        if ($id_e) {
            /** @var FluxEntiteHeritageSQL $fluxEntiteHeritageSQL */
            $fluxEntiteHeritageSQL = $this->getInstance(FluxEntiteHeritageSQL::class);
            $this->setViewParameter('id_e_mere', $this->getEntiteSQL()->getEntiteMere($id_e));
            $this->setViewParameter('all_herited', $fluxEntiteHeritageSQL->hasInheritanceAllFlux($id_e));

            $fluxList = $this->getFluxDefinitionFiles()->getAll();
            foreach ($fluxList as $fluxId => $fluxInfo) {
                $fluxList[$fluxId]['nb_connector'] = 0;
                foreach ($this->getConnectorForFlux($id_e, $fluxId) as $connectorInfo) {
                    if ($connectorInfo['connecteur_info']) {
                        $fluxList[$fluxId]['nb_connector']++;
                        $fluxList[$fluxId]['famille_associe'][$fluxList[$fluxId]['nb_connector']] =
                            $connectorInfo['connecteur_info']['type'];
                    }
                }
                unset($fluxList[$fluxId]['formulaire'], $fluxList[$fluxId]['action']);
            }

            $moduleListByType = $this->getInstance(ModuleListService::class)
                ->getModuleListOrderByType($this->getId_u());
            foreach ($moduleListByType as $fluxType => $fluxByType) {
                foreach ($fluxByType as $fluxId => $fluxNom) {
                    if (empty($fluxList[$fluxId]['connecteur'])) {
                        unset($moduleListByType[$fluxType][$fluxId]);
                    }
                }
            }
            $moduleListByType = array_filter($moduleListByType);

            $moduleTree = [];
            foreach ($moduleListByType as $type => $modules) {
                $children = [];
                foreach ($modules as $idFlux => $nom) {
                    $children[] = ['name' => $nom, 'value' => $idFlux];
                }
                $moduleTree[] = ['name' => $type, 'value' => $type, 'isGroupSelectable' => false, 'children' => $children];
            }
            $this->setViewParameter('module_treeselect_data', json_encode($moduleTree, JSON_THROW_ON_ERROR));

            foreach ($fluxList as $fluxId => $fluxInfo) {
                if ($fluxInfo['nb_connector'] === 0) {
                    unset($fluxList[$fluxId]);
                }
            }

            foreach ($fluxList as $fluxId => $fluxInfo) {
                $fluxList[$fluxId]['affiche_hover'] = false;

                if (count($fluxInfo['famille_associe']) > 4) {
                    $fluxList[$fluxId]['affiche_hover'] = true;
                    $hover = '';
                    foreach ($fluxInfo['famille_associe'] as $key => $connecteur) {
                        $hover .= $connecteur;
                        if ($key !== count($fluxInfo['famille_associe'])) {
                            $hover .= ', ';
                        }
                    }
                    $fluxList[$fluxId]['hover'] = $hover;
                }

                $fluxList[$fluxId]['famille_associe_affiche'] = '';
                foreach ($fluxInfo['famille_associe'] as $key => $connecteur) {
                    $fluxList[$fluxId]['famille_associe_affiche'] .= $connecteur;
                    if ($key !== count($fluxInfo['famille_associe'])) {
                        $fluxList[$fluxId]['famille_associe_affiche'] .= ', ';
                    }
                    if ($fluxList[$fluxId]['affiche_hover'] && $key === 4) {
                        $fluxList[$fluxId]['famille_associe_affiche'] .= '...';
                        break;
                    }
                }
            }
            $this->setViewParameter('flux_list', $fluxList);
            $this->setViewParameter('possibleFluxList', $moduleListByType);
            $this->setCanEditConnector($id_e);
            $this->setViewParameter('template_milieu', "FluxList");
        } else {
            $all_connecteur_type = $this->getConnecteurDefinitionFiles()->getAllGlobalType();
            $all_type = [];
            foreach ($all_connecteur_type as $connecteur_type) {
                try {
                    $global_connecteur = $this->getConnecteurFactory()->getGlobalConnecteur($connecteur_type);
                } catch (Exception) {
                    $global_connecteur =  false;
                }
                $all_type[$connecteur_type] = $global_connecteur;
            }

            $this->setViewParameter('all_connecteur_type', $all_type);
            $this->setViewParameter('all_flux_entite', $this->getFluxEntiteSQL()->getAllWithSameType($id_e));
            if (isset($this->getViewParameterOrObject('all_flux_entite')['global'])) {
                $this->setViewParameter('all_flux_global', $this->getViewParameterOrObject('all_flux_entite')['global']);
            } else {
                $this->setViewParameter('all_flux_global', []);
            }
            $this->setViewParameter('template_milieu', 'FluxGlobalList');
        }
        $this->setViewParameter('droit_edition', $this->getDroitService()->hasDroitConnecteurEdition($id_e, $this->getId_u()));
        $this->setViewParameter('entite_denomination', $this->getEntiteSQL()->getDenomination($this->getViewParameterOrObject('id_e')));
        $this->setViewParameter('page_title', "{$this->getViewParameterOrObject('entite_denomination')} : " . ($id_e ? 'Liste des types de dossier' : 'Associations connecteurs globaux'));

        $this->renderDefault();
    }

    public function detailAction(): void
    {
        $id_e = $this->getGetInfo()->getInt('id_e', 0);
        if ($id_e === 0) {
            $this->redirect("/Flux/index?id_e=0");
        }
        $this->hasConnecteurDroitLecture($id_e);
        $this->setViewParameter('id_e', $id_e);

        $flux = $this->getGetInfo()->get('flux', '');
        if ($flux === '') {
            $this->redirect("/Flux/index?id_e=$id_e");
        }

        $fluxEntiteHeritageSQL = $this->getInstance(FluxEntiteHeritageSQL::class);
        $this->setViewParameter('id_e_mere', $this->getEntiteSQL()->getEntiteMere($id_e));
        $this->setViewParameter('all_herited', $fluxEntiteHeritageSQL->hasInheritanceAllFlux($id_e));
        $this->setViewParameter('flux_connecteur_list', $this->getConnectorForFlux($id_e, $flux));
        $this->setViewParameter('template_milieu', "FluxDetail");
        $this->setViewParameter('droit_edition', $this->getDroitService()->hasDroitConnecteurEdition($id_e, $this->getId_u()));
        $this->setViewParameter('entite_denomination', $this->getEntiteSQL()->getDenomination($id_e));

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
     * @throws NotFoundException
     */
    public function editionAction()
    {
        $id_e = $this->getGetInfo()->getInt('id_e', 0);
        $flux = $this->getGetInfo()->get('flux', '');
        $type_connecteur = $this->getGetInfo()->get('type');
        $num_same_type = $this->getGetInfo()->getInt('num_same_type');

        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('flux', $flux);
        $this->setViewParameter('type_connecteur', $type_connecteur);
        $this->setViewParameter('num_same_type', $num_same_type);

        $this->hasDroitEdition($id_e);
        $entite_denomination = $this->getEntiteSQL()->getDenomination($id_e);
        $this->setViewParameter('entite_denomination', $entite_denomination);
        $this->setViewParameter('connecteur_disponible', $this->getConnecteurDispo($id_e, $type_connecteur));
        $this->setViewParameter('connecteur_info', $this->getEntityUtilitiesService()
            ->addDenominationForEntiteRacine(
                [$this->getFluxEntiteSQL()->getConnecteur($id_e, $flux, $type_connecteur, $num_same_type)]
            )[0]);

        $all_info = $this->getDocumentTypeFactory()->getFluxDocumentType($flux)->getConnecteurAllInfo();
        $type_connecteur_info = [];
        foreach ($all_info as $connecteur_info) {
            if ($connecteur_info['connecteur_id'] === $type_connecteur) {
                if ($connecteur_info['num_same_type'] === $num_same_type) {
                    $type_connecteur_info = $connecteur_info;
                    break;
                }
            }
        }
        $this->setViewParameter('type_connecteur_info', $type_connecteur_info);

        $flux_name = $flux ? $this->getDocumentTypeFactory()->getFluxDocumentType($flux)->getName() : 'global';
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

    private function getConnecteurDispo($id_e, $type_connecteur)
    {
        /** @var ConnecteurDisponible $connecteurDisponible */
        $connecteurDisponible = $this->getInstance(ConnecteurDisponible::class);

        $connecteur_disponible = $connecteurDisponible->getListByType(
            $this->getId_u(),
            $id_e,
            $type_connecteur,
            $id_e === 0
        );

        if (! $connecteur_disponible) {
            $this->setLastError("Aucun connecteur « $type_connecteur » disponible !");
            $this->redirect("/Flux/index?id_e=$id_e");
        } // @codeCoverageIgnore

        return $connecteur_disponible;
    }

    public function doEditionAction()
    {
        $id_e = $this->getPostInfo()->getInt('id_e');
        $flux = $this->getPostInfo()->get('flux');
        $type = $this->getPostInfo()->get('type');
        $id_ce = $this->getPostInfo()->getInt('id_ce');
        $num_same_type = $this->getPostInfo()->getInt('num_same_type');

        $this->hasDroitEdition($id_e);
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

    public function getConnectorForFlux(int $id_e, string $id_flux): array
    {
        $fluxEntiteHeritageSQL = $this->getObjectInstancier()->getInstance(FluxEntiteHeritageSQL::class);
        $all_flux_entite = $fluxEntiteHeritageSQL->getAllWithSameType($id_e);
        $result = [];
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($id_flux);
        foreach ($documentType->getConnecteurAllInfo() as $j => $connecteur_type_info) {
            $connecteur_id = $connecteur_type_info[DocumentType::CONNECTEUR_ID];
            $line = [];
            $line['nb_connecteur'] = count($documentType->getConnecteur());
            $line['num_connecteur'] = $j;
            $line['id_flux'] = $id_flux;
            $line['nom_flux'] = $documentType->getName();
            $line['connecteur_type'] = $connecteur_id;
            $line[DocumentType::CONNECTEUR_WITH_SAME_TYPE] =
                $connecteur_type_info[DocumentType::CONNECTEUR_WITH_SAME_TYPE];
            $line[DocumentType::NUM_SAME_TYPE] = $connecteur_type_info[DocumentType::NUM_SAME_TYPE];
            $line['inherited_flux'] = false;
            if (isset($all_flux_entite[$id_flux][$connecteur_id][$line[DocumentType::NUM_SAME_TYPE]])) {
                $line['connecteur_info'] = $this->getEntityUtilitiesService()
                    ->addDenominationForEntiteRacine(
                        [$all_flux_entite[$id_flux][$connecteur_id][$line[DocumentType::NUM_SAME_TYPE]]]
                    )[0];
            } else {
                $line['connecteur_info'] = false;
            }
            if (isset($all_flux_entite[$id_flux]['inherited_flux'])) {
                $line['inherited_flux'] = $all_flux_entite[$id_flux]['inherited_flux'];
            }
            $result[] = $line;
        }
        return $result;
    }

    public function toogleHeritageAction()
    {
        /** @var FluxEntiteHeritageSQL $fluxEntiteHeritageSQL */
        $fluxEntiteHeritageSQL = $this->getInstance(FluxEntiteHeritageSQL::class);

        $id_e = $this->getPostInfo()->getInt('id_e');
        $flux = $this->getPostInfo()->get('flux');
        $this->hasDroitEdition($id_e);
        $fluxEntiteHeritageSQL->toogleInheritance($id_e, $flux);
        $this->setLastMessage("L'héritage a été modifié");
        if ($flux === FluxEntiteHeritageSQL::ALL_FLUX) {
            $this->redirect("/Flux/index?id_e=$id_e");
        }
        $this->redirect("/Flux/detail?id_e=$id_e&flux=$flux");
    }
}
