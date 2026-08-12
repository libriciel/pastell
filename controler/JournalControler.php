<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Menu\MenuGaucheOption;
use Pastell\Service\Menu\MenuGaucheService;

class JournalControler extends PastellControler
{
    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function _beforeAction()
    {
        parent::_beforeAction();
        $id_e = $this->getPostOrGetInfo()->getInt('id_e');
        $id_d = $this->getPostOrGetInfo()->get('id_d');
        $type = $this->getPostOrGetInfo()->get('type');


        if ($id_d) {
            $document_info = $this->getDocumentSQL()->getInfo($id_d);
            $type = $document_info['type'];
            $this->setViewParameter('type_e_menu', $type);
        }


        $this->setNavigationInfo($id_e, "Journal/index?type=$type");
        $this->setMenuGaucheSelect(MenuGaucheOption::buildUrl(MenuGaucheService::JOURNAL_INDEX, ['type' => $type]));

        if ($id_d || $type) {
            $this->setDocumentMenuGauche($id_e);
        }
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function exportAction()
    {

        $recuperateur = new Recuperateur($_REQUEST);
        $id_e = $recuperateur->getInt('id_e', EntiteSQL::ID_E_ENTITE_RACINE);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('type', $recuperateur->get('type'));
        $this->setViewParameter('id_d', $recuperateur->get('id_d'));
        $this->setViewParameter('id_u', $recuperateur->get('id_u'));

        $this->checkDroitFor($id_e, DroitService::DROIT_JOURNAL, DroitType::LECTURE);

        $this->setViewParameter('entite_info', $this->getEntiteSQL()->getInfo($this->getViewParameterByKey('id_e')));
        $this->setViewParameter('utilisateur_info', $this->getUtilisateur()->getInfo($this->getViewParameterByKey('id_u')));
        $this->setViewParameter('document_info', $this->getDocumentSQL()->getInfo($this->getViewParameterByKey('id_d')));


        $this->setViewParameter('recherche', $recuperateur->get('recherche'));
        $this->setViewParameter('date_debut', $recuperateur->get('date_debut', date("Y-m-d")));
        $this->setViewParameter('date_fin', $recuperateur->get('date_fin', date("Y-m-d")));

        $this->setViewParameter('page_title', "Journal des événements - Export");
        $this->setViewParameter('template_milieu', "JournalExport");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function detailAction(): void
    {
        $recuperateur = new Recuperateur($_GET);
        $id_j = $recuperateur->getInt('id_j');
        $id_e = $recuperateur->getInt('id_e');
        $offset = $recuperateur->getInt('offset');
        $type = $recuperateur->get('type');
        $id_d = $recuperateur->get('id_d');

        $this->setViewParameter('id_j', $id_j);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('id_e', $id_e);
        $this->setViewParameter('type', $type);
        $this->setViewParameter('id_d', $id_d);

        $info = $this->getJournal()->getAllInfo($id_j);
        if (!$info) {
            $this->setLastError("Événement introuvable");
            $this->redirect("Journal/index?id_e={$id_e}&type={$type}&id_d={$id_d}&offset={$offset}");
        }
        $this->setViewParameter('info', $info);
        $this->checkDroitFor($info['id_e'], DroitService::DROIT_JOURNAL, DroitType::LECTURE);

        /** @var OpensslTSWrapper $opensslTSWrapper */
        $opensslTSWrapper = $this->getInstance(OpensslTSWrapper::class);

        $this->setViewParameter('preuve_txt', $opensslTSWrapper->getTimestampReplyString($this->getViewParameterByKey('info')['preuve']));

        $horodateur = $this->getConnecteurFactory()->getGlobalConnecteur('horodateur');
        if ($horodateur) {
            /** @var HorodateurPastell $horodateur */
            try {
                    $horodateur->verify($this->getViewParameterByKey('info')['message_horodate'], $this->getViewParameterByKey('info')['preuve']);
                    $this->setViewParameter('preuve_is_ok', true);
            } catch (Exception $e) {
                $this->setViewParameter('preuve_is_ok', false);
                $this->setViewParameter('preuve_error', $e->getMessage());
            }
            if ($this->getViewParameterByKey('preuve_is_ok') == false) {
                try {
                    //OK, c'est pas terrible, mais ca permet d'éviter la gestiond d'une constante supplémentaire
                    //pour noter la position du journal au moment de la bascule iso-8859-1 => utf-8
                    $horodateur->verify(utf8_decode($this->getViewParameterByKey('info')['message_horodate']), $this->getViewParameterByKey('info')['preuve']);
                    $this->setViewParameter('preuve_is_ok', true);
                } catch (Exception $e) {
                    $this->setViewParameter('preuve_is_ok', false);
                    $this->setViewParameter('preuve_error', $e->getMessage());
                }
            }
        } else {
            $this->setViewParameter('preuve_is_ok', false);
            $this->setViewParameter('preuve_error', "Aucun horodateur n'est configuré");
        }

        $this->setViewParameter('page_title', "Événement numéro {$this->getViewParameterByKey('id_j')}");
        $this->setViewParameter('template_milieu', "JournalDetail");
        $this->renderDefault();
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function indexAction()
    {
        $recuperateur = new Recuperateur($_GET);
        $id_e = $recuperateur->getInt('id_e', 0);
        $this->setViewParameter('offset', $recuperateur->getInt('offset', 0));
        $this->setViewParameter('type', $recuperateur->get('type'));
        $this->setViewParameter('id_d', $recuperateur->get('id_d'));
        $this->setViewParameter('id_u', $recuperateur->getInt('id_u'));
        $this->setViewParameter('recherche', $recuperateur->get('recherche'));
        $this->setViewParameter('date_debut', $recuperateur->get('date_debut'));
        $this->setViewParameter('date_fin', $recuperateur->get('date_fin'));

        $liste_collectivite = $this->getRoleUtilisateur()->getEntite(
            $this->getId_u(),
            DroitService::getDroitFor(DroitService::DROIT_JOURNAL, DroitType::LECTURE)
        );

        if (! $liste_collectivite) {
            header('Location: ' . $this->getSiteBase());
            exit;
        }

        if (! $id_e && (count($liste_collectivite) == 1)) {
            $id_e = $liste_collectivite[0];
        }
        $this->checkDroitFor($id_e, DroitService::DROIT_JOURNAL, DroitType::LECTURE);
        $this->setViewParameter('id_e', $id_e);

        $infoEntite = $this->getEntiteSQL()->getInfo($this->getViewParameterByKey('id_e'));


        $this->setViewParameter('count', $this->getJournal()->countAll(
            $this->getViewParameterByKey('id_e'),
            $this->getViewParameterByKey('type'),
            $this->getViewParameterByKey('id_d'),
            $this->getViewParameterByKey('id_u'),
            $this->getViewParameterByKey('recherche'),
            $this->getViewParameterByKey('date_debut'),
            $this->getViewParameterByKey('date_fin')
        ));

        $page_title = "Journal des événements";
        if ($this->getViewParameterByKey('id_e')) {
            $page_title .= " - " . $infoEntite['denomination'];
        }
        if ($this->getViewParameterByKey('type')) {
            $page_title .= " - " . $this->getViewParameterByKey('type');
        }
        if ($this->getViewParameterByKey('id_d')) {
            $documentInfo = $this->getDocumentSQL()->getInfo($this->getViewParameterByKey('id_d'));
            $page_title .= " - " . $documentInfo['titre'];
        }

        if ($this->getViewParameterByKey('id_u')) {
            $id_u = $this->getViewParameterByKey('id_u');
            $infoUtilisateur = $this->getUtilisateur()->getInfo($id_u);
            $page_title .= ' - ' . $infoUtilisateur['login'];
        }

        $this->setViewParameter('limit', 20);
        $this->setViewParameter(
            'all',
            $this->getJournal()->getAll(
                $this->getViewParameterByKey('id_e'),
                $this->getViewParameterByKey('type'),
                $this->getViewParameterByKey('id_d'),
                $this->getViewParameterByKey('id_u'),
                $this->getViewParameterByKey('offset'),
                $this->getViewParameterByKey('limit'),
                $this->getViewParameterByKey('recherche'),
                $this->getViewParameterByKey('date_debut'),
                $this->getViewParameterByKey('date_fin'),
                false,
                false,
            )
        );
        $this->setViewParameter('liste_collectivite', $liste_collectivite);

        $this->setNavigationInfo($id_e, "Journal/index?a=a");

        $this->setDroitViewParameter($id_e, DroitService::DROIT_JOURNAL, DroitType::LECTURE);
        $this->setViewParameter('infoEntite', $infoEntite);
        $this->setViewParameter('page_title', $page_title);
        $this->setViewParameter('template_milieu', "JournalIndex");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doExportAction()
    {
        $recuperateur = new Recuperateur($_REQUEST);
        $id_e = $recuperateur->getInt('id_e', 0);
        $type = $recuperateur->get('type');
        $id_d = $recuperateur->get('id_d');
        $id_u = $recuperateur->getInt('id_u');
        $recherche = $recuperateur->get('recherche');
        $date_debut = $recuperateur->get('date_debut');
        $date_fin = $recuperateur->get('date_fin');
        $en_tete_colonne = $recuperateur->get('en_tete_colonne');

        $this->checkDroitFor($id_e, DroitService::DROIT_JOURNAL, DroitType::LECTURE);

        $date_debut = date_fr_to_iso($date_debut);
        $date_fin = date_fr_to_iso($date_fin);

        list($sql,$value) = $this->getJournal()->getQueryAll($id_e, $type, $id_d, $id_u, 0, -1, $recherche, $date_debut, $date_fin) ;


        $this->getSQLQuery()->useUnberfferedQuery();

        $this->getSQLQuery()->prepareAndExecute($sql, $value);
        $CSVoutput = new CSVoutput();
        $CSVoutput->displayHTTPHeader("pastell-export-journal-$id_e-$id_u-$type-$id_d.csv");

        $CSVoutput->begin();
        if ($en_tete_colonne) {
            $headers = [
                'id_journal', 'type', 'id_entite', 'id_utilisateur', 'id_document', 'action', 'message', 'date',
                'horodatage', 'message_horodate', 'type_document', 'numero_acte', 'collectivite_libelle',
                'utilisateur_nom', 'utilisateur_prenom', 'collectivite_siren'
            ];
            $CSVoutput->displayLine($headers);
        }
        while ($this->getSQLQuery()->hasMoreResult()) {
            $data = $this->getSQLQuery()->fetch();
            unset($data['preuve']);
            $CSVoutput->displayLine($data);
        }
        $CSVoutput->end();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function messageAction()
    {
        $recuperateur = new Recuperateur($_GET);

        $id_j = $recuperateur->get('id_j');

        $info  = $this->getJournal()->getInfo($id_j);

        $this->checkDroitFor($info['id_e'], DroitService::DROIT_JOURNAL, DroitType::LECTURE);


        header("Content-Type: text/plain; charset=utf-8");
        header("Content-disposition: attachment; filename=preuve.txt");
        header("Cache-Control: must-revalidate, post-check=0,pre-check=0");
        header("Pragma: public");

        echo $info['message_horodate'];
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function preuveAction()
    {

        $recuperateur = new Recuperateur($_GET);

        $id_j = $recuperateur->get('id_j');

        $info  = $this->getJournal()->getInfo($id_j);

        $this->checkDroitFor($info['id_e'], DroitService::DROIT_JOURNAL, DroitType::LECTURE);

        header("Content-Type: application/timestamp-reply");
        header("Content-Transfer-Encoding: base64");
        header("Content-disposition: attachment; filename=preuve.tsa");

        header("Cache-Control: must-revalidate, post-check=0,pre-check=0");
        header("Pragma: public");


        echo $info['preuve'];
    }
}
