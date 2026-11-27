<?php

use Pastell\File\Chunk\ChunkUploader;
use Pastell\Service\Daemon\JobConsultationService;
use Pastell\Service\Document\DocumentActionService;
use Pastell\Service\Document\DocumentEntiteService;
use Pastell\Service\Document\DocumentFileService;
use Pastell\Service\Document\DocumentInfoService;
use Pastell\Service\Document\DocumentReindexService;
use Pastell\Service\Document\DocumentRoleService;
use Pastell\Service\Document\DocumentSearchCriteria;
use Pastell\Service\Document\DocumentSearchService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Entite\EntityInfoService;
use Pastell\Service\Entite\RootEntity;
use Pastell\Service\Utilisateur\UtilisateurEntiteService;
use Pastell\Service\Menu\MenuGaucheOption;
use Pastell\Service\Menu\MenuGaucheService;
use Pastell\ViewModel\DeleteConfirmation;

class DocumentControler extends PastellControler
{
    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function _beforeAction(): void
    {
        parent::_beforeAction();
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');
        $id_d = $this->getPostOrGetInfo()->get('id_d');
        $type = $this->getPostOrGetInfo()->get('type');

        if ($id_d && ! is_array($id_d)) {
            $type = $this->getInstance(DocumentInfoService::class)->getType((string) $id_d) ?? $type;
            $this->setViewParameter('type_e_menu', $type);
        }

        $this->setNavigationInfo($id_e, "Document/list?type=$type");
        $this->setMenuGaucheSelect(MenuGaucheOption::buildUrl(MenuGaucheService::DOCUMENT_LIST, ['type' => $type]));
        $this->setDocumentMenuGauche($id_e);
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function renderDefault(): void
    {
        if ($this->isViewParameter('id_e')) {
            $this->setDocumentMenuGauche((int)$this->getViewParameterByKey('id_e'));
        }
        $this->setViewParameter(
            'show_choice_entity_message',
            ! $this->getPostOrGetInfo()->getInt('id_e')
            && $this->getViewParameterByKey('id_e') === RootEntity::ID_E
        );
        parent::renderDefault();
    }

    /**
     * @return DocumentActionEntite
     * @deprecated 4.1.26 Use DocumentActionEntiteSQL, DocumentEntiteService and DocumentSearchService instead
     */
    protected function getDocumentActionEntite(): DocumentActionEntite
    {
        return $this->getInstance(DocumentActionEntite::class);
    }

    private function enrichListDocumentForDisplay(array $listDocument): array
    {
        $documentTypeFactory = $this->getDocumentTypeFactory();
        $documentSearchService = $this->getInstance(DocumentSearchService::class);
        foreach ($listDocument as &$document) {
            $document['documentType'] = $documentTypeFactory->getFluxDocumentType($document['type']);
            $document['index'] = $documentSearchService->getIndexValues($document['id_d']);
        }
        unset($document);
        return $listDocument;
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function redirectToList(int $id_e, string $type = ''): never
    {
        $this->redirect("/Document/list?id_e=$id_e&type=$type");
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function verifDroitLecture(int $id_e, string $id_d): array
    {
        $document_info = $this->getInstance(DocumentInfoService::class)->getInfo($id_d);
        if ($document_info === null) {
            $this->redirectToList($id_e);
        }

        $type = $document_info['type'];
        if (
            !$this->getDroitService()->hasDroitFor($this->getId_u(), $id_e, $type, DroitType::LECTURE)
            || !$this->getInstance(DocumentRoleService::class)->getRole($id_e, $id_d)
        ) {
            $this->redirectToList($id_e, $type);
        }
        return $document_info;
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function getDocumentInfoOrRedirect(int $id_e, string $id_d): array
    {
        $document_info = $this->getInstance(DocumentInfoService::class)->getInfo($id_d);
        if ($document_info === null) {
            $this->setLastError("Ce dossier n'existe pas");
            $this->redirectToList($id_e);
        }
        return $document_info;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function arAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');

        $info_document = $this->verifDroitLecture($id_e, $id_d);

        $true_last_action = $this->getInstance(DocumentEntiteService::class)->getCurrentAction($id_e, $id_d);
        /** @var DocumentType $documentType */
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($info_document['type']);

        $action = $documentType->getAction();
        if (! $action->getProperties($true_last_action, 'accuse_de_reception_action')) {
            $this->redirect("/Document/detail?id_e=$id_e&id_d=$id_d");
        }
        $this->setViewParameter('action', $action->getProperties($true_last_action, 'accuse_de_reception_action'));
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_d', $id_d);

        $this->setViewParameter('page_title', 'Accusé de réception');
        $this->setViewParameter('template_milieu', 'DocumentAR');
        $this->renderDefault();
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function detailAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $page = $recuperateur->getInt('page', 0);

        $info_document = $this->verifDroitLecture($id_e, $id_d);
        $type = $info_document['type'];

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $action = $documentType->getAction();
        $true_last_action = $this->getInstance(DocumentEntiteService::class)->getCurrentAction($id_e, $id_d);
        if ($action->getProperties($true_last_action, 'accuse_de_reception_action')) {
            $this->redirect("/Document/ar?id_e=$id_e&id_d=$id_d");
        }

        $this->getJournalEntryService()->addConsultation($id_e, $id_d, $this->getId_u());

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d, $type);
        $donneesFormulaire->getFormulaire()->setTabNumber($page);
        $my_role = $this->getInstance(DocumentRoleService::class)->getRole($id_e, $id_d);
        if ($documentType->isAfficheOneTab()) {
            $field_data_list = $donneesFormulaire->getFieldDataListAllOnglet($my_role);
        } else {
            $field_data_list = $donneesFormulaire->getFieldDataList($my_role, $page);
        }

        $documentEmailService = $this->getDocumentEmailService();
        $document_email_list = $documentEmailService->getRecipients($id_d);

        $job_list = false;
        if ($this->hasDroitFor($id_e, DroitService::DROIT_SYSTEM, DroitType::LECTURE)) {
            $job_list = $this->getInstance(JobConsultationService::class)->getJobsForDocument($id_e, $id_d);
        }

        $this->setViewParameter('info', $info_document);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_d', $id_d);
        $this->setViewParameter('page', $page);
        $this->setViewParameter('documentType', $documentType);
        $this->setViewParameter('infoEntite', $this->getInstance(EntityInfoService::class)->getInfo($id_e));
        $this->setViewParameter('formulaire', $documentType->getFormulaire());
        $this->setViewParameter('donneesFormulaire', $donneesFormulaire);
        $this->setViewParameter('actionPossible', $this->getActionPossible());
        $this->setViewParameter('theAction', $action);
        $this->setViewParameter('my_role', $my_role);
        $this->setViewParameter('infoDocumentEmail', $document_email_list);
        $this->setViewParameter('reponse_column', $documentEmailService->getResponseColumns($document_email_list));
        $this->setViewParameter(
            'document_action_list',
            $this->getInstance(DocumentEntiteService::class)->getActions($id_e, $id_d)
        );
        $this->setViewParameter('next_action_automatique', $action->getActionAutomatique($true_last_action));
        $this->setViewParameter(
            'droit_erreur_fatale',
            $this->getDroitService()->hasDroitFor($this->getId_u(), RootEntity::ID_E, $type, DroitType::EDITION)
        );
        $this->setRootDroitViewParameter(DroitService::DROIT_SYSTEM, DroitType::EDITION);
        if ($this->hasDroitFor(RootEntity::ID_E, DroitService::DROIT_SYSTEM, DroitType::EDITION)) {
            $this->setViewParameter('all_action', $action->getWorkflowAction());
        }
        $this->setViewParameter('fieldDataList', $field_data_list);
        $this->setViewParameter('document_email_reponse_list', $documentEmailService->getResponses($id_d));
        $this->setViewParameter('recuperation_fichier_url', "Document/recuperationFichier?id_d=$id_d&id_e=$id_e");
        $this->setViewParameter('job_list', $job_list);
        $this->setViewParameter('return_url', urlencode("Document/detail?id_e=$id_e&id_d=$id_d"));
        $this->setViewParameter('inject', ['id_e' => $id_e, 'id_ce' => '', 'id_d' => $id_d, 'action' => $action]);

        $this->setViewParameter('page_title', $info_document['titre'] . ' (' . $documentType->getName() . ')');
        $this->setViewParameter('template_milieu', 'DocumentDetail');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function detailMailReponseAction(): void
    {
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');
        $id_d = (string) $this->getPostOrGetInfo()->get('id_d');
        $id_d_reponse = (string) $this->getPostOrGetInfo()->get('id_d_reponse');

        $info_document = $this->verifDroitLecture($id_e, $id_d);

        try {
            $mail_info = $this->getDocumentEmailService()->readResponse(
                $id_e,
                $id_d,
                $id_d_reponse,
                (string) $this->getAuthentification()->getLogin()
            );
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect();
        }

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d_reponse);
        $this->setViewParameter('donneesFormulaire', $donneesFormulaire);
        $this->setViewParameter('fieldDataList', $donneesFormulaire->getFieldDataList('', 0));
        $this->setViewParameter(
            'recuperation_fichier_url',
            "Document/recuperationFichier?id_d=$id_d_reponse&id_e=$id_e"
        );

        $this->setViewParameter('page_title', $info_document['titre'] . ' ( Réponse de ' . $mail_info['email'] . ')');
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_d', $id_d);

        $this->setViewParameter('inject', [
            'id_d' => $id_d_reponse,
            'id_e' => $id_e,
            'id_ce' => false,
            'action' => false,
        ]);

        $this->setViewParameter('template_milieu', 'DocumentMailReponse');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function editionAction()
    {
        $id_d = (string) $this->getGetInfo()->get('id_d');
        $id_e = $this->getGetInfo()->getInt('id_e');
        $page = $this->getGetInfo()->getInt('page', 0);
        $action = $this->getGetInfo()->get('action') ?: Action::MODIFICATION;

        if (!$id_d) {
            $this->setLastError("id_d n'a pas été fourni");
            $this->redirect('/Document/list');
        }

        $info = $this->getDocumentInfoOrRedirect($id_e, $id_d);
        $type = $info['type'];

        $this->checkDroitFor($id_e, $type, DroitType::EDITION, '/Document/list');

        $actionPossible = $this->getActionPossible();
        if (!$actionPossible->isActionPossible($id_e, $this->getId_u(), $id_d, $action)) {
            $this->setLastError("L'action « $action »  n'est pas permise : " . $actionPossible->getLastBadRule());
            $this->redirect("/Document/detail?id_d=$id_d&id_e=$id_e&page=$page");
        }

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $infoEntite = $this->getInstance(EntityInfoService::class)->getInfo($id_e);
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d, $type);
        if (!$donneesFormulaire->getFormulaire()->tabNumberExists($page)) {
            $page = 0;
        }

        $this->setViewParameter(
            'inject',
            ['id_e' => $id_e, 'id_d' => $id_d, 'form_type' => $type, 'action' => $action, 'id_ce' => '']
        );
        $this->setViewParameter('info', $info);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_d', $id_d);
        $this->setViewParameter('page', $page);
        $this->setViewParameter('type', $type);
        $this->setViewParameter('action', $action);
        $this->setViewParameter('documentType', $documentType);
        $this->setViewParameter('infoEntite', $infoEntite);
        $this->setViewParameter('formulaire', $documentType->getFormulaire());
        $this->setViewParameter('donneesFormulaire', $donneesFormulaire);
        $this->setViewParameter('ActionPossible', $actionPossible);
        $this->setViewParameter('theAction', $documentType->getAction());
        $this->setViewParameter('my_role', $this->getInstance(DocumentRoleService::class)->getRole($id_e, $id_d));
        $this->setViewParameter('action_url', 'Document/doEdition');
        $this->setViewParameter('recuperation_fichier_url', "Document/recuperationFichier?id_d=$id_d&id_e=$id_e");
        $this->setViewParameter(
            'suppression_fichier_url',
            "Document/supprimerFichier?id_d=$id_d&id_e=$id_e&page=$page&action=$action"
        );
        $this->setViewParameter('externalDataURL', 'Document/externalData');

        $this->setViewParameter(
            'page_title',
            "Modification du dossier « {$documentType->getName()} » ( {$infoEntite['denomination']} ) "
        );
        $this->setViewParameter('template_milieu', 'DocumentEdition');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function indexAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_e = $recuperateur->getInt('id_e', 0);
        $type = $recuperateur->get('type');
        $offset = $recuperateur->getInt('offset', 0);
        $search = $recuperateur->get('search');
        $limit = 20;

        $liste_collectivite = $this->getInstance(UtilisateurEntiteService::class)
            ->getEntiteWithAnyDroit($this->getId_u());
        if (!$id_e) {
            if (count($liste_collectivite) === 0) {
                $this->redirect('/Connexion/nodroit');
            }
            if (count($liste_collectivite) === 1) {
                $id_e = (int) $liste_collectivite[0];
            }
        }

        if ($id_e && $type) {
            $this->redirect("/Document/list?id_e=$id_e&type=$type");
        }

        $liste_type = [];
        if ($id_e) {
            $liste_type = $this->getDroitService()->getPartsWithLecture($this->getId_u(), $id_e);
            if (!$liste_type) {
                $this->setLastError("Vous n'avez pas les droits nécessaires pour accéder à cette page");
                $this->redirect();
            }
        }

        $this->setViewParameter('type', $type);
        $this->setViewParameter('tri', $recuperateur->get('tri', 'date_dernier_etat'));
        $this->setViewParameter('sens_tri', $recuperateur->get('sens_tri', 'DESC'));
        $this->setViewParameter('url_tri', false);

        if ($id_e) {
            $documentSearchService = $this->getInstance(DocumentSearchService::class);
            $documentSearchCriteria = new DocumentSearchCriteria(
                allowed_types: $liste_type,
                id_e: $id_e,
                search: $search,
            );
            $listDocument = $documentSearchService->search($documentSearchCriteria, $offset, $limit);
            $this->setViewParameter('listDocument', $this->enrichListDocumentForDisplay($listDocument));
            $this->setViewParameter('count', $documentSearchService->count($documentSearchCriteria));
            $this->setViewParameter('type_list', $this->getAllType($listDocument));
        }

        $infoEntite = $this->getInstance(EntityInfoService::class)->getInfo($id_e);
        $this->setViewParameter('infoEntite', $infoEntite);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('search', $search);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('limit', $limit);
        $this->setViewParameter('url', "id_e=$id_e&search=$search");

        $this->setViewParameter('champs_affiches', DocumentType::getDefaultDisplayField());
        $this->setNavigationInfo($id_e, 'Document/index?a=a');
        $this->setViewParameter(
            'page_title',
            $infoEntite ? 'Liste des dossiers ' . $infoEntite['denomination'] : 'Liste des dossiers'
        );

        $this->setViewParameter('template_milieu', 'DocumentIndex');
        $this->renderDefault();
    }

    private function getAllType(array $listDocument): array
    {
        $type_list = [];
        foreach ($listDocument as $document_info) {
            $type_list[$document_info['type']] = $document_info['type'];
        }
        return array_keys($type_list);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function listAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_e = $recuperateur->get('id_e', 0);
        $offset = $recuperateur->getInt('offset', 0);
        $search = $recuperateur->get('search');
        $type = $recuperateur->get('type');
        $filtre = $recuperateur->get('filtre');
        $last_id = $recuperateur->get('last_id');

        $limit = 20;

        if (! $type) {
            $this->redirect("/Document/index?id_e=$id_e");
        }

        if ($id_e == RootEntity::ID_E) {
            $this->redirect("/Document/index?id_e=$id_e&type=$type");
        }

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);

        $liste_collectivite = $this->getInstance(UtilisateurEntiteService::class)->getEntite(
            $this->getId_u(),
            DroitService::getDroitFor($type, DroitType::LECTURE)
        );

        if (! $liste_collectivite) {
            $this->redirect('/Document/index');
        }

        if (!$id_e && (count($liste_collectivite) == 1)) {
            $id_e = $liste_collectivite[0];
            $this->setViewParameter('id_e_menu', $id_e);
            $this->setViewParameter('type_e_menu', $type);
        }

        $this->checkDroitFor($id_e, $type, DroitType::LECTURE);
        $infoEntite = $this->getInstance(EntityInfoService::class)->getInfo((int) $id_e);
        $this->setViewParameter('infoEntite', $infoEntite);

        $page_title = 'Liste des dossiers ' . $documentType->getName();
        if ($id_e) {
            $page_title .= ' pour ' . $infoEntite['denomination'];
        }

        $this->setViewParameter('page_title', $page_title);
        $this->setViewParameter('ActionPossible', $this->getActionPossible());
        $this->setViewParameter('all_action', $documentType->getAction()->getWorkflowAction());

        $documentSearchService = $this->getInstance(DocumentSearchService::class);
        if ($last_id) {
            $offset = $documentSearchService->getOffset($last_id, (int) $id_e, $type, $limit);
        }

        if ($this->getActionPossible()->isCreationPossible($id_e, $this->getId_u(), $type)) {
            $this->setViewParameter('nouveau_bouton_url', [
                'Créer' =>
                    [
                        'url' => 'Document/new',
                        'type' => $type,
                        'id_e' => $id_e,
                    ]
            ]);
        }
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('search', $search);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('limit', $limit);
        $this->setViewParameter('filtre', $filtre);
        $this->setViewParameter('last_id', $last_id);
        $this->setViewParameter('type', $type);
        $this->setViewParameter('url', "id_e=$id_e&search=$search&type=$type&lastetat=$filtre");

        $tri = $recuperateur->get('tri', 'date_dernier_etat');
        $sens_tri = $recuperateur->get('sens_tri', 'DESC');
        $this->setViewParameter('tri', $tri);
        $this->setViewParameter('sens_tri', $sens_tri);

        $this->setViewParameter('documentTypeFactory', $this->getDocumentTypeFactory());
        $this->setNavigationInfo($id_e, "Document/list?type=$type");

        $this->setViewParameter('champs_affiches', $documentType->getChampsAffiches());

        $allDroitEntite = $this->getDroitService()->getAllDocumentLecture($this->getId_u(), $id_e);
        $this->setViewParameter('allDroitEntite', $allDroitEntite);

        $indexedFieldsList = $documentType->getFormulaire()->getIndexedFields();
        $this->setViewParameter('indexedFieldsList', $indexedFieldsList);
        $indexedFieldValue = [];
        foreach ($indexedFieldsList as $indexField => $indexLibelle) {
            $indexedFieldValue[$indexField] = $recuperateur->get($indexField);
        }

        $documentSearchCriteria = new DocumentSearchCriteria(
            allowed_types: $allDroitEntite,
            id_e: (int) $id_e,
            type: $type,
            search: $search,
            last_actions: $filtre ? explode(',', $filtre) : [],
            index_values: $indexedFieldValue,
        );
        $this->setViewParameter('count', $documentSearchService->count($documentSearchCriteria));
        $listDocument = $documentSearchService->search(
            $documentSearchCriteria,
            $offset,
            $limit,
            $tri,
            $sens_tri
        );
        $this->setViewParameter('listDocument', $this->enrichListDocumentForDisplay($listDocument));

        $this->setViewParameter('url_tri', "Document/list?id_e=$id_e&type=$type&search=$search&filtre=$filtre");

        $this->setViewParameter('type_list', $this->getAllType($listDocument));

        $this->setViewParameter('template_milieu', 'DocumentList');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     */
    public function searchDocument(): DocumentSearchCriteria
    {
        $recuperateur = new Recuperateur($_REQUEST);
        $id_e = $recuperateur->getInt('id_e', RootEntity::ID_E);
        $type = $recuperateur->get('type');
        $lastEtat = $recuperateur->get('lastetat');
        $last_state_begin = $recuperateur->get('last_state_begin');
        $last_state_end = $recuperateur->get('last_state_end');
        $state_begin = $recuperateur->get('state_begin');
        $state_end = $recuperateur->get('state_end');
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('type', $type);
        $this->setViewParameter('lastEtat', $lastEtat);
        $this->setViewParameter('last_state_begin', $last_state_begin);
        $this->setViewParameter('last_state_end', $last_state_end);
        $this->setViewParameter('state_begin', $state_begin);
        $this->setViewParameter('state_end', $state_end);

        $last_state_begin_iso = getDateIso($last_state_begin);
        $last_state_end_iso = getDateIso($last_state_end);
        $state_begin_iso = getDateIso($state_begin);
        $state_end_iso = getDateIso($state_end);
        $this->setViewParameter('last_state_begin_iso', $last_state_begin_iso);
        $this->setViewParameter('last_state_end_iso', $last_state_end_iso);
        $this->setViewParameter('state_begin_iso', $state_begin_iso);
        $this->setViewParameter('state_end_iso', $state_end_iso);

        if (!$id_e) {
            $this->setLastError('id_e est obligatoire');
            $this->redirect('');
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_ENTITE, DroitType::LECTURE);

        $allDroitEntite = $this->getDroitService()->getAllDocumentLecture($this->getId_u(), $id_e);
        $this->setViewParameter('allDroitEntite', $allDroitEntite);

        $etatTransit = $recuperateur->get('etatTransit');
        $this->setViewParameter('etatTransit', $etatTransit);
        $this->setViewParameter('notEtatTransit', $recuperateur->get('notEtatTransit'));

        $this->setViewParameter('tri', $recuperateur->get('tri', 'date_dernier_etat'));
        $this->setViewParameter('sens_tri', $recuperateur->get('sens_tri', 'DESC'));
        $this->setViewParameter('go', $recuperateur->get('go', 0));
        $this->setViewParameter('offset', $recuperateur->getInt('offset', 0));
        $search = $recuperateur->get('search');
        $this->setViewParameter('search', $search);
        $this->setViewParameter('limit', $recuperateur->getInt('limit', 100));

        $indexedFieldValue = [];
        if ($type) {
            $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
            $indexedFieldsList = $documentType->getFormulaire()->getIndexedFields();
            $this->setViewParameter('indexedFieldsList', $indexedFieldsList);

            foreach ($indexedFieldsList as $indexField => $indexLibelle) {
                $indexedFieldValue[$indexField] = $recuperateur->get($indexField);
                if ($documentType->getFormulaire()->getField($indexField)->getType() == 'date') {
                    $indexedFieldValue[$indexField] = date_fr_to_iso($recuperateur->get($indexField));
                }
            }
            $this->setViewParameter('champs_affiches', $documentType->getChampsAffiches());
        } else {
            $this->setViewParameter('champs_affiches', DocumentType::getDefaultDisplayField());
            $this->setViewParameter('indexedFieldsList', []);
        }

        $this->setViewParameter('indexedFieldValue', $indexedFieldValue);

        $allDroit = $this->getDroitService()->getUnrestrictedDroits($this->getId_u());
        $this->setViewParameter('listeEtat', $this->getDocumentTypeFactory()->getActionByRole($allDroit));

        $documentSearchCriteria = new DocumentSearchCriteria(
            allowed_types: $allDroitEntite,
            id_e: $id_e,
            type: $type,
            search: $search,
            last_actions: $lastEtat ? explode(',', $lastEtat) : [],
            last_action_begin: $last_state_begin_iso,
            last_action_end: $last_state_end_iso,
            transit_action: $etatTransit,
            transit_begin: $state_begin_iso,
            transit_end: $state_end_iso,
            not_transit_action: $recuperateur->get('notEtatTransit'),
            index_values: $indexedFieldValue,
        );
        $this->setViewParameter('documentTypeFactory', $this->getDocumentTypeFactory());

        $this->setViewParameter('my_id_e', $id_e);

        try {
            $listDocument = $this->apiGet(sprintf('entite/%d/document', $id_e));
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect('');
        }
        $this->setViewParameter('listDocument', $this->enrichListDocumentForDisplay($listDocument));

        $url_tri = sprintf(
            'Document/search?id_e=%s&search=%s&type=%s&lastetat=%s&last_state_begin=%s&last_state_end=%s' .
            '&etatTransit=%s&state_begin=%s&state_end=%s&date_in_fr=true',
            $id_e,
            $search,
            $type,
            $lastEtat,
            $last_state_begin_iso,
            $last_state_end_iso,
            $etatTransit,
            $state_begin_iso,
            $state_end_iso,
        );
        if ($type) {
            foreach ($indexedFieldValue as $indexName => $indexValue) {
                $url_tri .= '&' . urlencode($indexName) . '=' . urlencode($indexValue);
            }
        }

        $this->setViewParameter('url_tri', $url_tri);
        $this->setViewParameter('type_list', $this->getAllType($listDocument));
        return $documentSearchCriteria;
    }

    /**
     * @throws NotFoundException
     */
    public function exportAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_e = $recuperateur->getInt('id_e', 0);
        $type = $recuperateur->get('type');
        $search = $recuperateur->get('search');
        $lastEtat = $recuperateur->get('lastetat');
        $last_state_begin_iso = getDateIso($recuperateur->get('last_state_begin'));
        $last_state_end_iso = getDateIso($recuperateur->get('last_state_end'));
        $etatTransit = $recuperateur->get('etatTransit');
        $state_begin = $recuperateur->get('state_begin');
        $state_end = $recuperateur->get('state_end');
        $notEtatTransit = $recuperateur->get('notEtatTransit');
        $tri = $recuperateur->get('tri');
        $sens_tri = $recuperateur->get('sens_tri');

        $allDroitEntite = $this->getDroitService()->getAllDocumentLecture($this->getId_u(), $id_e);

        $indexedFieldsList = [];
        $indexedFieldValue = [];
        if ($type) {
            $indexedFieldsList = $this->getDocumentTypeFactory()
                ->getFluxDocumentType($type)
                ->getFormulaire()
                ->getIndexedFields();
            foreach (array_keys($indexedFieldsList) as $indexField) {
                $indexedFieldValue[$indexField] = $recuperateur->get($indexField);
            }
        }

        $documentSearchCriteria = new DocumentSearchCriteria(
            allowed_types: $allDroitEntite,
            id_e: $id_e,
            type: $type,
            search: $search,
            last_actions: $lastEtat ? explode(',', $lastEtat) : [],
            last_action_begin: $last_state_begin_iso,
            last_action_end: $last_state_end_iso,
            transit_action: $etatTransit,
            transit_begin: $state_begin,
            transit_end: $state_end,
            not_transit_action: $notEtatTransit,
            index_values: $indexedFieldValue,
        );
        $csv_line_list = $this->getInstance(DocumentSearchService::class)->getCsvLines(
            $documentSearchCriteria,
            $indexedFieldsList,
            (string) $tri,
            (string) $sens_tri
        );

        $this->getInstance(CSVoutput::class)->sendAttachment(
            "pastell-export-$id_e-$type-$search-$lastEtat-$tri.csv",
            $csv_line_list
        );
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function searchAction(): void
    {
        $documentSearchCriteria = $this->searchDocument();
        $this->setViewParameter(
            'count',
            $this->getInstance(DocumentSearchService::class)->count($documentSearchCriteria)
        );
        $this->setViewParameter('page_title', 'Recherche avancée de dossiers');
        $this->setViewParameter('template_milieu', 'DocumentSearch');
        $this->renderDefault();
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function warningAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $action = $recuperateur->get('action');
        $id_e = $recuperateur->getInt('id_e');
        $page = $recuperateur->getInt('page', 0);

        $infoDocument = $this->verifDroitLecture($id_e, $id_d);

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($infoDocument['type']);
        $theAction = $documentType->getAction();

        $this->setViewParameter('id_d', $id_d);
        $this->setViewParameter('action', $action);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('page', $page);
        $this->setViewParameter('infoDocument', $infoDocument);

        if (\in_array($action, ['suppression', 'supression'], true)) {
            $this->renderDeleteConfirmation(
                "Suppression du dossier « {$infoDocument['titre']} »",
                new DeleteConfirmation(
                    'Document/action',
                    "Document/detail?id_d=$id_d&id_e=$id_e&page=$page",
                    [$infoDocument],
                    ['Identifiant' => 'id_d', 'Titre' => 'titre', 'Type' => fn () => $documentType->getName()],
                    ['id_d' => $id_d, 'id_e' => $id_e, 'page' => $page, 'action' => $action, 'go' => 1],
                )
            );
            return;
        }

        $this->setViewParameter('actionName', $theAction->getDoActionName($action));

        $this->setViewParameter('page_title', 'Attention ! Action irréversible');
        $this->setViewParameter('template_milieu', 'DocumentWarning');
        $this->renderDefault();
    }


    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    private function validTraitementParLot(array $input): void
    {
        $recuperateur = new Recuperateur($input);
        $id_e = $recuperateur->getInt('id_e', RootEntity::ID_E);
        $type = $recuperateur->get('type');

        if (!$type) {
            $this->redirect("/Document/index?id_e=$id_e");
        }
        if (!$id_e) {
            $this->redirect('/Document/index');
        }
        $this->checkDroitFor($id_e, $type, DroitType::EDITION);

        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('offset', $recuperateur->getInt('offset', 0));
        $this->setViewParameter('search', $recuperateur->get('search'));
        $this->setViewParameter('type', $type);
        $this->setViewParameter('filtre', $recuperateur->get('filtre'));
        $this->setViewParameter('limit', 20);
        $this->setViewParameter('id_e_menu', $id_e);
        $this->setViewParameter('infoEntite', $this->getInstance(EntityInfoService::class)->getInfo($id_e));
        $this->setViewParameter('type_e_menu', $type);
        $this->setViewParameter('url_retour', $recuperateur->get('url_retour'));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws Exception
     */
    public function traitementLotAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $this->validTraitementParLot($recuperateur->getAll());
        $id_e = $recuperateur->getInt('id_e', RootEntity::ID_E);
        $type = $recuperateur->get('type');

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);

        $this->searchDocument();
        $documentActionService = $this->getInstance(DocumentActionService::class);
        $listDocument = $documentActionService->addPossibleBatchActions(
            $id_e,
            $this->getId_u(),
            $this->getViewParameterByKey('listDocument')
        );

        $this->setViewParameter('documentTypeFactory', $this->getDocumentTypeFactory());
        $this->setViewParameter('theAction', $documentType->getAction());
        $this->setViewParameter('listDocument', $listDocument);
        $this->setViewParameter('all_action', $documentActionService->getAllPossibleBatchActions($listDocument));
        $this->setViewParameter('type_list', $this->getAllType($listDocument));

        $this->setNavigationInfo($id_e, "Document/list?type=$type");
        $this->setViewParameter(
            'page_title',
            \sprintf(
                'Traitement par lot pour les  documents %s pour %s',
                $documentType->getName(),
                $this->getViewParameterByKey('infoEntite')['denomination']
            )
        );
        $this->setViewParameter('template_milieu', 'DocumentTraitementLot');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function confirmTraitementLotAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_e = $recuperateur->getInt('id_e', RootEntity::ID_E);
        $type = $recuperateur->get('type');
        $search = $recuperateur->get('search');
        $filtre = $recuperateur->get('filtre');
        $offset = $recuperateur->getInt('offset', 0);
        $action_selected = (string) $recuperateur->get('action');
        $all_id_d = $recuperateur->get('id_d');

        $this->validTraitementParLot($recuperateur->getAll());

        $url_retour = sprintf(
            'Document/traitementLot?id_e=%s&type=%s&search=%s&filtre=%s&offset=%s',
            $id_e,
            $type,
            $search,
            $filtre,
            $offset
        );
        if (!$all_id_d || !is_array($all_id_d)) {
            $this->setLastError('Vous devez sélectionner au moins un document');
            $this->redirect($url_retour);
        }

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        try {
            $listDocument = $this->getInstance(DocumentActionService::class)->getDocumentsForBatchAction(
                $id_e,
                $this->getId_u(),
                $type,
                $all_id_d,
                $action_selected
            );
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect($url_retour);
        }

        $this->setViewParameter('url_retour', $url_retour);
        $this->setViewParameter('action_selected', $action_selected);
        $this->setViewParameter('theAction', $documentType->getAction());
        $this->setViewParameter('listDocument', $listDocument);

        $this->setViewParameter(
            'page_title',
            \sprintf(
                'Confirmation du traitement par lot pour les  documents %s pour %s',
                $documentType->getName(),
                $this->getViewParameterByKey('infoEntite')['denomination']
            )
        );
        $this->setViewParameter('template_milieu', 'DocumentConfirmTraitementLot');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws Exception
     */
    public function doTraitementLotAction(): void
    {
        $recuperateur = $this->getPostInfo();
        $id_e = $recuperateur->getInt('id_e', RootEntity::ID_E);
        $type = $recuperateur->get('type');
        $search = $recuperateur->get('search');
        $filtre = $recuperateur->get('filtre');
        $offset = $recuperateur->getInt('offset', 0);
        $action_selected = (string) $recuperateur->get('action');
        $all_id_d = $recuperateur->get('id_d');

        $this->validTraitementParLot($recuperateur->getAll());

        $url_retour = (string) $this->getViewParameterByKey('url_retour');
        if (!$all_id_d || !is_array($all_id_d)) {
            $this->setLastError('Vous devez sélectionner au moins un document');
            $this->redirect($url_retour);
        }

        try {
            $message = $this->getInstance(DocumentActionService::class)->executeBatch(
                $id_e,
                $this->getId_u(),
                $type,
                $all_id_d,
                $action_selected
            );
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect($url_retour);
        }

        $this->setLastMessage($message);
        $this->redirect(
            sprintf(
                'Document/list?id_e=%d&type=%s&search=%s&filtre=%s&offset=%s',
                $id_e,
                $type,
                $search,
                $filtre,
                $offset
            )
        );
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function retourTeletransmissionAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_e = $recuperateur->getInt('id_e', 0);
        $type = (string) $recuperateur->get('type');
        $all_id_d = $recuperateur->get('id_d');
        $action = (string) $recuperateur->get('action', 'return-teletransmission-tdt');

        $this->checkDroitFor($id_e, $type, DroitType::EDITION);

        $url_retour = "Document/list?id_e=$id_e&type=$type";
        if (!$all_id_d || !is_array($all_id_d)) {
            $this->setLastError('Vous devez sélectionner au moins un document');
            $this->redirect($url_retour);
        }

        try {
            $message = $this->getInstance(DocumentActionService::class)
                ->executeTeletransmissionReturn($id_e, $this->getId_u(), $type, $all_id_d, $action);
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect($url_retour);
        }

        $this->setLastMessage($message);
        $this->redirect($url_retour);
    }

    /**
     * @deprecated 4.1.26 Use DocumentReindexService instead
     * @throws NotFoundException
     */
    public function reindex($document_type, $all_field_name, $offset = 0, $limit = -1): void
    {
        $field_name_list = is_array($all_field_name) ? $all_field_name : [$all_field_name];
        $documentReindexService = $this->getInstance(DocumentReindexService::class);
        try {
            $document_list = $documentReindexService->getDocumentsToReindex(
                (string) $document_type,
                $field_name_list,
                (int) $offset,
                (int) $limit
            );
        } catch (UnrecoverableException $e) {
            echo '[ERREUR] ' . $e->getMessage() . "\n";
            return;
        }

        echo 'Nombre de documents : ' . count($document_list) . "\n";
        foreach ($document_list as $i => $document_info) {
            echo "Réindexation du document {$document_info['titre']} ({$document_info['id_d']})\n";
            $documentReindexService->reindexDocument($document_info['id_d'], $field_name_list);
            if (($i + 1) % 100 === 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     * @throws NotFoundException
     */
    public function fixModuleChamps($document_type, $old_field_name, $new_field_name): void
    {
        foreach ($this->getDocumentSQL()->getAllByType($document_type) as $document_info) {
            $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
            $value = $donneesFormulaire->get($old_field_name);
            $donneesFormulaire->setData($new_field_name, $value);
            $donneesFormulaire->deleteField($old_field_name);

            echo $document_info['id_d'] . " : OK\n";
        }
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function changeEtatAction(): void
    {
        $recuperateur = $this->getPostInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $action = (string) $recuperateur->get('action');
        $message = (string) $recuperateur->get('message');

        if (!$this->hasDroitFor(RootEntity::ID_E, DroitService::DROIT_SYSTEM, DroitType::EDITION)) {
            $this->redirect('');
        }

        try {
            $this->getInstance(DocumentActionService::class)
                ->changeState($id_e, $this->getId_u(), $id_d, $action, $message);
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect();
        }

        $this->setLastMessage("L'état du document a été modifié : -> $action");
        $this->redirect("Document/detail?id_d=$id_d&id_e=$id_e");
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     * @throws NotFoundException
     * @throws Exception
     */
    public function bulkModification($id_e, $type, $etat, $field_name, $field_value): int
    {
        $result = $this->getInstance(DocumentEntiteService::class)->getDocumentsByLastAction($id_e, $type, $etat);

        if (!$result) {
            throw new Exception("Il n'y a pas de document de type $type pour l'id_e $id_e");
        }
        foreach ($result as $document_info) {
            $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d'], $type);
            $donneesFormulaire->setData($field_name, $field_value);
        }
        return count($result);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function actionAction(): void
    {
        $recuperateur = $this->getPostInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $action = (string) $recuperateur->get('action');
        $id_e = $recuperateur->getInt('id_e');
        $page = $recuperateur->getInt('page', 0);
        $go = $recuperateur->getInt('go', 0);
        $id_destinataire = $recuperateur->get('destinataire') ?: [];

        $type = $this->getDocumentInfoOrRedirect($id_e, $id_d)['type'];
        $detail_url = "/Document/detail?id_d=$id_d&id_e=$id_e&page=$page";

        $this->checkDroitFor($id_e, $type, DroitType::EDITION, $detail_url);

        if (
            $action === CreationAction::ACTION_ID
            || !$this->getInstance(DocumentRoleService::class)->getRole($id_e, $id_d)
        ) {
            $this->setLastError("L'action « $action » n'est pas permise sur ce dossier");
            $this->redirect("/Document/list?id_e=$id_e");
        }

        $actionPossible = $this->getActionPossible();
        if (!$actionPossible->isActionPossible($id_e, $this->getId_u(), $id_d, $action)) {
            $this->setLastError(
                "L'action « $action »  n'est pas permise (elle a peut-être déjà été effectuée) : "
                . $actionPossible->getLastBadRule()
            );
            $this->redirect($detail_url);
        }

        if ($action === Action::MODIFICATION) {
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }

        $theAction = $this->getDocumentTypeFactory()->getFluxDocumentType($type)->getAction();
        $action_destinataire = $theAction->getActionDestinataire($action);
        if ($action_destinataire && !$id_destinataire) {
            $this->redirect("/Entite/choix?id_d=$id_d&id_e=$id_e&action=$action&type=$action_destinataire");
        }

        if ($theAction->getWarning($action) && !$go) {
            $this->redirect("/Document/warning?id_d=$id_d&id_e=$id_e&action=$action&page=$page");
        }

        try {
            $message = $this->getInstance(DocumentActionService::class)
                ->execute($id_e, $this->getId_u(), $id_d, $action, $id_destinataire);
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect($detail_url);
        }

        $this->setLastMessage($message);
        $this->redirect($detail_url);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function newAction(): void
    {
        $type = $this->getPostInfo()->get('type');
        $id_e = $this->getPostInfo()->getInt('id_e');
        try {
            $id_d = $this->getObjectInstancier()
                ->getInstance(DocumentCreationService::class)
                ->createDocument($id_e, $this->getId_u(), $type);
        } catch (Exception $e) {
            $this->setLastError('Impossible de créer le document : ' . $e->getMessage());
            $this->redirect("/Document/list?id_e=$id_e&type=$type");
        }

        $this->setLastMessage("Le document $id_d a été créé");
        $this->redirect("/Document/edition?id_e=$id_e&id_d=$id_d");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doEditionAction(): void
    {
        $recuperateur = $this->getPostInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $page = $recuperateur->getInt('page');
        $field_submitted = (string) $recuperateur->get('fieldSubmittedId');
        $add_file_button = $recuperateur->get('ajouter');
        $external_data_button = $recuperateur->get('external_data_button');
        $enregistrer = $recuperateur->get('enregistrer');

        try {
            $this->getInstance(DocumentModificationService::class)->modifyDocument(
                $id_e,
                $this->getId_u(),
                $id_d,
                $recuperateur,
                new FileUploader()
            );
            if ($field_submitted) {
                $this->getInstance(DocumentActionService::class)
                    ->executeOnChange($id_e, $this->getId_u(), $id_d, $field_submitted);
            }
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }

        if ($add_file_button) {
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }
        if ($external_data_button) {
            $this->redirect(urldecode($external_data_button));
        }
        // A tab was clicked: "enregistrer" holds the tab number
        if ($enregistrer !== 'enregistrer') {
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$enregistrer");
        }
        $this->redirect("/Document/detail?id_d=$id_d&id_e=$id_e&page=$page");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    private function checkEditionRight(int $id_e, string $type, string $id_d): void
    {
        if (!$this->getDroitService()->hasDroitFor($this->getId_u(), $id_e, $type, DroitType::EDITION)) {
            $this->setLastError(
                "Vous n'avez pas le droit de faire cette action (" .
                DroitService::getDroitFor($type, DroitType::EDITION) . ')'
            );
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e");
        }
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function externalDataAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $field = $recuperateur->get('field');
        $page = $recuperateur->getInt('page');

        $type = $this->getDocumentInfoOrRedirect($id_e, $id_d)['type'];
        $this->checkEditionRight($id_e, $type, $id_d);

        $theField = $this->getDocumentTypeFactory()->getFluxDocumentType($type)->getFormulaire()->getField($field);
        if (!$theField) {
            $this->setLastError("Le champ ($field) n'existe pas pour ce document.");
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e");
        }

        try {
            $this->getActionExecutorFactory()->displayChoice(
                $id_e,
                $this->getId_u(),
                $id_d,
                $theField->getProperties('choice-action'),
                false,
                $field,
                $page
            );
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws Exception
     */
    public function doExternalDataAction(): void
    {
        $recuperateur = new Recuperateur($_REQUEST);
        $id_d = (string) $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $field = $recuperateur->get('field');
        $page = $recuperateur->getInt('page', 0);

        $type = $this->getDocumentInfoOrRedirect($id_e, $id_d)['type'];
        $this->checkEditionRight($id_e, $type, $id_d);

        $actionPossible = $this->getActionPossible();
        if (!$actionPossible->isActionPossible($id_e, $this->getId_u(), $id_d, Action::MODIFICATION)) {
            $this->setLastError("L'action « modification »  n'est pas permise : " . $actionPossible->getLastBadRule());
            $this->redirect("/Document/detail?id_d=$id_d&id_e=$id_e&page=$page");
        }

        $formulaire = $this->getDocumentTypeFactory()->getFluxDocumentType($type)->getFormulaire();
        $formulaire->setTabNumber($page);
        $theField = $formulaire->getField($field);
        if (!$theField) {
            $this->setLastError("Le champ ($field) n'existe pas pour ce document.");
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }

        $this->getActionExecutorFactory()->goChoice(
            $id_e,
            $this->getId_u(),
            $id_d,
            $theField->getProperties('choice-action'),
            false,
            $field,
            $page
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function doExternalDataApiAction(): void
    {
        $recuperateur = $this->getPostOrGetInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $field = $recuperateur->get('field');

        $type = $this->getInstance(DocumentInfoService::class)->getType($id_d);
        if ($type === null) {
            echo "Ce dossier n'existe pas";
            return;
        }
        if (!$this->getDroitService()->hasDroitFor($this->getId_u(), $id_e, $type, DroitType::EDITION)) {
            echo "Vous n'avez pas le droit de faire cette action ($type:edition)";
            return;
        }

        $actionPossible = $this->getActionPossible();
        if (!$actionPossible->isActionPossible($id_e, $this->getId_u(), $id_d, Action::MODIFICATION)) {
            echo "L'action « modification »  n'est pas permise : " . $actionPossible->getLastBadRule();
            return;
        }

        $theField = $this->getDocumentTypeFactory()->getFluxDocumentType($type)->getFormulaire()->getField($field);
        if (!$theField) {
            echo "Le champ ($field) n'existe pas pour ce document.";
            return;
        }

        $this->getActionExecutorFactory()->goChoice(
            $id_e,
            $this->getId_u(),
            $id_d,
            $theField->getProperties('choice-action'),
            true,
            $field
        );
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     */
    public function recuperationFichierAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_d = (string) $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $field = (string) $recuperateur->get('field');
        $num = $recuperateur->getInt('num');

        // A document_email_reponse is checked against the rights on its document_email (#1486)
        $mail_info = $this->getDocumentEmailService()->getDocumentEmailFromIdReponse($id_d);
        $this->verifDroitLecture($id_e, $mail_info['id_d'] ?? $id_d);

        try {
            $documentFile = $this->getInstance(DocumentFileService::class)
                ->getFileToDownload($id_e, $this->getId_u(), $id_d, $field, $num);
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect();
        }

        $this->getInstance(SendFileToBrowser::class)->send($documentFile->path, $documentFile->name);
    }

    /**
     * @throws Exception
     */
    public function supprimerFichierAction(): void
    {
        $csrf_token = $this->getGetInfo()->get('csrf_token');
        $this->getObjectInstancier()->getInstance(CSRFToken::class)->verifParamToken($csrf_token);

        $id_d = $this->getGetInfo()->get('id_d');
        $page = $this->getGetInfo()->get('page');
        $id_e = $this->getGetInfo()->get('id_e');
        $field = $this->getGetInfo()->get('field');
        $num = $this->getGetInfo()->getInt('num', 0);

        $documentModificationService = $this->getObjectInstancier()
            ->getInstance(DocumentModificationService::class);

        try {
            $documentModificationService->removeFile($id_e, $this->getId_u(), $id_d, $field, $num);
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }

        $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function mailsecErrorAction(): void
    {
        $recuperateur = $this->getGetInfo();
        $id_de = $recuperateur->getInt('id_de');
        $id_e = $recuperateur->getInt('id_e');

        $documentEmailService = $this->getDocumentEmailService();
        $document_email_info = $documentEmailService->getRecipient($id_de);
        if ($document_email_info === null) {
            $this->setLastError("Ce destinataire n'existe pas");
            $this->redirect();
        }
        $this->verifDroitLecture($id_e, $document_email_info['id_d']);

        header_wrapper('Content-type: text/plain');
        echo $documentEmailService->getLastErrorWithoutKey($document_email_info);
    }

    private function isDocumentEmailChunkUpload(): bool
    {
        /* mailsec ? */
        $key = $this->getPostOrGetInfo()->get('key');
        $mailsec_info = $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class)->getByKey($key);
        if (! $mailsec_info) {
            return false;
        }
        $documentEmailReponseSQL = $this->getObjectInstancier()->getInstance(DocumentEmailReponseSQL::class);
        $id_d_reponse = $documentEmailReponseSQL->getDocumentReponseId($mailsec_info['id_de']);
        if ($this->getPostOrGetInfo()->get('id_d') != $id_d_reponse) {
            return false;
        }

        return true;
    }

    /**
     * @deprecated 4.1.26 Use DonneesFormulaireControler::chunkUploadAction() instead
     * @throws Exception
     */
    public function chunkUploadAction(): void
    {
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');
        $id_d = $this->getPostOrGetInfo()->get('id_d');
        $field = $this->getPostOrGetInfo()->get('field');
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $info = $this->getDocumentSQL()->getInfo($id_d);

        if (
            !$this->isDocumentEmailChunkUpload()
            && !$this->getDroitService()->hasDroitFor($this->getId_u(), $id_e, $info['type'], DroitType::EDITION)
        ) {
            echo 'KO';
            exit_wrapper();
        }

        $chunkUploader = $this->getInstance(ChunkUploader::class);
        $upload_filepath = $chunkUploader->getUploadChunkDirectory() . "/{$id_e}_{$id_d}_{$field}" . time() . '_' . random_int(0, mt_getrandmax());

        $this->getLogger()->debug(
            "Chargement partiel du fichier : $upload_filepath dans (id_e={$id_e},id_d={$id_d},field={$field}"
        );

        if ($chunkUploader->upload($upload_filepath)) {
            $documentModificationService =
                $this->getObjectInstancier()->getInstance(DocumentModificationService::class);

            if ($donneesFormulaire->getFormulaire()->getField($field)->isMultiple()) {
                $nb_file = $donneesFormulaire->get($field) ? count($donneesFormulaire->get($field)) : 0;
                $this->getLogger()->debug("ajout fichier $nb_file");
                $documentModificationService->addFile(
                    $id_e,
                    $this->getId_u(),
                    $id_d,
                    $field,
                    $nb_file,
                    $upload_filepath,
                    $chunkUploader->getUploadChunkDirectory()
                );
            } else {
                $documentModificationService->addFile(
                    $id_e,
                    $this->getId_u(),
                    $id_d,
                    $field,
                    0,
                    $upload_filepath,
                    $chunkUploader->getUploadChunkDirectory()
                );
            }
            $this->getLogger()->debug('chargement terminé');
            unlink($upload_filepath);
        }

        $chunkUploader->pruneChunks();
        echo 'OK';
        exit_wrapper();
    }
}
