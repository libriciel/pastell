<?php

use ParagonIE\Halite\Alerts\CannotPerformOperation;
use ParagonIE\Halite\Alerts\InvalidDigestLength;
use ParagonIE\Halite\Alerts\InvalidKey;
use ParagonIE\Halite\Alerts\InvalidMessage;
use ParagonIE\Halite\Alerts\InvalidSalt;
use ParagonIE\Halite\Alerts\InvalidType;
use Pastell\Service\Crypto;
use Pastell\Service\Connecteur\ConnecteurCreationService;
use Pastell\Service\Connecteur\ConnecteurActionService;
use Pastell\Service\Connecteur\ConnecteurModificationService;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;

class ConnecteurControler extends PastellControler
{
    protected function getConnecteurDefinitionFile(): ConnecteurDefinitionFiles
    {
        return $this->getInstance(ConnecteurDefinitionFiles::class);
    }
    private function getConnecteurActionService(): ConnecteurActionService
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurActionService::class);
    }
    private function getConnecteurCreationService(): ConnecteurCreationService
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurCreationService::class);
    }
    private function getConnecteurModificationService(): ConnecteurModificationService
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurModificationService::class);
    }


    public function _beforeAction(): void
    {
        parent::_beforeAction();

        $id_e = $this->getGetInfo()->getInt('id_e', 0);
        $global = $this->getGetInfo()->getInt('global', 0);
        if (! $id_e || ! $global) {
            $id_ce = $this->getGetInfo()->getInt('id_ce');
            $connecteur_entite_info = $this->getConnecteurEntiteSQL()->getInfo($id_ce);
            if (! $id_e) {
                $id_e = $connecteur_entite_info['id_e'] ?? 0;
            }
            if (! $global) {
                $global = $connecteur_entite_info['global'] ?? 0;
            }
        }
        $this->setViewParameter('id_e', $id_e);
        $this->setNavigationInfo($id_e, "Entite/connecteur?global=$global");
        $this->setViewParameter('id_e_menu', $id_e);
        $this->setViewParameter('type_e_menu', '');
        $this->setViewParameter(
            'droitLectureAnnuaire',
            $this->getRoleUtilisateur()->hasDroit($this->getId_u(), 'annuaire:lecture', $id_e)
        );
        $this->setViewParameter('menu_gauche_template', "EntiteMenuGauche");
        $this->setViewParameter('menu_gauche_select', "Entite/connecteur?global=$global");
        $this->setDroitLectureOnConnecteur($id_e);
        $this->setCanActOnConnector($id_e);
        $this->setCanEditConnector($id_e);
        $this->setDroitImportExportConfig($id_e);
        $this->setDroitLectureOnUtilisateur($id_e);
    }

    public function hasDroitEdition($id_e): void
    {
        $this->hasConnecteurDroitEdition($id_e);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function getConnectorEntityDetails(int $entityId): array
    {
        $details = $this->getConnecteurEntiteSQL()->getInfo($entityId);
        if (!$details) {
            $this->setLastError("Ce connecteur n'existe pas");
            $this->redirect('/Entite/detail?page=3');
        }
        return $details;
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function verifDroitOnConnecteur($id_ce)
    {
        $connecteur_entite_info = $this->getConnectorEntityDetails($id_ce);
        $this->hasDroitEdition($connecteur_entite_info['id_e']);
        return $connecteur_entite_info;
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function checkCanReadConnector(int $connectorId): void
    {
        $connecteur_entite_info = $this->getConnectorEntityDetails($connectorId);
        $this->hasConnecteurDroitLecture($connecteur_entite_info['id_e']);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function checkActionPermissionOnConnector(int $connectorId): void
    {
        $connectorDetails = $this->getConnectorEntityDetails($connectorId);
        $this->hasConnectorActionPermission($connectorDetails['id_e']);
    }


    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doNewAction()
    {
        $recuperateur = $this->getPostInfo();
        $id_e = $recuperateur->getInt('id_e');
        $global = $recuperateur->getInt('global', 0);
        $id_connecteur = $recuperateur->get('id_connecteur');
        $libelle = $recuperateur->get('libelle');

        try {
            if ($id_e) {
                $this->hasDroitEdition($id_e);
            }
            $connecteur_info = $this->getConnecteurDefinitionFile()->getInfo($id_connecteur, $global);
            if (!$connecteur_info) {
                throw new RuntimeException("Aucun connecteur du type « $id_connecteur »");
            }
            $this->getConnecteurCreationService()->createConnecteur(
                $id_connecteur,
                $connecteur_info['type'],
                $global,
                $id_e,
                $this->getId_u(),
                $libelle,
                [],
                "Le connecteur $id_connecteur « $libelle » a été créé"
            );

            $this->setLastMessage('Connecteur ajouté avec succès');
            $this->redirect("/Entite/connecteur?global=$global&id_e=$id_e");
        } catch (Exception $ex) {
            $this->setLastError($ex->getMessage());
            $this->redirect("/Connecteur/new?global=$global&id_e=$id_e");
        }
    }


    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doDeleteAction()
    {
        $recuperateur = $this->getPostInfo();
        $id_ce = $recuperateur->getInt('id_ce');

        try {
            $info = $this->getConnecteurEntiteSQL()->getInfo($id_ce);
            $this->apiDelete("/entite/{$info['id_e']}/connecteur/$id_ce");
            $this->setLastMessage("Le connecteur « {$info['libelle']} » a été supprimé.");
            $this->redirect("/Entite/connecteur?global={$info['global']}&id_e={$info['id_e']}");
        } catch (Exception $ex) {
            $this->setLastError($ex->getMessage());
            $this->redirect("/Connecteur/edition?id_ce=$id_ce");
        }
    }


    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doEditionLibelleAction()
    {
        $recuperateur = $this->getPostInfo();
        $id_ce = $recuperateur->getInt('id_ce');
        $libelle = $recuperateur->get('libelle');

        try {
            $info = $this->getConnecteurEntiteSQL()->getInfo($id_ce);
            if (! $info) {
                throw new Exception("Ce connecteur n'existe pas.");
            }
            $this->apiPatch("/entite/{$info['id_e']}/connecteur/$id_ce");
        } catch (Exception $ex) {
            $this->getLastError()->setLastError($ex->getMessage());
            $this->redirect("/Connecteur/editionLibelle?id_ce=$id_ce");
        }
        $this->getLastMessage()->setLastMessage("Le connecteur « $libelle » a été modifié.");
        $this->redirect("/Connecteur/edition?id_ce=$id_ce");
    }

    /**
     * @throws Exception
     */
    public function doEditionModifAction()
    {
        $recuperateur = $this->getPostInfo();
        $id_ce = $recuperateur->getInt('id_ce');
        $this->verifDroitOnConnecteur($id_ce);

        $result = $this->getConnecteurModificationService()->editConnecteurFormulaire(
            $id_ce,
            $recuperateur,
            new FileUploader(),
            false,
            $this->getConnecteurEntiteSQL()->getInfo($id_ce)['id_e'],
            $this->getId_u(),
            "Modification du connecteur"
        );
        if (! $result) {
            $this->setLastError($this->getConnecteurModificationService()->getLastMessage());
        }

        if ($recuperateur->get('external_data_button')) {
            $this->redirect(urldecode($recuperateur->get('external_data_button')));
        }
        /* On a appuyé sur un bouton "Ajouter un fichier" */
        if ($recuperateur->get('ajouter') == 'ajouter') {
            $fieldSubmitted = $recuperateur->get('fieldSubmittedId');
            $this->getConnecteurActionService()->add(
                $this->getConnecteurEntiteSQL()->getInfo($id_ce)['id_e'],
                $this->getId_u(),
                $id_ce,
                '',
                ConnecteurActionService::ACTION_MODIFFIE,
                "Le fichier $fieldSubmitted a été modifié"
            );
            $this->redirect("/Connecteur/editionModif?id_ce=$id_ce");
        } else {
            $this->redirect("/Connecteur/edition?id_ce=$id_ce");
        }
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     */
    public function recupFileAction()
    {
        $id_ce = $this->getGetInfo()->getInt('id_ce');
        $field = $this->getGetInfo()->get('field');
        $num = $this->getGetInfo()->getInt('num');

        $this->verifDroitOnConnecteur($id_ce);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($id_ce);
        $filePath = $donneesFormulaire->getFilePath($field, $num);
        if (!$filePath) {
            $this->setLastError("Ce fichier n'existe pas");
            $this->redirect("/Connecteur/edition?id_ce=$id_ce");
        }
        $fileName = $donneesFormulaire->getFileName($field, $num);

        header("Content-type: " . mime_content_type($filePath));
        header("Content-disposition: attachment; filename=\"$fileName\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0,pre-check=0");
        header("Pragma: public");
        readfile($filePath);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     */
    public function deleteFileAction()
    {
        $csrf_token = $this->getPostInfo()->get('csrf_token');
        $this->getObjectInstancier()->getInstance(CSRFToken::class)->verifParamToken($csrf_token);

        $id_ce = $this->getGetInfo()->getInt('id_ce');
        $field = $this->getGetInfo()->get('field');
        $num = $this->getGetInfo()->getInt('num');

        $this->verifDroitOnConnecteur($id_ce);

        $this->getConnecteurModificationService()->removeFile(
            $id_ce,
            $field,
            $num,
            $this->getConnecteurEntiteSQL()->getInfo($id_ce)['id_e'],
            $this->getId_u(),
            "Le fichier $field a été supprimé"
        );

        $this->redirect("/Connecteur/editionModif?id_ce=$id_ce");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function deleteAction()
    {
        $id_ce = $this->getGetInfo()->getInt('id_ce');
        $this->verifDroitOnConnecteur($id_ce);

        $this->setViewParameter('connecteur_entite_info', $this->getConnecteurEntiteSQL()->getInfo($id_ce));

        $this->setViewParameter(
            'page_title',
            sprintf(
                'Suppression du connecteur  « %s »',
                $this->getViewParameterOrObject('connecteur_entite_info')['libelle']
            )
        );
        $this->setViewParameter('template_milieu', 'ConnecteurDelete');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     */
    private function setConnecteurInfo()
    {
        $id_ce = $this->getGetInfo()->getInt('id_ce');
        $connecteur_entite_info = $this->getConnecteurEntiteSQL()->getInfo($id_ce);
        $id_e = $connecteur_entite_info['id_e'];
        $global = $connecteur_entite_info['global'];
        $entite_info = $this->getEntiteSQL()->getInfo($id_e) ?: [];

        $this->setViewParameter(
            'has_definition',
            (bool) $this->getConnecteurDefinitionFile()->getInfo($connecteur_entite_info['id_connecteur'], $global)
        );

        if ($this->getViewParameterByKey('has_definition')) {
            $donneesFormulaire = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($id_ce);
            $this->setViewParameter('donneesFormulaire', $donneesFormulaire);
            if ($global) {
                $this->setViewParameter('action', $this->getDocumentTypeFactory()
                    ->getGlobalDocumentType($connecteur_entite_info['id_connecteur'])
                    ->getAction());
            } else {
                $this->setViewParameter('action', $this->getDocumentTypeFactory()
                    ->getEntiteDocumentType($connecteur_entite_info['id_connecteur'])
                    ->getAction());
            }
        } else {
            $this->setViewParameter(
                'donneesFormulaire',
                $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire()
            );
            $this->setViewParameter('action', []);
        }

        $this->setViewParameter('inject', ['id_e' => $id_e,'id_ce' => $id_ce,'id_d' => '','action' => '']);

        $this->setViewParameter('my_role', '');

        if (! $id_e) {
            $entite_info['denomination'] = 'Entité racine';
        }

        $this->setViewParameter('entite_info', $entite_info);
        $this->setViewParameter('connecteur_entite_info', $connecteur_entite_info);
        $this->setViewParameter('id_ce', $id_ce);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('menu_gauche_select', "Entite/connecteur?global=$global");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function editionModifAction()
    {
        $this->setConnecteurInfo();
        $this->verifDroitOnConnecteur($this->getViewParameterByKey('id_ce'));
        $this->setViewParameter(
            'page_title',
            sprintf(
                "Configuration du connecteur « %s » pour « %s »",
                $this->getViewParameterOrObject('connecteur_entite_info')['libelle'],
                $this->getViewParameterOrObject('entite_info')['denomination']
            )
        );
        $this->setViewParameter('action_url', "Connecteur/doEditionModif");
        $this->setViewParameter('recuperation_fichier_url', "Connecteur/recupFile?id_ce=" . $this->getViewParameterOrObject('id_ce'));
        $this->setViewParameter('suppression_fichier_url', "Connecteur/deleteFile?id_ce=" . $this->getViewParameterOrObject('id_ce'));
        $this->setViewParameter('page', 0);
        $this->setViewParameter('externalDataURL', "Connecteur/externalData") ;
        $this->setViewParameter('template_milieu', "ConnecteurEditionModif");
        $this->renderDefault();
    }

    /**
     * @return JobManager
     */
    private function getJobManager()
    {
        return $this->getObjectInstancier()->getInstance(JobManager::class);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws Exception
     */
    public function editionAction()
    {
        $this->checkCanReadConnector($this->getGetInfo()->getInt('id_ce'));
        $this->setConnecteurInfo();
        $this->setViewParameter(
            'page_title',
            "Configuration des connecteurs pour « {$this->getViewParameter()['entite_info']['denomination']} »"
        );
        $this->setViewParameter(
            'recuperation_fichier_url',
            "Connecteur/recupFile?id_ce=" . $this->getViewParameterByKey('id_ce')
        );
        $this->setViewParameter('template_milieu', 'ConnecteurEdition');
        $this->setViewParameter(
            'fieldDataList',
            $this->getViewParameterByKey('donneesFormulaire')
                ->getFieldDataListAllOnglet($this->getViewParameterByKey('my_role'))
        );
        $this->setViewParameter(
            'job_list',
            $this->getWorkerSQL()->getJobListWithWorkerForConnecteur($this->getViewParameterByKey('id_ce'))
        );
        $this->setViewParameter(
            'return_url',
            urlencode("Connecteur/edition?id_ce={$this->getViewParameterByKey('id_ce')}")
        );
        $this->setViewParameter(
            'connecteurFrequence',
            $this->getJobManager()->getNearestConnecteurFrequence($this->getViewParameterByKey('id_ce'))
        );
        $this->setViewParameter(
            'connecteurFrequenceByFlux',
            $this->getJobManager()->getNearestConnecteurForDocument($this->getViewParameterByKey('id_ce'))
        );
        $this->setViewParameter(
            'connecteur_hash',
            $this->getConnecteurActionService()->getLastHash($this->getViewParameterByKey('id_ce'))
        );
        $this->setViewParameter(
            'usage_flux_list',
            $this->getFluxEntiteSQL()->getFluxAndEntityByConnectorId($this->getViewParameterByKey('id_ce'))
        );
        if ($this->getViewParameterByKey('has_definition')) {
            $this->setViewParameter('action_possible', $this->getActionPossible()
                ->getActionPossibleOnConnecteur($this->getViewParameterByKey('id_ce'), $this->getId_u()));
        } else {
            $this->setViewParameter('action_possible', []);
        }

        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function etatAction()
    {
        $this->setViewParameter('id_ce', $this->getGetInfo()->getInt('id_ce'));
        $this->verifDroitOnConnecteur($this->getViewParameterOrObject('id_ce'));
        $connecteur_entite_info = $this->getConnecteurEntiteSQL()->getInfo($this->getViewParameterOrObject('id_ce'));
        $id_e = $connecteur_entite_info['id_e'];
        $entite_info = $this->getEntiteSQL()->getInfo($id_e) ?: [];
        if (! $id_e) {
            $entite_info['denomination'] = "Entité racine";
        }
        $this->setViewParameter('page_title', "États du connecteur « {$connecteur_entite_info['libelle']} » 
            pour « {$entite_info['denomination']} »");
        $this->setViewParameter('offset', $this->getPostOrGetInfo()->get('offset', 0));
        $this->setViewParameter('limit', 20);
        $this->setViewParameter('count', $this->getConnecteurActionService()->countByIdCe($this->getViewParameterOrObject('id_ce')));
        $this->setViewParameter('connecteurAction', $this->getConnecteurActionService()
            ->getByIdCe($this->getViewParameterOrObject('id_ce'), $this->getViewParameterOrObject('offset'), $this->getViewParameterOrObject('limit')));

        $this->setViewParameter('template_milieu', "ConnecteurEtat");
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     */
    public function newAction()
    {
        $id_e = $this->getGetInfo()->getInt('id_e');
        $global = $this->getGetInfo()->getInt('global', 0);

        $this->verifDroit($id_e, "connecteur:edition");

        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('global', $global);
        $this->setViewParameter('all_connecteur_dispo', $this->getConnecteurDefinitionFile()->getAll($global));

        $this->setViewParameter('page_title', "Ajout d'un connecteur");
        $this->setViewParameter('template_milieu', "ConnecteurNew");
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function editionLibelleAction()
    {
        $id_ce = $this->getGetInfo()->getInt('id_ce');
        $this->verifDroitOnConnecteur($id_ce);

        $this->setViewParameter('connecteur_entite_info', $this->getConnecteurEntiteSQL()->getInfo($id_ce));

        $this->setViewParameter('page_title', "Modification du connecteur  « {$this->getViewParameterOrObject('connecteur_entite_info')['libelle']} »");
        $this->setViewParameter('template_milieu', "ConnecteurEditionLibelle");
        $this->renderDefault();
    }


    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function exportAction(): void
    {
        $id_ce = $this->getGetInfo()->getInt('id_ce');
        $this->verifDroitOnConnecteur($id_ce);

        $generator = new UriSafeTokenGenerator();
        $password = $generator->generateToken();
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->store(
            "export_connector_password_$id_ce",
            $password,
            60
        );

        $this->setViewParameter('id_ce', $id_ce);
        $this->setViewParameter('password', $password);
        $this->setViewParameter('page_title', 'Connecteur - Export');
        $this->setViewParameter('template_milieu', 'ConnecteurExport');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws CannotPerformOperation
     * @throws InvalidDigestLength
     * @throws InvalidKey
     * @throws InvalidMessage
     * @throws InvalidSalt
     * @throws InvalidType
     */
    public function doExportAction(): void
    {
        $id_ce = $this->getPostInfo()->getInt('id_ce');
        $this->verifDroitOnConnecteur($id_ce);
        $password = $this->getObjectInstancier()
            ->getInstance(MemoryCache::class)
            ->fetch("export_connector_password_$id_ce");
        if (empty($password)) {
            $this->setLastError('Export impossible : Expiration du mot de passe généré');
            $this->redirect("/Connecteur/edition?id_ce=$id_ce");
        }

        try {
            $connecteurConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce);
        } catch (Exception $e) {
            $this->setLastError('Export impossible : Impossible de trouver la définition de ce connecteur');
            $this->redirect("/Connecteur/edition?id_ce=$id_ce");
        }

        $encryptedConnector = $this->getInstance(Crypto::class)
            ->encrypt($connecteurConfig->jsonExport(), $password);

        $connecteurEntite = $this->getConnecteurEntiteSQL();
        $info = $connecteurEntite->getInfo($id_ce);

        $filename = strtr($info['libelle'], ' ', '_') . '.json';

        $this->getObjectInstancier()->getInstance(SendFileToBrowser::class)
            ->sendData($encryptedConnector, $filename, 'application/json');
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function importAction()
    {
        $id_ce = $this->getGetInfo()->getInt('id_ce');

        $this->verifDroitOnConnecteur($id_ce);

        $this->setViewParameter('connecteur_entite_info', $this->getConnecteurEntiteSQL()->getInfo($id_ce));

        $this->setViewParameter('page_title', "Importer des données pour le connecteur 
            « {$this->getViewParameterOrObject('connecteur_entite_info')['libelle']} »");
        $this->setViewParameter('template_milieu', "ConnecteurImport");
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doImportAction()
    {
        $id_ce = $this->getPostInfo()->getInt('id_ce');
        $password = $this->getPostInfo()->get('password');

        $this->verifDroitOnConnecteur($id_ce);
        $fileUploader = new FileUploader();
        $file_content = $fileUploader->getFileContent('pser');

        $connecteurConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce);
        try {
            $connecteurConfig->jsonImport($file_content);
        } catch (DonneesFormulaireException $exception) {
            try {
                $message = $this->getInstance(Crypto::class)->decrypt($file_content, $password);
                $connecteurConfig->jsonImport($message);
            } catch (Exception $e) {
                $this->setLastError($e->getMessage());
                $this->redirect("/Connecteur/import?id_ce=$id_ce");
            }
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/Connecteur/import?id_ce=$id_ce");
        }

        $message = "Les données du connecteur ont été importées";
        $this->getConnecteurActionService()->add(
            $this->getConnecteurEntiteSQL()->getInfo($id_ce)['id_e'],
            $this->getId_u(),
            $id_ce,
            '',
            ConnecteurActionService::ACTION_MODIFFIE,
            $message
        );
        $this->setLastMessage($message);

        $this->redirect("/Connecteur/edition?id_ce=$id_ce");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     */
    public function actionAction()
    {

        $recuperateur = $this->getPostInfo();

        $action = $recuperateur->get('action');
        $id_ce = $recuperateur->getInt('id_ce', 0);

        $this->checkActionPermissionOnConnector($id_ce);

        $actionPossible = $this->getActionPossible();

        if (! $actionPossible->isActionPossibleOnConnecteur($id_ce, $this->getId_u(), $action)) {
            $this->setLastError("L'action « $action »  n'est pas permise : " . $actionPossible->getLastBadRule());
            $this->redirect("/Connecteur/edition?id_ce=$id_ce");
        }

        $result = $this->getActionExecutorFactory()->executeOnConnecteur($id_ce, $this->getId_u(), $action);

        $message = $this->getActionExecutorFactory()->getLastMessage();

        if (! $result) {
            $this->setLastError($message);
        } else {
            $this->setLastMessage($message);
        }

        $this->redirect("/Connecteur/edition?id_ce=$id_ce");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function externalDataAction()
    {
        $recuperateur = $this->getGetInfo();
        $id_ce = $recuperateur->getInt('id_ce');
        $field = $recuperateur->get('field');

        $connecteur_info = $this->getConnecteurEntiteSQL()->getInfo($id_ce);
        $id_e = $connecteur_info['id_e'];
        $this->setDroitLectureOnUtilisateur($id_e);
        $this->verifDroitOnConnecteur($id_ce);

        $documentType = ($connecteur_info['global']) ?
            $this->getDocumentTypeFactory()->getGlobalDocumentType($connecteur_info['id_connecteur'])
            : $this->getDocumentTypeFactory()->getEntiteDocumentType($connecteur_info['id_connecteur']);

        $formulaire = $documentType->getFormulaire();
        $formField = $formulaire->getField($field);
        if (!$formField) {
            $this->setLastError("Le champ $field n'existe pas");
            $this->redirect("/Connecteur/editionModif?id_ce=$id_ce");
        }
        $action_name = $formField->getProperties('choice-action');
        $result = $this->getActionExecutorFactory()->displayChoiceOnConnecteur(
            $id_ce,
            $this->getId_u(),
            $action_name,
            $field
        );
        if (!$result) {
            $this->setLastError($this->getActionExecutorFactory()->getLastMessage());
            $this->redirect("/Connecteur/editionModif?id_ce=$id_ce");
        }
    }

    private function addExternalData(int $id_ce, string $field, bool $from_api = false): bool
    {
        $this->verifDroitOnConnecteur($id_ce);

        return $this->getConnecteurModificationService()->addExternalData(
            $id_ce,
            $field,
            $this->getId_u(),
            "L'external data $field a été modifié",
            $from_api
        );
    }

    /**
     * @throws Exception
     */
    public function doExternalDataAction()
    {
        $recuperateur = $this->getPostOrGetInfo();
        $id_ce = $recuperateur->getInt('id_ce');
        $field = $recuperateur->get('field');

        $result = $this->addExternalData($id_ce, $field);
        if (! $result) {
            $this->setLastError($this->getConnecteurModificationService()->getLastMessage());
        }
    }

    public function doExternalDataApiAction()
    {
        $recuperateur = $this->getPostOrGetInfo();
        $id_ce = $recuperateur->getInt('id_ce');
        $field = $recuperateur->get('field');

        $this->addExternalData($id_ce, $field, true);
    }
}
