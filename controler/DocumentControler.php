<?php

use Pastell\File\Chunk\ChunkUploader;

class DocumentControler extends PastellControler
{
    public function _beforeAction()
    {
        parent::_beforeAction();
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');
        $id_d = $this->getPostOrGetInfo()->get('id_d');
        $type = $this->getPostOrGetInfo()->get('type');


        if ($id_d && ! is_array($id_d)) {
            $document_info = $this->getDocumentSQL()->getInfo($id_d);
            $type = $document_info['type'];
            $this->setViewParameter('type_e_menu', $type);
        }

        $this->setNavigationInfo($id_e, "Document/list?type=$type");
    }

    public function renderDefault(): void
    {
        $this->setViewParameter(
            'show_choice_entity_message',
            !(bool)$this->getPostOrGetInfo()->getInt('id_e')
            && $this->getViewParameterByKey('id_e') === 0
        );
        parent::renderDefault();
    }

    /**
     * @return DocumentActionEntite
     */
    protected function getDocumentActionEntite()
    {
        return $this->getInstance(DocumentActionEntite::class);
    }

    private function redirectToList($id_e, $type = false)
    {
        $this->redirect("/Document/list?id_e=$id_e&type=$type");
    }

    private function verifDroitLecture($id_e, $id_d)
    {
        $info = $this->getDocumentSQL()->getInfo($id_d);
        if (!$info) {
            $this->redirectToList($id_e);
        }

        if (! $this->getDroitService()->hasDroit($this->getId_u(), $this->getDroitService()->getDroitLecture($info['type']), $id_e)) {
            $this->redirectToList($id_e, $info['type']);
        }

        $my_role = $this->getDocumentEntite()->getRole($id_e, $id_d);
        if (! $my_role) {
            $this->redirectToList($id_e, $info['type']);
        }
        return $info;
    }

    public function arAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_d = $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');

        $info_document = $this->verifDroitLecture($id_e, $id_d);


        $true_last_action = $this->getDocumentActionEntite()->getTrueAction($id_e, $id_d);
        /** @var DocumentType $documentType */
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($info_document['type']);

        $action = $documentType->getAction();
        if (! $action->getProperties($true_last_action, 'accuse_de_reception_action')) {
            $this->redirect("/Document/detail?id_e=$id_e&id_d=$id_d");
        }
        $this->setViewParameter('action', $action->getProperties($true_last_action, 'accuse_de_reception_action'));
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_d', $id_d);

        $this->setViewParameter('page_title', "Accusé de réception");
        $this->setViewParameter('template_milieu', "DocumentAR");
        $this->renderDefault();
    }

    public function detailAction()
    {
        $recuperateur = $this->getGetInfo();
        $id_d = $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $page = $recuperateur->getInt('page', 0);

        $info_document = $this->verifDroitLecture($id_e, $id_d);

        /** @var DocumentType $documentType */
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($info_document['type']);

        $true_last_action = $this->getDocumentActionEntite()->getTrueAction($id_e, $id_d);

        $action = $documentType->getAction();
        if ($action->getProperties($true_last_action, 'accuse_de_reception_action')) {
            $this->redirect("/Document/ar?id_e=$id_e&id_d=$id_d");
        }

        $this->getJournal()->addConsultation($id_e, $id_d, $this->getId_u());

        $this->setViewParameter('info', $info_document);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_d', $id_d);
        $this->setViewParameter('page', $page);
        $this->setViewParameter('documentType', $documentType);
        $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($id_e));
        $this->setViewParameter('formulaire', $documentType->getFormulaire());
        $this->setViewParameter('donneesFormulaire', $this->getDonneesFormulaireFactory()->get($id_d, $info_document['type']));
        $this->getViewParameterOrObject('donneesFormulaire')->getFormulaire()->setTabNumber($page);

        $this->setViewParameter('actionPossible', $this->getActionPossible());
        $this->setViewParameter('theAction', $documentType->getAction());
        $this->setViewParameter('documentEntite', $this->getDocumentEntite());
        $this->setViewParameter('my_role', $this->getDocumentEntite()->getRole($id_e, $id_d));
        $this->setViewParameter('documentEmail', $this->getInstance(DocumentEmail::class));
        $this->setViewParameter('documentActionEntite', $this->getDocumentActionEntite());

        $this->setViewParameter('next_action_automatique', $this->getViewParameterOrObject('theAction')->getActionAutomatique($true_last_action));
        $this->setViewParameter('droit_erreur_fatale', $this->getDroitService()->hasDroit($this->getId_u(), $this->getDroitService()->getDroitEdition($info_document['type']), 0));

        $this->setViewParameter('is_super_admin', $this->getRoleUtilisateur()->hasDroit($this->getId_u(), "system:edition", 0));
        if ($this->getViewParameterOrObject('is_super_admin')) {
            $this->setViewParameter('all_action', $documentType->getAction()->getWorkflowAction());
        }

        $this->setViewParameter('page_title', $info_document['titre'] . " (" . $documentType->getName() . ")");

        if ($documentType->isAfficheOneTab()) {
            $this->setViewParameter('fieldDataList', $this->getViewParameterOrObject('donneesFormulaire')->getFieldDataListAllOnglet($this->getViewParameterOrObject('my_role')));
        } else {
            $this->setViewParameter('fieldDataList', $this->getViewParameterOrObject('donneesFormulaire')->getFieldDataList($this->getViewParameterOrObject('my_role'), $page));
        }

        $document_email_reponse_list =
            $this->getObjectInstancier()->getInstance(DocumentEmailReponseSQL::class)->getAllReponse($id_d);


        $this->setViewParameter('document_email_reponse_list', $document_email_reponse_list);

        $this->setViewParameter('recuperation_fichier_url', "Document/recuperationFichier?id_d=$id_d&id_e=$id_e");
        if ($this->hasDroit($this->getViewParameterOrObject('id_e'), "system:lecture")) {
            $this->setViewParameter('job_list', $this->getWorkerSQL()->getJobListWithWorkerForDocument($this->getViewParameterOrObject('id_e'), $this->getViewParameterOrObject('id_d')));
        } else {
            $this->setViewParameter('job_list', false);
        }
        $this->setViewParameter('return_url', urlencode("Document/detail?id_e={$this->getViewParameterOrObject('id_e')}&id_d={$this->getViewParameterOrObject('id_d')}"));

        $this->setViewParameter('template_milieu', "DocumentDetail");
        $this->setViewParameter('inject', ['id_e' => $id_e,'id_ce' => '','id_d' => $id_d,'action' => $action]);

        $this->renderDefault();
    }

    /**
     * @throws Exception
     */
    public function detailMailReponseAction()
    {

        $id_e = $this->getPostOrGetInfo()->get('id_e');
        $id_d = $this->getPostOrGetInfo()->get('id_d');
        $id_d_reponse = $this->getPostOrGetInfo()->get('id_d_reponse');

        $info_document = $this->verifDroitLecture($id_e, $id_d);

        $reponse_info =
            $this->getObjectInstancier()
                ->getInstance(DocumentEmailReponseSQL::class)
                ->getInfoFromIdReponse($id_d_reponse);

        $mail_info =
            $this->getObjectInstancier()
                ->getInstance(DocumentEmail::class)
                ->getInfoFromPK($reponse_info['id_de']);

        if ($mail_info['id_d'] != $id_d) {
            $this->setLastError("Impossible de lire ce document");
            $this->redirect();
        }

        if (! $reponse_info['is_lu']) {
            $this->getObjectInstancier()
                ->getInstance(DocumentEmailReponseSQL::class)
                ->setLu($id_d_reponse);

            $this->getJournal()->add(
                Journal::MAIL_SECURISE,
                $id_e,
                $id_d,
                "Lecture d'une réponse",
                $this->getAuthentification()->getLogin() . " a lu la réponse de {$mail_info['email']} (id_de={$mail_info['id_de']}, id_d_reponse={$reponse_info['id_d_reponse']})"
            );
        }

        $this->setViewParameter('donneesFormulaire', $this->getDonneesFormulaireFactory()->get($id_d_reponse));
        $this->setViewParameter('fieldDataList', $this->getViewParameterOrObject('donneesFormulaire')->getFieldDataList("", 0));
        $this->setViewParameter('recuperation_fichier_url', "Document/recuperationFichier?id_d=$id_d_reponse&id_e=$id_e");

        $this->setViewParameter('page_title', $info_document['titre'] . " ( Réponse de " . $mail_info['email'] . ")");
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_d', $id_d);

        $this->setViewParameter('inject', [
            'id_d' => $id_d_reponse,
            'id_e' => $id_e,
            'id_ce' => false,
            'action' => false,
        ]);

        $this->setViewParameter('template_milieu', "DocumentMailReponse");
        $this->renderDefault();
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function editionAction()
    {
        $id_d = $this->getGetInfo()->get('id_d');
        $type = $this->getGetInfo()->get('type');
        $id_e = $this->getGetInfo()->getInt('id_e');
        $page = $this->getGetInfo()->getInt('page', 0);
        $action = $this->getGetInfo()->get('action');

        $document = $this->getDocumentSQL();

        if (! $id_d) {
            $this->setLastError("id_d n'a pas été fourni");
            $this->redirect("/Document/list");
        }

        $info = $document->getInfo($id_d);
        $type = $info['type'];
        if (! $action) {
            $action = 'modification';
        }

        $this->verifDroit($id_e, $type . ":edition", "/Document/list");

        $actionPossible = $this->getActionPossible();

        if (! $actionPossible->isActionPossible($id_e, $this->getId_u(), $id_d, $action)) {
            $this->setLastError("L'action « $action »  n'est pas permise : " . $actionPossible->getLastBadRule());
            header("Location: detail?id_d=$id_d&id_e=$id_e&page=$page");
            exit;
        }


        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);

        $infoEntite = $this->getEntiteSQL()->getInfo($id_e);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d, $type);

        $formulaire = $donneesFormulaire->getFormulaire();
        if (! $formulaire->tabNumberExists($page)) {
            $page = 0;
        }


        $this->setViewParameter('inject', ['id_e' => $id_e,'id_d' => $id_d,'form_type' => $type,'action' => $action,'id_ce' => '']);
        $this->setViewParameter('page_title', "Modification du dossier « " . $documentType->getName() . " » ( " . $infoEntite['denomination'] . " ) ");

        $this->setViewParameter('info', $info);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('id_d', $id_d);
        $this->setViewParameter('page', $page);
        $this->setViewParameter('type', $type);
        $this->setViewParameter('action', $action);
        $this->setViewParameter('documentType', $documentType);
        $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($id_e));
        $this->setViewParameter('formulaire', $documentType->getFormulaire());
        $this->setViewParameter('donneesFormulaire', $donneesFormulaire);
        $this->setViewParameter('ActionPossible', $this->getActionPossible());
        $this->setViewParameter('theAction', $documentType->getAction());
        $this->setViewParameter('documentEntite', $this->getDocumentEntite());
        $this->setViewParameter('my_role', $this->getDocumentEntite()->getRole($id_e, $id_d));
        $this->setViewParameter('documentEmail', $this->getInstance(DocumentEmail::class));
        $this->setViewParameter('documentActionEntite', $this->getDocumentActionEntite());

        $this->setViewParameter('action_url', "Document/doEdition");
        $this->setViewParameter('recuperation_fichier_url', "Document/recuperationFichier?id_d=$id_d&id_e=$id_e");
        $this->setViewParameter('suppression_fichier_url', "Document/supprimerFichier?id_d=$id_d&id_e=$id_e&page=$page&action=$action");
        $this->setViewParameter('externalDataURL', "Document/externalData") ;

        $this->setViewParameter('template_milieu', "DocumentEdition");
        $this->renderDefault();
    }


    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function indexAction()
    {
        $recuperateur = $this->getGetInfo();
        $id_e = $recuperateur->getInt('id_e', 0);
        $offset = $recuperateur->getInt('offset', 0);
        $search = $recuperateur->get('search');
        $limit = 20;

        $liste_type = [];
        $allDroit = $this->getDroitService()->getAllDroit($this->getId_u());

        foreach ($allDroit as $droit) {
            if (preg_match('/^(.*):lecture$/u', $droit, $result)) {
                $liste_type[] = $result[1];
            }
        }

        $liste_collectivite = $this->getRoleUtilisateur()->getEntiteWithSomeDroit($this->getId_u());
        if (! $id_e) {
            if (count($liste_collectivite) == 0) {
                $this->redirect("/Connexion/nodroit");
            }
            if (count($liste_collectivite) == 1) {
                $id_e = $liste_collectivite[0];
            }
        }
        if ($id_e) {
            foreach ($liste_type as $i => $the_type) {
                if (! $this->getDroitService()->hasDroit($this->getId_u(), $this->getDroitService()->getDroitLecture($the_type), $id_e)) {
                    unset($liste_type[$i]);
                }
            }
            if (! $liste_type) {
                $this->setLastError("Vous n'avez pas les droits nécessaires pour accéder à cette page");
                $this->redirect();
            }
        }

        $this->setViewParameter('tri', $recuperateur->get('tri', 'date_dernier_etat'));
        $this->setViewParameter('sens_tri', $recuperateur->get('sens_tri', 'DESC'));

        $this->setViewParameter('url_tri', false);


        if ($id_e) {
            $this->setViewParameter('listDocument', $this->getDocumentActionEntite()->getListDocumentByEntite($id_e, $liste_type, $offset, $limit, $search));
            $this->setViewParameter('count', $this->getDocumentActionEntite()->getNbDocumentByEntite($id_e, $liste_type, $search));
            $this->setViewParameter('type_list', $this->getAllType($this->getViewParameterOrObject('listDocument')));
        }

        $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($id_e));
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('search', $search);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('limit', $limit);
        $this->setViewParameter('url', "id_e=$id_e&search=$search");

        $this->setViewParameter('champs_affiches', DocumentType::getDefaultDisplayField());

        $this->setNavigationInfo($id_e, "Document/index?a=a");
        if ($this->getViewParameterOrObject('infoEntite')) {
            $this->setViewParameter('page_title', "Liste des dossiers " . $this->getViewParameterOrObject('infoEntite')['denomination']) ;
        } else {
            $this->setViewParameter('page_title', "Liste des dossiers");
        }

        $this->setViewParameter('template_milieu', "DocumentIndex");
        $this->renderDefault();
    }

    private function getAllType(array $listDocument)
    {
        $type = [];
        foreach ($listDocument as $doc) {
            $type[$doc['type']] = $doc['type'];
        }
        return array_keys($type);
    }

    public function listAction()
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

        if ($id_e == '0') {
            $this->redirect("/Document/index?id_e=$id_e&type=$type");
        }

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);

        $liste_collectivite = $this->getRoleUtilisateur()->getEntite($this->getId_u(), $type . ":lecture");

        if (! $liste_collectivite) {
            $this->redirect("/Document/index");
        }

        if (!$id_e && (count($liste_collectivite) == 1)) {
            $id_e = $liste_collectivite[0];
            $this->setViewParameter('id_e_menu', $id_e);
            $this->setViewParameter('type_e_menu', $type);
        }


        $this->verifDroit($id_e, "$type:lecture");
        $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($id_e));

        $page_title = "Liste des dossiers " . $documentType->getName();
        if ($id_e) {
            $page_title .= " pour " . $this->getViewParameterOrObject('infoEntite')['denomination'];
        }

        $this->setViewParameter('page_title', $page_title);
        $this->setViewParameter('documentActionEntite', $this->getDocumentActionEntite());
        $this->setViewParameter('ActionPossible', $this->getActionPossible());


        $this->setViewParameter('all_action', $documentType->getAction()->getWorkflowAction());


        if ($last_id) {
            $offset =  $this->getObjectInstancier()->getInstance(DocumentActionEntite::class)->getOffset($last_id, $id_e, $type, $limit);
        }

        if ($this->getActionPossible()->isCreationPossible($id_e, $this->getId_u(), $type)) {
            $this->setViewParameter('nouveau_bouton_url', ["Créer" => "Document/new?type=$type&id_e=$id_e"]);
        }
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('search', $search);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('limit', $limit);
        $this->setViewParameter('filtre', $filtre);
        $this->setViewParameter('last_id', $last_id);
        $this->setViewParameter('type', $type);
        $this->setViewParameter('url', "id_e=$id_e&search=$search&type=$type&lastetat=$filtre");

        $this->setViewParameter('tri', $recuperateur->get('tri', 'date_dernier_etat'));
        $this->setViewParameter('sens_tri', $recuperateur->get('sens_tri', 'DESC'));


        $this->setViewParameter('documentTypeFactory', $this->getDocumentTypeFactory());
        $this->setNavigationInfo($id_e, "Document/list?type=$type");

        $this->setViewParameter('champs_affiches', $documentType->getChampsAffiches());


        $this->setViewParameter('allDroitEntite', $this->getDroitService()->getAllDocumentLecture($this->getId_u(), $this->getViewParameterOrObject('id_e')));

        $this->setViewParameter('indexedFieldsList', $documentType->getFormulaire()->getIndexedFields());
        $indexedFieldValue = [];
        foreach ($this->getViewParameterOrObject('indexedFieldsList') as $indexField => $indexLibelle) {
            $indexedFieldValue[$indexField] = $recuperateur->get($indexField);
        }

        $this->setViewParameter('listDocument', $this->getDocumentActionEntite()->getListBySearch(
            $id_e,
            $type,
            $offset,
            $limit,
            $search,
            $filtre,
            false,
            false,
            $this->getViewParameterOrObject('tri'),
            $this->getViewParameterOrObject('allDroitEntite'),
            false,
            false,
            false,
            false,
            $indexedFieldValue,
            $this->getViewParameterOrObject('sens_tri')
        ));


        $this->setViewParameter('url_tri', "Document/list?id_e=$id_e&type=$type&search=$search&filtre=$filtre");

        $this->setViewParameter('type_list', $this->getAllType($this->getViewParameterOrObject('listDocument')));

        $this->setViewParameter('template_milieu', "DocumentList");
        $this->renderDefault();
    }

    public function searchDocument()
    {
        $recuperateur = new Recuperateur($_REQUEST);
        $this->setViewParameter('id_e', $recuperateur->getInt('id_e', 0));
        $this->setViewParameter('type', $recuperateur->get('type'));
        $this->setViewParameter('lastEtat', $recuperateur->get('lastetat'));
        $this->setViewParameter('last_state_begin', $recuperateur->get('last_state_begin'));
        $this->setViewParameter('last_state_end', $recuperateur->get('last_state_end'));
        $this->setViewParameter('state_begin', $recuperateur->get('state_begin'));
        $this->setViewParameter('state_end', $recuperateur->get('state_end'));


        $this->setViewParameter('last_state_begin_iso', getDateIso($this->getViewParameterOrObject('last_state_begin')));
        $this->setViewParameter('last_state_end_iso', getDateIso($this->getViewParameterOrObject('last_state_end')));
        $this->setViewParameter('state_begin_iso', getDateIso($this->getViewParameterOrObject('state_begin')));
        $this->setViewParameter('state_end_iso', getDateIso($this->getViewParameterOrObject('state_end')));

        if (! $this->getViewParameterOrObject('id_e')) {
            $error_message = "id_e est obligatoire";
            $this->setLastError($error_message);
            $this->redirect("");
        }
        $this->verifDroit($this->getViewParameterOrObject('id_e'), "entite:lecture");

        $this->setViewParameter('allDroitEntite', $this->getDroitService()->getAllDocumentLecture($this->getId_u(), $this->getViewParameterOrObject('id_e')));

        $this->setViewParameter('etatTransit', $recuperateur->get('etatTransit'));
        $this->setViewParameter('notEtatTransit', $recuperateur->get('notEtatTransit'));

        $this->setViewParameter('tri', $recuperateur->get('tri', 'date_dernier_etat'));
        $this->setViewParameter('sens_tri', $recuperateur->get('sens_tri', 'DESC'));
        $this->setViewParameter('go', $recuperateur->get('go', 0));
        $this->setViewParameter('offset', $recuperateur->getInt('offset', 0));
        $this->setViewParameter('search', $recuperateur->get('search'));
        $this->setViewParameter('limit', $recuperateur->getInt('limit', 100));

        $indexedFieldValue = [];
        if ($this->getViewParameterOrObject('type')) {
            $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($this->getViewParameterOrObject('type'));
            $this->setViewParameter('indexedFieldsList', $documentType->getFormulaire()->getIndexedFields());

            foreach ($this->getViewParameterOrObject('indexedFieldsList') as $indexField => $indexLibelle) {
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


        $allDroit = $this->getDroitService()->getAllDroit($this->getId_u());
        $this->setViewParameter('listeEtat', $this->getDocumentTypeFactory()->getActionByRole($allDroit));

        $this->setViewParameter('documentActionEntite', $this->getDocumentActionEntite());
        $this->setViewParameter('documentTypeFactory', $this->getDocumentTypeFactory());

        $this->setViewParameter('my_id_e', $this->getViewParameterOrObject('id_e'));


        try {
            $this->setViewParameter(
                'listDocument',
                $this->apiGet(
                    sprintf('entite/%d/document', $this->getViewParameterOrObject('id_e'))
                )
            );
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("");
        }

        $url_tri = \sprintf(
            'Document/search?id_e=%s&search=%s&type=%s&lastetat=%s&last_state_begin=%s&last_state_end=%s' .
            '&etatTransit=%s&state_begin=%s&state_end=%s&date_in_fr=true',
            $this->getViewParameterByKey('id_e'),
            $this->getViewParameterByKey('search'),
            $this->getViewParameterByKey('type'),
            $this->getViewParameterByKey('lastEtat'),
            $this->getViewParameterByKey('last_state_begin_iso'),
            $this->getViewParameterByKey('last_state_end_iso'),
            $this->getViewParameterByKey('etatTransit'),
            $this->getViewParameterByKey('state_begin_iso'),
            $this->getViewParameterByKey('state_end_iso'),
        );
        if ($this->getViewParameterOrObject('type')) {
            foreach ($indexedFieldValue as $indexName => $indexValue) {
                $url_tri .= "&" . urlencode($indexName) . "=" . urlencode($indexValue);
            }
        }

        $this->setViewParameter('url_tri', $url_tri);
        $this->setViewParameter('type_list', $this->getAllType($this->getViewParameterOrObject('listDocument')));
    }

    public function exportAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e', 0);
        $type = $recuperateur->get('type');
        $search = $recuperateur->get('search');

        $lastEtat = $recuperateur->get('lastetat');
        $last_state_begin = $recuperateur->get('last_state_begin');
        $last_state_end = $recuperateur->get('last_state_end');

        $last_state_begin_iso = getDateIso($last_state_begin);
        $last_state_end_iso = getDateIso($last_state_end);

        $etatTransit = $recuperateur->get('etatTransit');
        $state_begin =  $recuperateur->get('state_begin');
        $state_end =  $recuperateur->get('state_end');
        $notEtatTransit = $recuperateur->get('notEtatTransit');
        $tri =  $recuperateur->get('tri');
        $sens_tri = $recuperateur->get('sens_tri');

        $offset = 0;

        $allDroitEntite = $this->getDroitService()->getAllDocumentLecture($this->getId_u(), $id_e);


        $indexedFieldValue = [];
        if ($type) {
            $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
            $indexedFieldsList = $documentType->getFormulaire()->getIndexedFields();
            foreach ($indexedFieldsList as $indexField => $indexLibelle) {
                $indexedFieldValue[$indexField] = $recuperateur->get($indexField);
            }
            /*$champs_affiches = $documentType->getChampsAffiches();*/
        } else {
            //$champs_affiches = array('titre'=>'Objet','type'=>'Type','entite'=>'Entité','dernier_etat'=>'Dernier état','date_dernier_etat'=>'Date');
            $indexedFieldsList = [];
        }

        $limit = $this->getDocumentActionEntite()->getNbDocumentBySearch(
            $id_e,
            $type,
            $search,
            $lastEtat,
            $last_state_begin_iso,
            $last_state_end_iso,
            $allDroitEntite,
            $etatTransit,
            $state_begin,
            $state_end,
            $notEtatTransit,
            $indexedFieldValue
        );
        $listDocument = $this->getDocumentActionEntite()->getListBySearch(
            $id_e,
            $type,
            $offset,
            $limit,
            $search,
            $lastEtat,
            $last_state_begin_iso,
            $last_state_end_iso,
            $tri,
            $allDroitEntite,
            $etatTransit,
            $state_begin,
            $state_end,
            $notEtatTransit,
            $indexedFieldValue,
            $sens_tri
        );

        $line = ["ENTITE","ID_D","TYPE","TITRE","DERNIERE ACTION","DATE DERNIERE ACTION"];
        foreach ($indexedFieldsList as $indexField => $indexLibelle) {
            $line[] = $indexLibelle;
        }
        $result = [$line];
        foreach ($listDocument as $i => $document) {
             $line = [
                    $document['denomination'],
                    $document['id_d'],
                    $document['type'],
                    $document['titre'],
                    $document['last_action'],
                    $document['last_action_date'],

             ];
             foreach ($indexedFieldsList as $indexField => $indexLibelle) {
                 $line[] = $this->getInstance(DocumentIndexSQL::class)->get($document['id_d'], $indexField);
             }
             $result[] = $line;
        }

        $this->getInstance(CSVoutput::class)->sendAttachment(
            "pastell-export-$id_e-$type-$search-$lastEtat-$tri.csv",
            $result
        );
    }


    public function searchAction()
    {
        $this->searchDocument();
        $this->setViewParameter('page_title', "Recherche avancée de dossiers");
        $this->setViewParameter('template_milieu', "DocumentSearch");
        $this->renderDefault();
    }

    public function warningAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $this->setViewParameter('id_d', $recuperateur->get('id_d'));
        $this->setViewParameter('action', $recuperateur->get('action'));
        $this->setViewParameter('id_e', $recuperateur->get('id_e'));
        $this->setViewParameter('page', $recuperateur->getInt('page', 0));


        $this->setViewParameter('infoDocument', $this->getDocumentSQL()->getInfo($this->getViewParameterOrObject('id_d')));

        $type = $this->getViewParameterOrObject('infoDocument')['type'];
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $theAction = $documentType->getAction();

        $this->setViewParameter('actionName', $theAction->getDoActionName($this->getViewParameterOrObject('action')));

        $this->setViewParameter('page_title', "Attention ! Action irréversible");
        $this->setViewParameter('template_milieu', "DocumentWarning");
        $this->renderDefault();
    }


    private function validTraitementParLot($input)
    {
        $recuperateur = new Recuperateur($input);
        $this->setViewParameter('id_e', $recuperateur->get('id_e', 0));
        $this->setViewParameter('offset', $recuperateur->getInt('offset', 0));
        $this->setViewParameter('search', $recuperateur->get('search'));
        $this->setViewParameter('type', $recuperateur->get('type'));
        $this->setViewParameter('filtre', $recuperateur->get('filtre'));
        $this->setViewParameter('limit', 20);

        if (! $this->getViewParameterOrObject('type')) {
            $this->redirect("/Document/index?id_e={$this->getViewParameterOrObject('id_e')}");
        }
        if (!$this->getViewParameterOrObject('id_e')) {
            $this->redirect("/Document/index");
        }

        $this->setViewParameter('id_e_menu', $this->getViewParameterOrObject('id_e'));
        $this->verifDroit($this->getViewParameterOrObject('id_e'), "{$this->getViewParameterOrObject('type')}:lecture");
        $this->setViewParameter('infoEntite', $this->getEntiteSQL()->getInfo($this->getViewParameterOrObject('id_e')));

        $this->setViewParameter('id_e_menu', $this->getViewParameterOrObject('id_e'));
        $this->setViewParameter('type_e_menu', $this->getViewParameterOrObject('type'));
        $this->setViewParameter('url_retour', $recuperateur->get('url_retour'));
    }

    public function traitementLotAction()
    {
        $this->validTraitementParLot($_GET);
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($this->getViewParameterOrObject('type'));
        $page_title = "Traitement par lot pour les  documents " . $documentType->getName();
        $page_title .= " pour " . $this->getViewParameterOrObject('infoEntite')['denomination'];
        $this->setViewParameter('page_title', $page_title);

        $this->setViewParameter('documentTypeFactory', $this->getDocumentTypeFactory());
        $this->setNavigationInfo($this->getViewParameterOrObject('id_e'), "Document/list?type={$this->getViewParameterOrObject('type')}");
        $this->setViewParameter('theAction', $documentType->getAction());



        $this->searchDocument();
        $listDocument = $this->getViewParameterOrObject('listDocument');

        $all_action = [];
        foreach ($listDocument as $i => $document) {
            $listDocument[$i]['action_possible'] =  $this->getActionPossible()->getActionPossibleLot($this->getViewParameterOrObject('id_e'), $this->getId_u(), $document['id_d']);
            $all_action = array_merge($all_action, $listDocument[$i]['action_possible']);
        }
        $this->setViewParameter('listDocument', $listDocument);

        $all_action = array_unique($all_action);

        $this->setViewParameter('all_action', $all_action);
        $this->setViewParameter('type_list', $this->getAllType($this->getViewParameterOrObject('listDocument')));
        $this->setViewParameter('template_milieu', "DocumentTraitementLot");
        $this->renderDefault();
    }

    public function confirmTraitementLotAction()
    {
        $this->validTraitementParLot($_GET);
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($this->getViewParameterOrObject('type'));
        $this->setViewParameter('page_title', "Confirmation du traitement par lot pour les  documents " . $documentType->getName() . " pour " .
            $this->getViewParameterOrObject('infoEntite')['denomination']);

        $this->setViewParameter(
            'url_retour',
            sprintf(
                'Document/traitementLot?id_e=%s&type=%s&search=%s&filtre=%s&offset=%s',
                $this->getViewParameterOrObject('id_e'),
                $this->getViewParameterOrObject('type'),
                $this->getViewParameterOrObject('search'),
                $this->getViewParameterOrObject('filtre'),
                $this->getViewParameterOrObject('offset')
            )
        );

        $recuperateur = new Recuperateur($_GET);
        $this->setViewParameter('action_selected', $recuperateur->get('action'));
        $this->setViewParameter('theAction', $documentType->getAction());

        $action_libelle = $this->getViewParameterOrObject('theAction')->getActionName($this->getViewParameterOrObject('action_selected'));

        $all_id_d = $recuperateur->get('id_d');
        if (! $all_id_d) {
            $this->setLastError("Vous devez sélectionner au moins un document");
            $this->redirect($this->getViewParameterOrObject('url_retour'));
        }

        $error = "";
        $listDocument = [];

        foreach ($all_id_d as $id_d) {
            $infoDocument  = $this->getDocumentActionEntite()->getInfo($id_d, $this->getViewParameterOrObject('id_e'));
            if (! $this->getActionPossible()->isActionPossible($this->getViewParameterOrObject('id_e'), $this->getId_u(), $id_d, $this->getViewParameterOrObject('action_selected'))) {
                $error .= "L'action « $action_libelle » n'est pas possible pour le document « {$infoDocument['titre']} »<br/>";
            }
            if ($this->getInstance(JobManager::class)->hasActionProgramme($this->getViewParameterOrObject('id_e'), $id_d)) {
                $error .= "Il y a déjà une action programmée pour le document « {$infoDocument['titre']} »<br/>";
            }
            $listDocument[] = $infoDocument;
        }
        if ($error) {
            $this->setLastError($error . "<br/><br/>Aucune action n'a été executée");
            $this->redirect($this->getViewParameterOrObject('url_retour'));
        }

        $this->setViewParameter('listDocument', $listDocument);
        $this->setViewParameter('template_milieu', "DocumentConfirmTraitementLot");
        $this->renderDefault();
    }

    public function doTraitementLotAction()
    {
        $this->validTraitementParLot($_POST);
        $recuperateur = new Recuperateur($_POST);
        $action_selected = $recuperateur->get('action');
        $all_id_d = $recuperateur->get('id_d');
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($this->getViewParameterOrObject('type'));

        $action_libelle = $documentType->getAction()->getDoActionName($action_selected);

        $error = "";
        $message = "";
        foreach ($all_id_d as $id_d) {
            $infoDocument  = $this->getDocumentActionEntite()->getInfo($id_d, $this->getViewParameterOrObject('id_e'));
            if (! $this->getActionPossible()->isActionPossible($this->getViewParameterOrObject('id_e'), $this->getId_u(), $id_d, $action_selected)) {
                $error .= "L'action « $action_libelle » n'est pas possible pour le document « {$infoDocument['titre']} »<br/>";
            }

            if ($this->getInstance(JobManager::class)->hasActionProgramme($this->getViewParameterOrObject('id_e'), $id_d)) {
                $error .= "Il y a déjà une action programmée pour le document « {$infoDocument['titre']} »<br/>";
            }

            $listDocument[] = $infoDocument;
            $document_titre = $infoDocument['titre'] ?: $id_d;
            $message .= "L'action « $action_libelle » est programmée pour le document « {$document_titre} »<br/>";
        }
        if ($error) {
            $this->setLastError($error . "<br/><br/>Aucune action n'a été executée");
            $this->redirect($this->getViewParameterOrObject('url_retour'));
        }

        $this->getActionExecutorFactory()->executeLotDocument($this->getViewParameterOrObject('id_e'), $this->getId_u(), $all_id_d, $action_selected);
        $this->setLastMessage($message);
        $url_retour = sprintf(
            'Document/list?id_e=%d&type=%s&search=%s&filtre=%s&offset=%s',
            $this->getViewParameterOrObject('id_e'),
            $this->getViewParameterOrObject('type'),
            $this->getViewParameterOrObject('search'),
            $this->getViewParameterOrObject('filtre'),
            $this->getViewParameterOrObject('offset')
        );
        $this->redirect($url_retour);
    }

    /**
     * @throws Exception
     */
    public function retourTeletransmissionAction()
    {

        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->get('id_e', 0);
        $id_u = $recuperateur->get('id_u');
        $type = $recuperateur->get('type');
        $all_id_d = $recuperateur->get('id_d');
        $action = $recuperateur->get('action', 'return-teletransmission-tdt');

        $url_retour = "Document/list?id_e={$id_e}&type={$type}";
        $message = "";

        /* FIXME FIXME : Il y a une référence vers un connecteur !!! */
        /** @var TdtConnecteur $tdt */
        $tdt = $this->getConnecteurFactory()->getConnecteurByType($id_e, $type, 'TdT');

        $stringMapper = $this->getDocumentTypeFactory()->getFluxDocumentType($type)->getAction()->getConnecteurMapper($action);


        foreach ($all_id_d as $id_d) {
            $infoDocument  = $this->getDocumentActionEntite()->getInfo($id_d, $id_e);
            $listDocument[] = $infoDocument;

            $tedetis_transaction_id = $this->getDonneesFormulaireFactory()->get($id_d)->get($stringMapper->get('tedetis_transaction_id'));
            $status =  $tdt->getStatus($tedetis_transaction_id);

            if (in_array($status, [TdtConnecteur::STATUS_ACTES_EN_ATTENTE_DE_POSTER])) {
                $message .= "La transaction pour le document « {$infoDocument['titre']} » n'a pas le bon status : " . TdtConnecteur::getStatusString($status) . " trouvé<br/>";
            } else {
                $this->getActionChange()->addAction($id_d, $id_e, $id_u, $stringMapper->get("send-tdt"), "Le document a été télétransmis à la préfecture");
                $message .= "Le document « {$infoDocument['titre']} » a été télétransmis<br/>";
            }
            /** @var JobManager $jobManager */
            $jobManager = $this->getInstance(JobManager::class);
            $jobManager->setJobForDocument($id_e, $id_d, "suite traitement par lot");
        }

        $this->setLastMessage($message);
        $this->redirect($url_retour);
    }


    public function reindex($document_type, $all_field_name, $offset = 0, $limit = -1)
    {
        if (! $this->getDocumentTypeFactory()->isTypePresent($document_type)) {
            echo "[ERREUR] Le type de dossier $document_type n'existe pas sur cette plateforme.\n";
            return;
        }
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($document_type);
        $formulaire = $documentType->getFormulaire();

        if (!is_array($all_field_name)) {
            $all_field_name = [$all_field_name];
        }
        foreach ($all_field_name as $field_name) {
            $field = $formulaire->getField($field_name);
            if (!$field) {
                echo "[ERREUR] Le champs $field_name n'existe pas pour le type de dossier $document_type\n";
                return;
            }
            if (!$field->isIndexed()) {
                echo "[ERREUR] Le champs $document_type:$field_name n'est pas indexé\n";
                return;
            }
        }

        $document_list = $this->getDocumentSQL()->getAllByType($document_type);
        if ($limit > 0) {
            $document_list = array_slice($document_list, $offset, $limit);
        }
        echo "Nombre de documents : " . count($document_list) . "\n";
        $document_index = 0;

        foreach ($document_list as $document_info) {
            $id_d = $document_info['id_d'];
            echo "Réindexation du document {$document_info['titre']} ($id_d)\n";
            $documentIndexor = new DocumentIndexor($this->getInstance(DocumentIndexSQL::class), $id_d);
            $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
            foreach ($all_field_name as $field_name) {
                $fieldData = $donneesFormulaire->getFieldData($field_name);

                $documentIndexor->index($field_name, $fieldData->getValueForIndex());
            }
            // Libération mémoire; GC par paquet pour optimiser la fréquence d'appel
            $this->getViewParameterOrObject('DonneesFormulaireFactory')->clearCache();
            if (++$document_index % 100 == 0) {
                gc_collect_cycles();
            }
        }
    }

    public function fixModuleChamps($document_type, $old_field_name, $new_field_name)
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
     */
    public function changeEtatAction()
    {
        if (!$this->getRoleUtilisateur()->hasDroit($this->getId_u(), "system:edition", 0)) {
            $this->redirect("");
        }

        $recuperateur = $this->getPostInfo();
        $id_d = $recuperateur->get('id_d');
        $id_e = $recuperateur->getInt('id_e');
        $action = $recuperateur->get('action');
        $message = $recuperateur->get('message');

        $role = $this->getDocumentEntite()->getRole($id_e, $id_d);
        if (!$role) {
            $this->setLastError("Le document $id_d n'appartient pas à l'entité $id_e");
            $this->redirect();
        }
        $infoDocument = $this->getDocumentSQL()->getInfo($id_d);
        $type = $infoDocument['type'];

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $actions = $documentType->getAction()->getAll();

        if (!in_array($action, $actions, true)) {
            $this->setLastError("L'action $action n'existe pas");
            $this->redirect();
        }

        $this->getActionChange()->addAction(
            $id_d,
            $id_e,
            $this->getId_u(),
            $action,
            "Modification manuelle de l'état - $message"
        );
        $this->setLastMessage("L'état du document a été modifié : -> $action");

        $this->redirect("Document/detail?id_d=$id_d&id_e=$id_e");
    }

    public function bulkModification($id_e, $type, $etat, $field_name, $field_value)
    {
        $result = $this->getDocumentActionEntite()->getDocument($id_e, $type, $etat);

        if (!$result) {
            throw new Exception("Il n'y a pas de document de type $type pour l'id_e $id_e");
        }
        foreach ($result as $document_info) {
            $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d'], $type);
            $donneesFormulaire->setData($field_name, $field_value);
        }
        return count($result);
    }

    public function actionAction()
    {

        $recuperateur = $this->getPostOrGetInfo();
        $id_d = $recuperateur->get('id_d');
        $action = $recuperateur->get('action');
        $id_e = $recuperateur->get('id_e');
        $page = $recuperateur->getInt('page', 0);
        $go = $recuperateur->getInt('go', 0);

        $document = $this->getDocumentSQL();
        $infoDocument = $document->getInfo($id_d);
        $type = $infoDocument['type'];

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $theAction = $documentType->getAction();


        $actionPossible = $this->getActionPossible();

        $this->verifDroit($id_e, "$type:edition", "/Document/detail?id_d=$id_d&id_e=$id_e&page=$page");

        if (! $actionPossible->isActionPossible($id_e, $this->getId_u(), $id_d, $action)) {
            $this->setLastError("L'action « $action »  n'est pas permise (elle a peut-être déjà été effectuée) : " . $actionPossible->getLastBadRule());
            $this->redirect("/Document/detail?id_d=$id_d&id_e=$id_e&page=$page");
        }

        if ($action == Action::MODIFICATION) {
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }


        $id_destinataire = $recuperateur->get('destinataire') ?: [];

        $action_destinataire =  $theAction->getActionDestinataire($action);
        if ($action_destinataire) {
            if (! $id_destinataire) {
                $this->redirect("/Entite/choix?id_d=$id_d&id_e=$id_e&action=$action&type=$action_destinataire");
            }
        }

        if ($theAction->getWarning($action) && ! $go) {
            $this->redirect("/Document/warning?id_d=$id_d&id_e=$id_e&action=$action&page=$page");
        }
        $result = $this->getActionExecutorFactory()->executeOnDocument($id_e, $this->getId_u(), $id_d, $action, $id_destinataire);
        $message = $this->getActionExecutorFactory()->getLastMessage();
        if ($action === FatalError::ACTION_ID && $go) {
            $this->getInstance(JobManager::class)->deleteDocumentForAllEntities($id_d);
        }
        if (! $result) {
            $this->setLastError($message);
        } else {
            $this->setLastMessage($message);
        }
        $this->redirect("/Document/detail?id_d=$id_d&id_e=$id_e&page=$page");
    }

    public function newAction()
    {
        $type = $this->getPostInfo()->get('type');
        $id_e = $this->getPostInfo()->getInt('id_e');
        try {
            $id_d = $this->getObjectInstancier()
                ->getInstance(DocumentCreationService::class)
                ->createDocument($id_e, $this->getId_u(), $type);
        } catch (Exception $e) {
            $this->setLastError("Impossible de créer le document : " . $e->getMessage());
            $this->redirect("/Document/list?id_e=$id_e&type=$type");
        }

        $this->setLastMessage("Le document $id_d a été créé");
        $this->redirect("/Document/edition?id_e=$id_e&id_d=$id_d");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doEditionAction()
    {
        $id_d = $this->getPostInfo()->get('id_d');
        $id_e = $this->getPostInfo()->get('id_e');
        $page = $this->getPostInfo()->getInt('page');

        $documentModificationService =
            $this->getObjectInstancier()->getInstance(DocumentModificationService::class);

        try {
            $documentModificationService->modifyDocument(
                $id_e,
                $this->getId_u(),
                $id_d,
                $this->getPostInfo(),
                new FileUploader()
            );

            $fieldSubmitted = $this->getPostInfo()->get('fieldSubmittedId');
            if ($fieldSubmitted) {
                $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
                $field = $donneesFormulaire->getFormulaire()->getField($fieldSubmitted);
                if ($field) {
                    $onchange = $field->getOnChange();
                    if ($onchange) {
                        $this->getActionExecutorFactory()->executeOnDocument($id_e, $this->getId_u(), $id_d, $onchange);
                    }
                }
            }
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }

        /* On a appuyer sur un bouton "Ajouter un fichier" */
        if ($this->getPostInfo()->get('ajouter')) {
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }

        /* On a appuyer sur un bouton de type externalData */
        if ($this->getPostInfo()->get('external_data_button')) {
            $this->redirect(urldecode($this->getPostInfo()->get('external_data_button')));
        }

        /* On a appuyer sur un onglet */
        $enregistrer = $this->getPostInfo()->get('enregistrer');
        if ($enregistrer !== 'enregistrer') {
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$enregistrer");
        }

        /* On a appuyer sur le bouton enregistrer */
        $this->redirect("/Document/detail?id_d=$id_d&id_e=$id_e&page=$page");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function externalDataAction()
    {
        $recuperateur = $this->getGetInfo();
        $id_d = $recuperateur->get('id_d');
        $id_e = $recuperateur->get('id_e');
        $field = $recuperateur->get('field');
        $page = $recuperateur->get('page');

        $document = $this->getDocumentSQL();

        $info = $document->getInfo($id_d);
        $type = $info['type'];
        if (! $this->getDroitService()->hasDroit($this->getId_u(), $this->getDroitService()->getDroitEdition($type), $id_e)) {
            $this->setLastError("Vous n'avez pas le droit de faire cette action ($type:edition)");
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e");
        }

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $formulaire = $documentType->getFormulaire();

        $theField = $formulaire->getField($field);
        if (!$theField) {
            $this->setLastError("Le champ ($field) n'existe pas pour ce document.");
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e");
        }

        try {
            $action_name = $theField->getProperties('choice-action');
            $this->getActionExecutorFactory()->displayChoice($id_e, $this->getId_u(), $id_d, $action_name, false, $field, $page);
        } catch (Exception $e) {
            $this->setLastError($e->getMessage());
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e&page=$page");
        }
    }

    public function doExternalDataAction()
    {
        $recuperateur = new Recuperateur($_REQUEST);
        $id_d = $recuperateur->get('id_d');
        $id_e = $recuperateur->get('id_e');
        $field = $recuperateur->get('field');
        $page = $recuperateur->getInt('page', 0);

        $document = $this->getDocumentSQL();
        $info = $document->getInfo($id_d);
        $type = $info['type'];

        if (! $this->getDroitService()->hasDroit($this->getId_u(), $this->getDroitService()->getDroitEdition($type), $id_e)) {
            $this->setLastError("Vous n'avez pas le droit de faire cette action ($type:edition)");
            $this->redirect("/Document/edition?id_d=$id_d&id_e=$id_e");
        }

        $actionPossible = $this->getActionPossible();


        if (! $actionPossible->isActionPossible($id_e, $this->getId_u(), $id_d, 'modification')) {
            $this->setLastError("L'action « modification »  n'est pas permise : " . $actionPossible->getLastBadRule());
            header("Location: detail?id_d=$id_d&id_e=$id_e&page=$page");
            exit;
        }

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $formulaire = $documentType->getFormulaire();
        $formulaire->setTabNumber($page);

        $theField = $formulaire->getField($field);


        $action_name = $theField->getProperties('choice-action');

        $this->getActionExecutorFactory()->goChoice($id_e, $this->getId_u(), $id_d, $action_name, false, $field, $page);
    }

    /**
     * @throws Exception
     */
    public function doExternalDataApiAction()
    {
        $recuperateur = $this->getPostOrGetInfo();
        $id_d = $recuperateur->get('id_d');
        $id_e = $recuperateur->get('id_e');
        $field = $recuperateur->get('field');

        $document = $this->getDocumentSQL();
        $info = $document->getInfo($id_d);
        $type = $info['type'];

        if (! $this->getDroitService()->hasDroit($this->getId_u(), $this->getDroitService()->getDroitEdition($type), $id_e)) {
            echo "Vous n'avez pas le droit de faire cette action ($type:edition)";
            return;
        }

        $actionPossible = $this->getActionPossible();

        if (! $actionPossible->isActionPossible($id_e, $this->getId_u(), $id_d, 'modification')) {
            echo "L'action « modification »  n'est pas permise : " . $actionPossible->getLastBadRule();
            return;
        }

        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($type);
        $formulaire = $documentType->getFormulaire();

        $theField = $formulaire->getField($field);

        $action_name = $theField->getProperties('choice-action');

        $this->getActionExecutorFactory()->goChoice($id_e, $this->getId_u(), $id_d, $action_name, true, $field);
    }


    public function recuperationFichierAction()
    {
        $recuperateur = $this->getGetInfo();
        $id_d = $recuperateur->get('id_d');
        $id_e = $recuperateur->get('id_e');
        $field = $recuperateur->get('field');
        $num = $recuperateur->getInt('num');

        // Si l'id_d est un document_email_reponse alors on vérifie les droits sur le document_email, issue #1486
        $mail_info = $this->getDocumentEmailService()->getDocumentEmailFromIdReponse($id_d);
        if (!empty($mail_info)) {
            $this->verifDroitLecture($id_e, $mail_info['id_d']);
        } else {
            $this->verifDroitLecture($id_e, $id_d);
        }

        $document = $this->getDocumentSQL();
        $info = $document->getInfo($id_d);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d, $info['type']);

        $file_path = $donneesFormulaire->getFilePath($field, $num);
        $file_name_array = $donneesFormulaire->get($field);
        $file_name = $file_name_array[$num];

        if (! file_exists($file_path)) {
            $this->setLastError("Ce fichier n'existe pas");
            $this->redirect();
        }

        $utilisateur = new UtilisateurSQL($this->getSQLQuery());
        $infoUtilisateur = $utilisateur->getInfo($this->getId_u());
        $nom = $infoUtilisateur['prenom'] . " " . $infoUtilisateur['nom'];

        $this->getJournal()->add(
            Journal::DOCUMENT_CONSULTATION,
            $id_e,
            $id_d,
            "Consulté",
            "$nom a consulté le document $file_name"
        );

        if (mb_strlen($file_name) > 80) {
            $pos = mb_strrpos($file_name, ".");
            $name = mb_substr($file_name, 0, $pos);
            $extension = mb_substr($file_name, $pos + 1, mb_strlen($file_name));
            $file_name = mb_substr($name, 0, 76) . "." . $extension;
        }

        $sendFileToBrowser = $this->getObjectInstancier()->getInstance(SendFileToBrowser::class);
        $sendFileToBrowser->send($file_path, $file_name);
    }

    /**
     * @throws Exception
     */
    public function supprimerFichierAction()
    {
        $id_d = $this->getPostInfo()->get('id_d');
        $page = $this->getPostInfo()->get('page');
        $id_e = $this->getPostInfo()->get('id_e');
        $field = $this->getPostInfo()->get('field');
        $num = $this->getPostInfo()->getInt('num', 0);

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

    public function mailsecErrorAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_de = $recuperateur->get('id_de');
        $id_e = $recuperateur->get('id_e');
        /** @var DocumentEmail $documentEmail */
        $documentEmail = $this->getViewParameterOrObject('DocumentEmail');
        $info = $documentEmail->getInfoFromPK($id_de);

        $this->verifDroitLecture($id_e, $info['id_d']);

        header_wrapper("Content-type: text/plain");
        echo str_replace($info['key'], "XXXX-LA-CLE-NE-PEUT-ETRE-DIVULGUEE-ICI-XXXX", $info['last_error']);
    }

    private function isDocumentEmailChunkUpload()
    {
        /* mailsec ? */
        $key = $this->getPostOrGetInfo()->get('key');
        $documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        $mailsec_info = $documentEmail->getInfoFromKey($key);
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
     * @throws Exception
     */
    public function chunkUploadAction()
    {

        $id_e = $this->getPostOrGetInfo()->getInt('id_e');
        $id_d = $this->getPostOrGetInfo()->get('id_d');
        $page = $this->getPostOrGetInfo()->getInt('page');
        $field = $this->getPostOrGetInfo()->get('field');
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);


        $info = $this->getDocumentSQL()->getInfo($id_d);

        if (
            !$this->isDocumentEmailChunkUpload()
            && !$this->getDroitService()->hasDroit($this->getId_u(), $this->getDroitService()::getDroitEdition($info['type']), $id_e)
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
