<?php

use Pastell\Service\TypeDossier\TypeDossierDeletionService;
use Pastell\Service\TypeDossier\TypeDossierEditionService;
use Pastell\Service\TypeDossier\TypeDossierExportService;
use Pastell\Service\TypeDossier\TypeDossierImportService;
use Pastell\Service\TypeDossier\TypeDossierUtilService;
use Pastell\Service\TypeDossier\TypeDossierManager;
use Pastell\Service\TypeDossier\TypeDossierActionService;
use Pastell\Service\Menu\MenuGaucheService;

class TypeDossierControler extends PastellControler
{
    public function _beforeAction()
    {
        parent::_beforeAction();
        $this->setViewParameter('menu', $this->getInstance(MenuGaucheService::class)->getConfigurationMenu());
        $this->setMenuGaucheSelect(MenuGaucheService::TYPE_DOSSIER_LIST);
        $this->verifDroit(0, "system:lecture");
        $this->setViewParameter('dont_display_breacrumbs', true);
    }

    private function commonEdition()
    {
        $this->verifDroit(0, "system:edition");
        $this->setViewParameter('id_t', $this->getPostOrGetInfo()->getInt('id_t'));
        $this->setViewParameter('type_de_dossier_info', $this->getTypeDossierSQL()->getInfo($this->getViewParameterOrObject('id_t')));
        $this->setViewParameter('type_dossier_hash', $this->getTypeDossierActionService()->getLastHash($this->getViewParameterOrObject('id_t')));
        $this->setViewParameter('typeDossierProperties', $this->getTypeDossierManager()->getTypeDossierProperties($this->getViewParameterOrObject('id_t')));
        $this->setViewParameter('page_title', "Type de dossier personnalisé {$this->getViewParameterOrObject('type_de_dossier_info')['id_type_dossier']}");
        $this->setViewParameter('id_type_dossier', $this->getViewParameterOrObject('type_de_dossier_info')['id_type_dossier']);

        $typeDossierEtape = $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);
        $this->setViewParameter('all_etape_type', $typeDossierEtape->getAllType());
    }

    /**
     * @return TypeDossierSQL
     */
    private function getTypeDossierSQL()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierSQL::class);
    }

    /**
     * @return TypeDossierService
     */
    private function getTypeDossierService()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierService::class);
    }

    /**
     * @return TypeDossierManager
     */
    private function getTypeDossierManager()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierManager::class);
    }

    private function getTypeDossierEtapeManager(): TypeDossierEtapeManager
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);
    }

    /**
     * @return TypeDossierEditionService
     */
    private function getTypeDossierEditionService()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
    }

    /**
     * @return TypeDossierEtapeManager
     */
    private function getTypeDossierEtapeDefinition()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);
    }

    /**
     * @return TypeDossierActionService
     */
    private function getTypeDossierActionService(): TypeDossierActionService
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierActionService::class);
    }

    /**
     * @throws NotFoundException
     */
    public function listAction()
    {
        $this->setViewParameter('type_dossier_list', $this->getTypeDossierSQL()->getAll());
        $this->setViewParameter('droit_edition', $this->hasDroit(0, "system:edition"));
        $this->setViewParameter('page_title', "Types de dossier personnalisés");
        $this->setViewParameter('template_milieu', "TypeDossierList");
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function editionAction()
    {
        $this->verifDroit(0, "system:edition");
        $id_t = $this->getPostOrGetInfo()->getInt('id_t');
        $this->setViewParameter('flux_info', $this->getTypeDossierSQL()->getInfo($id_t));

        if ($this->getViewParameterByKey('flux_info')) {
            $id_type_dossier = $this->getViewParameterByKey('flux_info')['id_type_dossier'];

            if ($this->getDocumentSQL()->isTypePresent($id_type_dossier)) {
                $this->setLastError(
                    "Des dossiers du type <b>$id_type_dossier</b> existent déjà sur ce Pastell. Impossible de modifier l'identifiant."
                );
                $this->redirect("/TypeDossier/list");
            }
        }

        $this->setViewParameter('page_title', "Création d'un type de dossier personnalisé");
        $this->setViewParameter('template_milieu', "TypeDossierEdition");
        $this->renderDefault();
    }

    /**
     * @throws Exception
     */
    public function doEditionAction()
    {
        $this->verifDroit(0, "system:edition");

        $target_type_dossier_id = $this->getPostOrGetInfo()->get('id_type_dossier');
        $id_t = $this->getPostOrGetInfo()->getInt('id_t');
        $is_new = ! $id_t;

        $typeDossierEditionService = $this->getTypeDossierEditionService();
        try {
            $typeDossierEditionService->checkTypeDossierId($target_type_dossier_id);
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/TypeDossier/list");
        }

        $typeDossierProperties = $this->getTypeDossierManager()->getTypeDossierProperties($id_t);
        if (! $is_new) {
            $source_type_dossier_id = $typeDossierProperties->id_type_dossier;
            $this->verifyTypeDossierIsUnused($source_type_dossier_id);
            $typeDossierEditionService->renameTypeDossierId($source_type_dossier_id, $target_type_dossier_id);
        }
        $typeDossierProperties->id_type_dossier = $target_type_dossier_id;
        try {
            $id_t = $typeDossierEditionService->edit($id_t, $typeDossierProperties);
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/TypeDossier/list");
        }

        if ($is_new) {
            $typeDossierEditionService->editLibelleInfo(
                $id_t,
                $target_type_dossier_id,
                TypeDossierUtilService::TYPE_DOSSIER_CLASSEMENT_DEFAULT,
                "",
                "onglet1",
                'false'
            );
            $message = "Le type de dossier personnalisé $target_type_dossier_id a été créé";
            $typeDosssierAction = TypeDossierActionService::ACTION_AJOUTE;
        } else {
            $message = "Modification de l'identifiant du type de dossier personnalisé $target_type_dossier_id";
            $typeDosssierAction = TypeDossierActionService::ACTION_MODIFFIE;
        }
        $this->getTypeDossierActionService()->add(
            $this->getId_u(),
            $id_t,
            $typeDosssierAction,
            $message
        );
        $this->setLastMessage($message);

        $this->redirect("/TypeDossier/detail?id_t=$id_t");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function deleteAction()
    {
        $this->commonEdition();

        $id_type_dossier = $this->getViewParameterOrObject('type_de_dossier_info')['id_type_dossier'];
        $this->verifyTypeDossierIsUnused($id_type_dossier);

        $this->setViewParameter('template_milieu', "TypeDossierDelete");
        $this->renderDefault();
    }

    /**
     * @param $id_type_dossier
     * @throws LastErrorException
     * @throws LastMessageException
     */
    private function verifyTypeDossierIsUnused($id_type_dossier)
    {
        $this->verifyNoDocumentIsUsingTypeDossier($id_type_dossier);
        $this->verifyNoRoleIsUsingTypeDossier($id_type_dossier);
        $this->verifyNoConnectorIsAssociatedToTypeDossier($id_type_dossier);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws TypeDossierException
     */
    public function doDeleteAction()
    {
        $this->commonEdition();
        $id_type_dossier = $this->getViewParameterOrObject('type_de_dossier_info')['id_type_dossier'];
        $this->verifyTypeDossierIsUnused($id_type_dossier);

        $this->getObjectInstancier()->getInstance(TypeDossierDeletionService::class)->delete($this->getViewParameterOrObject('id_t'));

        $this->setLastMessage("Le type de dossier <b>{$this->getViewParameterOrObject('id_type_dossier')}</b> a été supprimé");
        $this->redirect("/TypeDossier/list");
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function detailAction()
    {
        $this->commonEdition();
        $this->setViewParameter('csrfToken', $this->getObjectInstancier()->getInstance(CSRFToken::class));
        $this->setViewParameter('template_milieu', 'TypeDossierDetail');
        $this->setViewParameter('fieldsFromEtape', $this->getFieldsFromEtape());
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function etatAction()
    {
        $this->verifDroit(0, "system:edition");
        $this->setViewParameter('id_t', $this->getPostOrGetInfo()->getInt('id_t'));
        $this->setViewParameter(
            'page_title',
            sprintf(
                'États du type de dossier personnalisé %s',
                $this->getTypeDossierSQL()->getInfo($this->getViewParameterByKey('id_t'))['id_type_dossier']
            )
        );
        $this->setViewParameter('offset', $this->getPostOrGetInfo()->get('offset', 0));
        $this->setViewParameter('limit', 10);
        $this->setViewParameter(
            'count',
            $this->getTypeDossierActionService()->countById($this->getViewParameterByKey('id_t'))
        );
        $this->setViewParameter(
            'typeDossierAction',
            $this->getTypeDossierActionService()->getById(
                $this->getViewParameterByKey('id_t'),
                $this->getViewParameterByKey('offset'),
                $this->getViewParameterByKey('limit')
            )
        );

        $this->setViewParameter('template_milieu', "TypeDossierEtat");
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     */
    public function editionLibelleAction()
    {
        $this->commonEdition();
        $this->setViewParameter('template_milieu', "TypeDossierEditionLibelle");
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doEditionLibelleAction()
    {
        $this->commonEdition();
        $nom = $this->getPostOrGetInfo()->get('nom');
        $type = $this->getPostOrGetInfo()->get('type');
        $description = $this->getPostOrGetInfo()->get('description');
        $nom_onglet = $this->getPostOrGetInfo()->get('nom_onglet');
        $affiche_one = $this->getPostOrGetInfo()->get('affiche_one');
        try {
            $this->getTypeDossierEditionService()->editLibelleInfo($this->getViewParameterOrObject('id_t'), $nom, $type, $description, $nom_onglet, $affiche_one);
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/TypeDossier/editionLibelle?id_t={$this->getViewParameterOrObject('id_t')}");
        }
        $message = "La modification des informations sur le type de dossier a été enregistrée";
        $this->getTypeDossierActionService()->add($this->getId_u(), $this->getViewParameterOrObject('id_t'), TypeDossierActionService::ACTION_MODIFFIE, $message);
        $this->setLastMessage($message);
        $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterOrObject('id_t')}");
    }

    /**
     * @throws NotFoundException
     */
    public function editionElementAction()
    {
        $this->commonEdition();
        $element_id = $this->getPostOrGetInfo()->get('element_id');
        $this->setViewParameter('formulaireElement', $this->getTypeDossierService()->getFormulaireElement($this->getViewParameterOrObject('id_t'), $element_id));
        $this->setViewParameter('template_milieu', "TypeDossierEditionElement");
        $this->renderDefault();
    }

    /**
     * @throws Exception
     */
    public function doEditionElementAction(): void
    {
        $this->commonEdition();
        $id_type_dossier = $this->getViewParameterByKey('type_de_dossier_info')['id_type_dossier'];
        $this->verifyNoDocumentIsUsingTypeDossier($id_type_dossier, '/TypeDossier/detail?id_t=' . $this->getViewParameterByKey('id_t'));
        try {
            $this->getTypeDossierService()->editionElement($this->getViewParameterByKey('id_t'), $this->getPostOrGetInfo());
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterByKey('id_t')}");
        }
        $message = "La modification d'éléments du formulaire a été enregistrée";
        $this->getTypeDossierActionService()->add($this->getId_u(), $this->getViewParameterByKey('id_t'), TypeDossierActionService::ACTION_MODIFFIE, $message);
        $this->setLastMessage($message);
        $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterByKey('id_t')}");
    }


    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function deleteElementAction()
    {
        $this->commonEdition();
        $id_type_dossier = $this->getViewParameterOrObject('type_de_dossier_info')['id_type_dossier'];
        $this->verifyNoDocumentIsUsingTypeDossier($id_type_dossier, '/TypeDossier/detail?id_t=' . $this->getViewParameterOrObject('id_t'));
        $element_id = $this->getPostOrGetInfo()->get('element_id');
        try {
            $this->getTypeDossierService()->deleteElement($this->getViewParameterOrObject('id_t'), $element_id);
        } catch (Exception $e) {
            $this->setLastMessage($e->getMessage());
            $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterOrObject('id_t')}");
        }
        $message = "La modification d'éléments du formulaire a été enregistrée";
        $this->getTypeDossierActionService()->add($this->getId_u(), $this->getViewParameterOrObject('id_t'), TypeDossierActionService::ACTION_MODIFFIE, $message);
        $this->setLastMessage($message);
        $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterOrObject('id_t')}");
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function editionEtapeAction(): void
    {
        $this->commonEdition();
        $num_etape = $this->getPostOrGetInfo()->get('num_etape', 0);

        $this->setViewParameter(
            'file_field_list',
            $this->getTypeDossierService()->getFieldWithType($this->getViewParameterByKey('id_t'), 'file')
        );
        $this->setViewParameter(
            'multi_file_field_list',
            $this->getTypeDossierService()->getFieldWithType($this->getViewParameterByKey('id_t'), 'multi_file')
        );
        $this->setViewParameter(
            'text_field_list',
            $this->getTypeDossierService()->getFieldWithType($this->getViewParameterByKey('id_t'), 'text')
        );
        $this->setViewParameter(
            'textarea_field_list',
            $this->getTypeDossierService()->getFieldWithType($this->getViewParameterByKey('id_t'), 'textarea')
        );

        $this->setViewParameter(
            'etapeInfo',
            $this->getTypeDossierService()->getEtapeInfo($this->getViewParameterByKey('id_t'), $num_etape)
        );
        $this->setViewParameter(
            'formulaire_etape',
            $this->getTypeDossierEtapeDefinition()->getFormulaireConfigurationEtape(
                $this->getViewParameterByKey('etapeInfo')->type
            )
        );

        $this->setViewParameter('template_milieu', 'TypeDossierEditionEtape');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doEditionEtapeAction()
    {
        $this->commonEdition();
        $id_type_dossier = $this->getViewParameterOrObject('type_de_dossier_info')['id_type_dossier'];
        $this->verifyNoDocumentIsUsingTypeDossier($id_type_dossier, '/TypeDossier/detail?id_t=' . $this->getViewParameterOrObject('id_t'));
        try {
            $this->getTypeDossierService()->editionEtape($this->getViewParameterOrObject('id_t'), $this->getPostOrGetInfo());
        } catch (Exception $e) {
            $this->setLastMessage($e->getMessage());
            $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterOrObject('id_t')}");
        }
        $message = "La modification des étapes du cheminement a été enregistrée";
        $this->getTypeDossierActionService()->add($this->getId_u(), $this->getViewParameterOrObject('id_t'), TypeDossierActionService::ACTION_MODIFFIE, $message);
        $this->setLastMessage($message);
        $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterOrObject('id_t')}");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function deleteEtapeAction()
    {
        $this->commonEdition();
        $id_type_dossier = $this->getViewParameterOrObject('type_de_dossier_info')['id_type_dossier'];
        $this->verifyNoDocumentIsUsingTypeDossier($id_type_dossier, '/TypeDossier/detail?id_t=' . $this->getViewParameterOrObject('id_t'));
        $num_etape = $this->getPostOrGetInfo()->getInt('num_etape');
        try {
            $this->getTypeDossierService()->deleteEtape($this->getViewParameterOrObject('id_t'), $num_etape);
        } catch (Exception $e) {
            $this->setLastMessage($e->getMessage());
            $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterOrObject('id_t')}");
        }
        $message = "La modification des étapes du cheminement a été enregistrée";
        $this->getTypeDossierActionService()->add($this->getId_u(), $this->getViewParameterOrObject('id_t'), TypeDossierActionService::ACTION_MODIFFIE, $message);
        $this->setLastMessage($message);
        $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterOrObject('id_t')}");
    }

    /**
     * @throws Exception
     */
    public function sortElementAction()
    {
        $this->commonEdition();
        $tr = $this->getPostInfo()->get("tr");
        $this->getTypeDossierService()->sortElement($this->getViewParameterOrObject('id_t'), $tr);
        $message = "L'ordre des éléments du formulaire a été modifié";
        $this->getTypeDossierActionService()->add($this->getId_u(), $this->getViewParameterOrObject('id_t'), TypeDossierActionService::ACTION_MODIFFIE, $message);
        print_r($tr);
        echo "OK";
    }

    /**
     * @throws Exception
     */
    public function sortEtapeAction()
    {
        $this->commonEdition();
        $id_type_dossier = $this->getViewParameterOrObject('type_de_dossier_info')['id_type_dossier'];
        $this->verifyNoDocumentIsUsingTypeDossier($id_type_dossier);
        $tr = $this->getPostInfo()->get("tr");
        $this->getTypeDossierService()->sortEtape($this->getViewParameterOrObject('id_t'), $tr);
        $message = "L'ordre des étapes du cheminement a été modifié";
        $this->getTypeDossierActionService()->add($this->getId_u(), $this->getViewParameterOrObject('id_t'), TypeDossierActionService::ACTION_MODIFFIE, $message);

        print_r($tr);
        echo "OK";
    }

    /**
     * @throws NotFoundException
     */
    public function newEtapeAction()
    {
        $this->commonEdition();
        $this->setViewParameter('template_milieu', "TypeDossierNewEtape");
        $this->setViewParameter('etapeInfo', $this->getTypeDossierService()->getEtapeInfo($this->getViewParameterOrObject('id_t'), "new"));
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function doNewEtapeAction(): void
    {
        $this->commonEdition();
        $id_type_dossier = $this->getViewParameterByKey('type_de_dossier_info')['id_type_dossier'];
        $this->verifyNoDocumentIsUsingTypeDossier(
            $id_type_dossier,
            '/TypeDossier/detail?id_t=' . $this->getViewParameterByKey('id_t')
        );
        $num_etape = 0;
        try {
            $num_etape = $this->getTypeDossierService()->newEtape(
                $this->getViewParameterByKey('id_t'),
                $this->getPostOrGetInfo()
            );
        } catch (Exception $e) {
            $this->setLastMessage($e->getMessage());
            $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterByKey('id_t')}");
        }

        $etapeInfo = $this->getTypeDossierService()->getEtapeInfo($this->getViewParameterByKey('id_t'), $num_etape);
        if (!array_key_exists($etapeInfo->type, $this->getTypeDossierEtapeManager()->getAllType())) {
            throw new \RuntimeException("Type d'étape inconnu");
        }
        $message = 'La modification des étapes du cheminement a été enregistrée';
        if ($etapeInfo->specific_type_info) {
            $this->getTypeDossierActionService()->add(
                $this->getId_u(),
                $this->getViewParameterByKey('id_t'),
                TypeDossierActionService::ACTION_MODIFFIE,
                $message
            );
            $this->setLastMessage("L'étape a été créée. Veuillez saisir les propriétés spécifiques de l'étape.");
            $this->redirect(
                "/TypeDossier/editionEtape?id_t={$this->getViewParameterByKey('id_t')}&num_etape=$num_etape"
            );
        }
        $this->getTypeDossierActionService()->add(
            $this->getId_u(),
            $this->getViewParameterByKey('id_t'),
            TypeDossierActionService::ACTION_MODIFFIE,
            $message
        );
        $this->setLastMessage("L'étape a été créée.");
        $this->redirect("/TypeDossier/detail?id_t={$this->getViewParameterByKey('id_t')}");
    }

    public function exportAction()
    {
        $id_t = $this->getPostOrGetInfo()->getInt('id_t');
        $type_dossier_info = $this->getTypeDossierSQL()->getInfo($id_t);
        $typeDossierExportService = $this->getObjectInstancier()->getInstance(TypeDossierExportService::class);
        $data_to_send = $typeDossierExportService->export($id_t);
        $sendFileToBrowser = $this->getObjectInstancier()->getInstance(SendFileToBrowser::class);
        $sendFileToBrowser->sendData($data_to_send, $type_dossier_info['id_type_dossier'] . ".json", "application/json");
    }

    /**
     * @throws NotFoundException
     */
    public function importAction()
    {
        $this->verifDroit(0, "system:edition");
        $this->setViewParameter('page_title', "Import d'un type de dossier personnalisé");
        $this->setViewParameter('template_milieu', "TypeDossierImport");
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doImportAction()
    {
        $this->verifDroit(0, "system:edition");
        $fileUploader  = $this->getObjectInstancier()->getInstance(FileUploader::class);
        $file_content = $fileUploader->getFileContent("json_type_dossier");

        $typeDossierImportService = $this->getObjectInstancier()->getInstance(TypeDossierImportService::class);

        $result = [];
        try {
            $result = $typeDossierImportService->import($file_content);
        } catch (TypeDossierException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/TypeDossier/import");
        }

        if ($result[TypeDossierUtilService::ID_TYPE_DOSSIER] == $result[TypeDossierUtilService::ORIG_ID_TYPE_DOSSIER]) {
            $message = "Le type de dossier  <b>{$result['id_type_dossier']}</b> a été importé.";
        } else {
            $message = sprintf(
                "Le type de dossier a été importé avec l'identifiant <b>%s</b> car l'identifiant original (%s)" .
                ' existe déjà sur la plateforme',
                $result[TypeDossierUtilService::ID_TYPE_DOSSIER],
                $result[TypeDossierUtilService::ORIG_ID_TYPE_DOSSIER]
            );
        }
        $this->getTypeDossierActionService()->add(
            $this->getId_u(),
            $result[TypeDossierUtilService::ID_T],
            TypeDossierActionService::ACTION_AJOUTE,
            $message
        );
        $this->setLastMessage($message);
        $this->redirect("/TypeDossier/detail?id_t={$result['id_t']}");
    }

    /**
     * @param $id_type_dossier
     * @throws LastErrorException
     * @throws LastMessageException
     */
    private function verifyNoRoleIsUsingTypeDossier($id_type_dossier): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);

        $role_list = array_unique(array_merge(
            $roleSQL->getRoleByDroit("$id_type_dossier:lecture"),
            $roleSQL->getRoleByDroit("$id_type_dossier:edition")
        ));

        if ($role_list) {
            if (count($role_list) == 1) {
                $this->setLastError(
                    "Le type de dossier <b>{$id_type_dossier}</b> est utilisé par le rôle « {$role_list[0]} »"
                );
            } else {
                $this->setLastError(
                    "Le type de dossier <b>{$id_type_dossier}</b> est utilisé par les rôles suivants " . implode(",", $role_list)
                );
            }
            $this->redirect("/TypeDossier/list");
        }
    }

    /**
     * @param $id_type_dossier
     * @throws LastErrorException
     * @throws LastMessageException
     */
    private function verifyNoConnectorIsAssociatedToTypeDossier($id_type_dossier): void
    {
        $fluxEntiteSQL = $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class);
        $entite_list = $fluxEntiteSQL->getEntiteByFlux($id_type_dossier);
        if ($entite_list) {
            $output = [];
            foreach ($entite_list as $entite_info) {
                $output[] = "{$entite_info['denomination']} (id_e={$entite_info['id_e']})";
            }
            if (count($output) == 1) {
                $message = "Le type de dossier <b>{$id_type_dossier}</b> a été associé avec des connecteurs sur l'entité ";
            } else {
                $message = "Le type de dossier <b>{$id_type_dossier}</b> a été associé avec des connecteurs sur les entités : ";
            }
            $this->setLastError(
                $message . implode(", ", $output)
            );
            $this->redirect("/TypeDossier/list");
        }
    }

    /**
     * @param $id_type_dossier
     * @param string $redirectTo
     * @throws LastErrorException
     * @throws LastMessageException
     */
    private function verifyNoDocumentIsUsingTypeDossier($id_type_dossier, $redirectTo = '/TypeDossier/list'): void
    {
        $entite_list = $this->getDocumentSQL()->getEntiteWhichUsedDocument($id_type_dossier);
        $id_t = $this->getTypeDossierSQL()->getByIdTypeDossier($id_type_dossier);

        if (! $entite_list) {
            return;
        }
        $gabarit = $this->getObjectInstancier()->getInstance(Gabarit::class);
        $gabarit->setParameters([
                'entite_list' => $entite_list,
                'id_type_dossier' => $id_type_dossier
        ]);
        $content = $gabarit->getRender("TypeDossierCountByEntiteBox");

        $this->setLastError(
            "La modification n'est pas possible. Le type de dossier {$id_type_dossier} est utilisé par des dossiers qui
                ne sont pas dans l'état <i>terminé</i> ou <i>erreur fatale</i>: $content<br/>
                
                <a href='TypeDossier/putInFatalError?id_t=$id_t&id_type_dossier={$id_type_dossier}' class='btn btn-danger'>
                    <i class='fa fa-folder'></i>&nbsp;Mettre tous les dossiers en erreur fatale
                </a><br>"
        );
        $this->redirect($redirectTo);
    }

    /**
     * @throws NotFoundException
     */
    public function putInFatalErrorAction(): void
    {
        $this->commonEdition();
        $id_type_dossier = $this->getGetInfo()->get('id_type_dossier');
        $entite_list = $this->getDocumentSQL()->getEntiteWhichUsedDocument($id_type_dossier);
        $gabarit = $this->getObjectInstancier()->getInstance(Gabarit::class);
        $gabarit->setParameters([
            'entite_list' => $entite_list,
            'id_type_dossier' => $id_type_dossier
        ]);
        $content = $gabarit->getRender("TypeDossierCountByEntiteBox");
        $this->setViewParameter('content', $content);
        $this->setViewParameter('template_milieu', 'TypeDossierPutInFatalError');

        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doPutInFatalErrorAction()
    {
        $id_type_dossier = $this->getPostInfo()->get('id_type_dossier');
        $this->verifDroit(0, "$id_type_dossier:edition");

        $dossierFetched = $this->getObjectInstancier()->getInstance(TypeDossierSQL::class)
            ->getNotFinished($id_type_dossier);

        foreach ($dossierFetched as $dossier) {
            $this->getObjectInstancier()->getInstance(ActionChange::class)->addAction(
                $dossier['id_d'],
                $dossier['id_e'],
                $this->getId_u(),
                'fatal-error',
                "Passage en erreur fatale via le studio"
            );
            $this->getObjectInstancier()->getInstance(JobManager::class)->deleteDocumentForAllEntities($dossier['id_d']);
        }

        $this->setLastMessage("Tous les dossiers <b>{$id_type_dossier}</b> ont été mis dans l'état erreur fatale");
        $this->redirect('/TypeDossier/list');
    }

    /**
     * @throws UnrecoverableException
     */
    private function getFieldsFromEtape(): array
    {
        $typeDossierProperties = $this->getTypeDossierManager()
            ->getTypeDossierProperties($this->getViewParameterByKey('id_t'));

        $fieldsFromEtape = $this->getTypeDossierService()->getFieldsFromEtape($typeDossierProperties);
        $fieldsForOngletCheminement = $this->getDocumentTypeFactory()
            ->getFluxDocumentType($typeDossierProperties->id_type_dossier)
            ->getFormulaire()
            ->getFieldsForOnglet('Cheminement');
        foreach ($fieldsForOngletCheminement as $field) {
            $fieldsFromEtape [] =  $field->getName();
        }
        return $fieldsFromEtape;
    }
}
