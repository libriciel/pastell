<?php

use Pastell\Service\Menu\MenuGaucheService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Symfony\Component\Process\Process;

class DaemonControler extends PastellControler
{
    public const NB_JOB_DISPLAYING = 50;

    public function _beforeAction()
    {
        parent::_beforeAction();
        $this->setViewParameter('menu', $this->getInstance(MenuGaucheService::class)->getDaemonMenu());
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_INDEX);
        $this->setViewParameter('dont_display_breacrumbs', true);
    }

    /**
     * @return DaemonManager
     */
    public function getDaemonManager()
    {
        return $this->getInstance(DaemonManager::class);
    }

    /**
     * @return JobQueueSQL
     */
    public function getJobQueueSQL()
    {
        return $this->getInstance(JobQueueSQL::class);
    }

    /**
     * @return JobManager
     */
    public function getJobManager()
    {
        return $this->getInstance(JobManager::class);
    }

    /** @return ConnecteurFrequenceSQL */
    public function getConnecteurFrequenceSQL()
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function indexAction(): void
    {
        $this->indexData();
        $this->setViewParameter('page_url', 'index');
        $this->setViewParameter('twigTemplate', 'daemon/index.html.twig');
        $this->setViewParameter('page_title', 'Gestionnaire de tâches');
        $this->renderDefault();
    }

    public function verrouAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::LECTURE);
        $this->setViewParameter('job_queue_info_list', $this->getJobQueueSQL()->getCountJobByVerrouAndEtat());
        $this->setViewParameter('template_milieu', "DaemonVerrou");
        $this->setViewParameter('page_title', "Gestionnaire de tâches : Files d'attente");
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_VERROU);
        $this->setViewParameter('return_url', "Daemon/verrou");

        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     */
    public function indexContentAction(): void
    {
        $this->indexData();
        $this->setViewParameter('twigTemplate', 'daemon/index_content.html.twig');
        header('Content-type: text/html; charset=utf-8;');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function indexData(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::LECTURE);
        $this->setViewParameter('nb_worker_actif', $this->getWorkerSQL()->getNbActif());
        $this->setViewParameter('job_stat_info', $this->getJobQueueSQL()->getStatInfo());
        $this->setViewParameter('daemon_pid', $this->getDaemonManager()->getDaemonPID());
        $this->setViewParameter('sub_title', 'Liste de tous les travaux');
        $this->setViewParameter('return_url', urlencode('Daemon/index'));
        $this->setViewParameter('job_list', $this->getWorkerSQL()->getJobListWithWorker());
        $this->setViewParameter('daemonManager', $this->getObjectInstancier()->getInstance(DaemonManager::class));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function daemonStartAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        try {
            $this->getDaemonManager()->start();
            $this->getLogger()->info('Daemon start manually');
        } catch (Exception $e) {
            $this->getLogger()->critical('Started daemon ');
            $this->setLastError($e->getMessage());
            $this->redirect("Daemon/index");
        }
        if ($this->getDaemonManager()->status() == DaemonManager::IS_RUNNING) {
            $this->setLastMessage("Le gestionnaire de tâche a été démarré");
            $this->getLogger()->info('Daemon is up');
        } else {
            $this->setLastError(
                "Une erreur s'est produite lors de la tentative de démarrage du gestionnaire de tâches"
            );
            $this->getLogger()->critical('Daemon is down after manually started');
        }
        $this->redirect("Daemon/index");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function daemonStopAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $this->getDaemonManager()->stop();
        if ($this->getDaemonManager()->status() == DaemonManager::IS_STOPPED) {
            $this->setLastMessage("Le gestionnaire de tâches a été arrêté");
        } else {
            $this->setLastError("Une erreur s'est produite lors de la tentative d'arrêt du gestionnaire de tâches");
        }
        $this->redirect("Daemon/index");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function lockAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);

        $id_job = $this->getGetInfo()->getInt('id_job');
        $id_verrou = $this->getGetInfo()->get('id_verrou');
        $etat_source = $this->getGetInfo()->get('etat_source');
        $etat_cible = $this->getGetInfo()->get('etat_cible');
        $return_url = $this->getGetInfo()->get('return_url', 'Daemon/index');

        if ($id_job) {
            $this->getJobQueueSQL()->lock($id_job);
        }

        if ($id_verrou || $etat_source || $etat_cible) {
            $this->getJobQueueSQL()->lockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible);
        }
        $this->redirect("$return_url");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function unlockAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);

        $id_job = $this->getGetInfo()->getInt('id_job');
        $id_verrou = $this->getGetInfo()->get('id_verrou');
        $etat_source = $this->getGetInfo()->get('etat_source');
        $etat_cible = $this->getGetInfo()->get('etat_cible');
        $return_url = $this->getGetInfo()->get('return_url', 'Daemon/index');

        $this->getWorkerSQL()->menageAll();
        if ($id_job) {
            $this->getJobQueueSQL()->unlock($id_job);
        }
        if ($id_verrou || $etat_source || $etat_cible) {
            $this->getJobQueueSQL()->unlockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible);
        }
        $this->redirect($return_url);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function unlockAllAction()
    {
        $this->getWorkerSQL()->menageAll();
        $this->getJobQueueSQL()->unlockAll();
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $this->redirect('Daemon/index');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function killAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $recuperateur = new Recuperateur($_GET);
        $id_worker = $recuperateur->getInt('id_worker');
        $return_url = $recuperateur->get('return_url', 'Daemon/index');

        $info = $this->getWorkerSQL()->getInfo($id_worker);
        if (!$info) {
            $this->setLastError("Ce processus n'existe pas ou plus");
            $this->redirect("$return_url");
        }

        $this->getJobQueueSQL()->lock($info['id_job']);
        $process = new Process(['kill', '-9', $info['pid']]);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->setLastError("Le processus n'a pas été tué : " . $process->getErrorOutput());
            $this->redirect($return_url);
        }
        $this->getWorkerSQL()->error($info['id_worker'], "Processus tué manuellement");

        $this->setLastMessage("Le processus a été tué");
        $this->redirect("$return_url");
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function jobAction(): void
    {
        $recuperateur = $this->getGetInfo();

        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $this->setViewParameter('twigTemplate', 'daemon/job.html.twig');
        $this->setViewParameter('page_title', 'Gestionnaire de tâches');
        $filtre = $recuperateur->get('filtre', '');

        $sub_title = '';
        if ($filtre) {
            $this->setViewParameter('page_url', "job?filtre=$filtre");
            switch ($filtre) {
                case 'actif':
                    $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_JOB_ACTIF);
                    $sub_title = 'Liste des travaux actifs';
                    break;
                case 'lock':
                    $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_JOB_LOCK);
                    $sub_title = 'Liste des travaux suspendus';
                    break;
                case 'wait':
                    $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_JOB_WAIT);
                    $sub_title = 'Liste des travaux en retard';
                    break;
                default:
                    $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_JOB);
            }
        } else {
            $sub_title = 'Liste de tous les travaux';
            $this->setViewParameter('page_url', 'job');
            $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_JOB);
        }

        $this->setViewParameter('sub_title', $sub_title);
        $this->setViewParameter('offset', $recuperateur->getInt('offset', 0));
        $this->setViewParameter('limit', self::NB_JOB_DISPLAYING);
        $this->setViewParameter('filtre', $filtre);

        $this->setViewParameter(
            'return_url',
            "Daemon/job?filtre=$filtre&offset=" . $this->getViewParameterByKey('offset')
        );

        $this->setViewParameter('count', $this->getWorkerSQL()->getNbJob($filtre));
        $this->setViewParameter(
            'job_list',
            $this->getWorkerSQL()->getJobListWithWorker(
                $this->getViewParameterByKey('offset'),
                $this->getViewParameterByKey('limit'),
                $filtre
            )
        );

        $this->renderDefault();
    }

    public function detailAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_job = $this->getGetInfo()->get("id_job");

        $this->setViewParameter('page_title', "Détail du travail #{$id_job}");
        /** @var JobQueueSQL $jobQueueSQL */
        $jobQueueSQL = $this->getViewParameterOrObject('JobQueueSQL');
        $this->setViewParameter('job_info', $jobQueueSQL->getJobInfo($id_job));
        $this->setViewParameter('return_url', "Daemon/detail?id_job=$id_job");
        $this->setViewParameter('template_milieu', "DaemonDetail");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function configAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);

        $this->setViewParameter('page_title', "Configuration de la fréquence des connecteurs");
        $this->setViewParameter('template_milieu', "DaemonConfig");
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_CONFIG);
        $this->setViewParameter('nouveau_bouton_url', ['Ajouter' => "Daemon/editFrequence"]);
        $this->setViewParameter('connecteur_frequence_list', $this->getConnecteurFrequenceSQL()->getAll());
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function editFrequenceAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_cf = $this->getGetInfo()->getInt('id_cf');
        $connecteurFrequence = $this->getConnecteurFrequenceSQL()->getConnecteurFrequence($id_cf) ?: new ConnecteurFrequence();

        $this->setViewParameter('connecteurFrequence', $connecteurFrequence);

        $verbe = $connecteurFrequence->id_cf ? "Modification" : "Ajout";
        $this->setViewParameter('page_title', "$verbe d'une fréquence de connecteur");
        $this->setViewParameter('template_milieu', "DaemmonEditFrequence");
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_CONFIG);
        $this->renderDefault();
    }

    public function listFamilleAjaxAction()
    {
        echo json_encode($this->apiGet("/FamilleConnecteur"));
    }

    public function listConnecteurAjaxAction()
    {
        $connecteur = $this->getGetInfo()->get("famille_connecteur");
        $result = $this->apiGet("/FamilleConnecteur/$connecteur");
        echo json_encode($result);
    }

    public function listInstanceConnecteurAjaxAction()
    {
        $id_connecteur = $this->getGetInfo()->get('id_connecteur');
        $result = $this->apiGet("Connecteur/all/$id_connecteur");
        echo json_encode($result);
    }

    public function listFluxAjaxAction()
    {
        $flux = $this->apiGet("/Flux");
        echo json_encode(array_keys($flux));
    }

    public function listFluxActionAjaxAction()
    {
        $type_document = $this->getGetInfo()->get('type_document');
        $famille_connecteur = $this->getGetInfo()->get('famille_connecteur');

        $result = $this->apiGet("Flux/$type_document/action");

        $result = array_filter($result, function ($e) use ($famille_connecteur) {
            if (empty($e['connecteur-type'])) {
                return false;
            }
            return $e['connecteur-type'] == $famille_connecteur;
        });

        echo json_encode(array_keys($result));
    }

    public function listActionAjaxAction()
    {
        $famille_connecteur = $this->getGetInfo()->get("famille_connecteur");
        $id_connecteur = $this->getGetInfo()->get("id_connecteur");
        $global = $this->getGetInfo()->get("global");
        $result = $this->apiGet("/FamilleConnecteur/$famille_connecteur/$id_connecteur?global=$global");
        if (empty($result['action'])) {
            echo json_encode([]);
            return;
        }
        echo json_encode(array_keys($result['action']));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doEditFrequenceAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $connecteurFrequence = new ConnecteurFrequence($this->getPostInfo()->getAll());
        $id_cf = $this->getConnecteurFrequenceSQL()->edit($connecteurFrequence);
        $this->redirect("Daemon/connecteurFrequenceDetail?id_cf=$id_cf");
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function connecteurFrequenceDetailAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_cf = $this->getGetInfo()->getInt('id_cf');
        $connecteurFrequence = $this->verifConnecteur($id_cf);
        $this->setViewParameter('connecteurFrequence', $connecteurFrequence);
        $this->setViewParameter('page_title', "Détail sur la fréquence d'un connecteur");
        $this->setViewParameter('template_milieu', "DaemonFrequenceDetail");
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_CONFIG);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function verifConnecteur($id_cf)
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $connecteurFrequence = $this->getConnecteurFrequenceSQL()->getConnecteurFrequence($id_cf);

        if (! $connecteurFrequence) {
            $this->setLastError("Impossible de trouver le connecteur $id_cf");
            $this->redirect("Daemon/config");
        }
        return $connecteurFrequence;
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function deleteFrequenceAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_cf = $this->getGetInfo()->get('id_cf');
        $this->getConnecteurFrequenceSQL()->delete($id_cf);
        $this->setLastMessage("La fréquence a été supprimée");
        $this->redirect("Daemon/config");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function deleteJobAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_job = $this->getGetInfo()->get('id_job');
        $id_connecteur = $this->getGetInfo()->get('id_ce');
        $this->getJobQueueSQL()->deleteJob($id_job);
        $this->redirect("Connecteur/edition?id_ce=$id_connecteur");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function deleteJobDocumentAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $id_job = $this->getGetInfo()->get('id_job');
        $id_document = $this->getGetInfo()->get('id_d');
        $id_entite = $this->getGetInfo()->get('id_e');
        $this->getJobQueueSQL()->deleteJob($id_job);
        $this->redirect("Document/detail?id_d=$id_document&id_e=$id_entite");
    }
}
