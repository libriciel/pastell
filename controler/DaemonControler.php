<?php

declare(strict_types=1);

use Pastell\Configuration\JobStatus;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Entite\EntityUtilitiesService;
use Pastell\Service\Menu\MenuGaucheService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Module\ModuleListService;
use Symfony\Component\Process\Process;

class DaemonControler extends PastellControler
{
    public const NB_JOB_DISPLAYING = 50;

    public function _beforeAction()
    {
        parent::_beforeAction();
        $this->setDaemonMenuGauche();
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_INDEX);
        $this->setViewParameter('dont_display_breacrumbs', true);
        $this->setDroitsDaemon(EntiteSQL::ID_E_ENTITE_RACINE);
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
        $this->setJobSearchViewParameters(
            $this->getJobAdvancedFilters($this->getGetInfo()),
            'app.legacy.daemon_index',
            $this->getJobQueueSQL()->getDistinctVerrou()
        );
        $this->setViewParameter('page_url', 'index');
        $this->setViewParameter('twigTemplate', 'daemon/index.html.twig');
        $this->setViewParameter('page_title', 'Gestionnaire de tâches');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function verrouAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::LECTURE);
        $this->setViewParameter('job_queue_info_list', $this->getJobQueueSQL()->getCountJobByVerrouAndEtat());
        $this->setViewParameter('template_milieu', 'DaemonVerrou');
        $this->setViewParameter('page_title', "Gestionnaire de tâches : Files d'attente");
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_VERROU);
        $this->setViewParameter('return_url', 'Daemon/verrou');

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
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::LECTURE);
        $this->setDroitsDaemon(EntiteSQL::ID_E_ENTITE_RACINE);
        $this->setViewParameter('nb_worker_actif', $this->getWorkerSQL()->getNbActif());
        $this->setViewParameter('nb_workers', $this->getDaemonSQL()->getNbWorkers());
        $this->setViewParameter('job_stat_info', $this->getJobQueueSQL()->getStatInfo());
        $this->setViewParameter('daemon_pid', $this->getDaemonManager()->getDaemonPID());
        $this->setViewParameter('sub_title', 'Liste de tous les travaux');
        $this->setViewParameter('return_url', urlencode('Daemon/index'));
        $this->setViewParameter('filtre', '');

        $advancedFilters = $this->getJobAdvancedFilters($this->getGetInfo());
        $offset = $this->getGetInfo()->getInt('offset', 0);
        $this->setViewParameter('search', $advancedFilters);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('limit', self::NB_JOB_DISPLAYING);
        $this->setViewParameter('count', $this->getJobQueueSQL()->getNbJob('', null, $advancedFilters));
        $this->setViewParameter(
            'job_list',
            $this->getJobQueueSQL()->getFilteredJobList(
                self::NB_JOB_DISPLAYING,
                $offset,
                '',
                null,
                $advancedFilters
            )
        );
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function globalDaemonStartAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        try {
            $this->getDaemonManager()->start();
            $this->getLogger()->info('Daemon start manually');
        } catch (Exception $e) {
            $this->getLogger()->critical('Started daemon ');
            $this->setLastError($e->getMessage());
            $this->redirect('Daemon/index');
        }
        if ($this->getDaemonManager()->status() === DaemonManager::IS_RUNNING) {
            $this->setLastMessage('Les gestionnaires de tâches ont été démarrés');
            $this->getLogger()->info('Daemon is up');
        } else {
            $this->setLastError(
                "Une erreur s'est produite lors de la tentative de démarrage des gestionnaires de tâches"
            );
            $this->getLogger()->critical('Daemon is down after manually started');
        }
        $this->redirect('Daemon/index');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function globalDaemonStopAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $this->getDaemonManager()->stop();
        if ($this->getDaemonManager()->status() === DaemonManager::IS_STOPPED) {
            $this->setLastMessage('Les gestionnaires de tâches ont été arrêtés');
        } else {
            $this->setLastError("Une erreur s'est produite lors de la tentative d'arrêt des gestionnaires de tâches");
        }
        $this->redirect('Daemon/index');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function lockAction(): void
    {
        $id_job = $this->getGetInfo()->getInt('id_job');
        $id_verrou = $this->getGetInfo()->get('id_verrou');
        $etat_source = $this->getGetInfo()->get('etat_source');
        $etat_cible = $this->getGetInfo()->get('etat_cible');
        $return_url = $this->getGetInfo()->get('return_url', 'Daemon/index');

        if ($id_job) {
            $job = $this->getJobQueueSQL()->getJob($id_job);
            if ($job === null) {
                $this->setLastError('Impossible de trouver le travail à suspendre');
            } else {
                $this->checkDroitFor($job->id_e, DroitService::DROIT_DAEMON, DroitType::EDITION);
                $this->getJobQueueSQL()->lock($job->id_job, JobStatus::SUSPENDED_BY_USER);
                $this->setLastMessage('Le travail a été suspendu');
            }
        } elseif ($id_verrou || $etat_source || $etat_cible) {
            $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
            $this->getJobQueueSQL()->lockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible);
            $this->setLastMessage('Les travaux correspondants ont été suspendus');
        } else {
            $this->setLastError('Aucun travail à suspendre');
        }

        $this->redirect($return_url);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function unlockAction(): void
    {
        $id_job = $this->getGetInfo()->getInt('id_job');
        $id_verrou = $this->getGetInfo()->get('id_verrou');
        $etat_source = $this->getGetInfo()->get('etat_source');
        $etat_cible = $this->getGetInfo()->get('etat_cible');
        $return_url = $this->getGetInfo()->get('return_url', 'Daemon/index');

        $this->getWorkerSQL()->menageAll();

        if ($id_job) {
            $job = $this->getJobQueueSQL()->getJob($id_job);
            if ($job === null) {
                $this->setLastError('Impossible de trouver le travail à réactiver');
            } else {
                $this->checkDroitFor($job->id_e, DroitService::DROIT_DAEMON, DroitType::EDITION);
                $this->getJobQueueSQL()->unlock($job->id_job);
                $this->setLastMessage('Le travail a été réactivé');
            }
        } elseif ($id_verrou || $etat_source || $etat_cible) {
            $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
            $this->getJobQueueSQL()->unlockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible);
            $this->setLastMessage('Les travaux correspondants ont été réactivés');
        } else {
            $this->setLastError('Aucun travail à réactiver');
        }

        $this->redirect($return_url);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function unlockAllAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $this->getWorkerSQL()->menageAll();
        $this->getJobQueueSQL()->unlockAll();
        $this->redirect('Daemon/index');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function killAction(): void
    {
        $id_worker = $this->getGetInfo()->getInt('id_worker');
        $return_url = $this->getGetInfo()->get('return_url', 'Daemon/index');
        $worker = $this->getWorkerSQL()->getWorker($id_worker);

        if ($worker === null) {
            $this->setLastError("Ce processus n'existe pas ou plus");
            $this->redirect($return_url);
        }
        $id_job = $worker->id_job ?? null;
        $job = $this->getJobQueueSQL()->getJob($id_job);
        if (!$job) {
            $this->setLastError('Impossible de trouver le travail associé à ce processus');
            $this->redirect($return_url);
        }

        $this->checkDroitFor($job->id_e, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $this->getJobQueueSQL()->lock($job->id_job, JobStatus::KILLED_BY_USER);

        $process = new Process(['kill', '-9', $worker->pid]);
        $process->run();

        if ($process->isSuccessful()) {
            $this->getWorkerSQL()->error($id_worker, 'Processus tué manuellement');
            $this->setLastMessage('Le processus a été tué');
        } else {
            $this->setLastError("Le processus n'a pas été tué : " . $process->getErrorOutput());
        }
        $this->redirect($return_url);
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

        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::LECTURE);
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
        $this->setViewParameter('unlock_all_action', 'app.legacy.daemon_unlockAll');
        $this->setViewParameter('offset', $recuperateur->getInt('offset', 0));
        $this->setViewParameter('limit', self::NB_JOB_DISPLAYING);
        $this->setViewParameter('filtre', $filtre);

        $advancedFilters = $this->getJobAdvancedFilters($recuperateur);
        $this->setJobSearchViewParameters(
            $advancedFilters,
            'app.legacy.daemon_job',
            $this->getJobQueueSQL()->getDistinctVerrou()
        );

        $this->setViewParameter(
            'return_url',
            "Daemon/job?filtre=$filtre&offset=" . $this->getViewParameterByKey('offset')
        );

        $this->setViewParameter('count', $this->getJobQueueSQL()->getNbJob($filtre, null, $advancedFilters));
        $this->setViewParameter(
            'job_list',
            $this->getJobQueueSQL()->getFilteredJobList(
                $this->getViewParameterByKey('limit'),
                $this->getViewParameterByKey('offset'),
                $filtre,
                null,
                $advancedFilters
            )
        );

        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function detailAction(): void
    {
        $id_job = $this->getGetInfo()->get('id_job');
        $job = $this->getJobQueueSQL()->getJob($id_job);

        if ($job === null) {
            $this->setLastError('Impossible de trouver le travail demandé');
            $this->redirect('Daemon/index');
        }

        $this->checkDroitFor($job->id_e, DroitService::DROIT_DAEMON, DroitType::LECTURE);

        $this->setViewParameter('page_title', "Détail du travail #{$id_job}");
        /** @var JobQueueSQL $jobQueueSQL */
        $jobQueueSQL = $this->getInstance(JobQueueSQL::class);
        $this->setViewParameter('job', $jobQueueSQL->getJob($id_job));
        $this->setViewParameter('return_url', "Daemon/detail?id_job=$id_job");
        $this->setViewParameter('template_milieu', 'DaemonDetail');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function frequenceConfigurationAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);

        $this->setViewParameter('page_title', 'Configuration de la fréquence des connecteurs');
        $this->setViewParameter('template_milieu', 'DaemonFrequenceConfiguration');
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_FREQUENCE_CONFIGURATION);
        $this->setViewParameter('nouveau_bouton_url', ['Ajouter' => 'Daemon/editFrequence']);
        $this->setViewParameter('connecteur_frequence_list', $this->getConnecteurFrequenceSQL()->getAll());
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function editFrequenceAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $id_cf = $this->getGetInfo()->getInt('id_cf');
        $connecteurFrequence = $this->getConnecteurFrequenceSQL()->getConnecteurFrequence(
            $id_cf
        ) ?: new ConnecteurFrequence();

        $this->setViewParameter('connecteurFrequence', $connecteurFrequence);

        $verbe = $connecteurFrequence->id_cf ? 'Modification' : 'Ajout';
        $this->setViewParameter('page_title', "$verbe d'une fréquence de connecteur");
        $this->setViewParameter('template_milieu', 'DaemonEditFrequence');
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_FREQUENCE_CONFIGURATION);
        $this->renderDefault();
    }

    public function listFamilleAjaxAction()
    {
        echo json_encode($this->apiGet('/FamilleConnecteur'));
    }

    public function listConnecteurAjaxAction()
    {
        $connecteur = $this->getGetInfo()->get('famille_connecteur');
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
        $flux = $this->getInstance(ModuleListService::class)->getModuleListOrderByNom($this->getId_u());
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
        $famille_connecteur = $this->getGetInfo()->get('famille_connecteur');
        $id_connecteur = $this->getGetInfo()->get('id_connecteur');
        $global = $this->getGetInfo()->get('global');
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
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $connecteurFrequence = new ConnecteurFrequence($this->getPostInfo()->getAll());
        $id_cf = $this->getConnecteurFrequenceSQL()->edit($connecteurFrequence);
        $this->redirect("Daemon/connecteurFrequenceDetail?id_cf=$id_cf");
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function connecteurFrequenceDetailAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $id_cf = $this->getGetInfo()->getInt('id_cf');
        $connecteurFrequence = $this->verifConnecteur($id_cf);
        $this->setViewParameter('connecteurFrequence', $connecteurFrequence);
        $this->setViewParameter('page_title', "Détail sur la fréquence d'un connecteur");
        $this->setViewParameter('template_milieu', 'DaemonFrequenceDetail');
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_FREQUENCE_CONFIGURATION);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function verifConnecteur($id_cf): ConnecteurFrequence
    {
        $connecteurFrequence = $this->getConnecteurFrequenceSQL()->getConnecteurFrequence($id_cf);
        if (!$connecteurFrequence) {
            $this->setLastError("Impossible de trouver le connecteur $id_cf");
            $this->redirect('Daemon/frequenceConfiguration');
        }
        return $connecteurFrequence;
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function deleteFrequenceAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $id_cf = $this->getGetInfo()->get('id_cf');
        $this->getConnecteurFrequenceSQL()->delete($id_cf);
        $this->setLastMessage('La fréquence a été supprimée');
        $this->redirect('Daemon/frequenceConfiguration');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function deleteJobAction(): void
    {
        $id_job = $this->getGetInfo()->get('id_job');
        $id_connecteur = $this->getGetInfo()->get('id_ce', 'Connecteur/index');
        if ($id_job) {
            $job = $this->getJobQueueSQL()->getJob($id_job);
            if ($job === null) {
                $this->setLastError('Impossible de trouver le travail à supprimer');
            } else {
                $this->checkDroitFor($job->id_e, DroitService::DROIT_DAEMON, DroitType::EDITION);
                $this->getJobQueueSQL()->deleteJob($job->id_job);
                $this->setLastMessage('Le travail a été supprimé');
            }
        } else {
            $this->setLastError('Identifiant de travail manquant');
        }
        $this->redirect("Connecteur/edition?id_ce=$id_connecteur");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function deleteJobDocumentAction(): void
    {
        $id_job = $this->getGetInfo()->get('id_job');
        $id_document = $this->getGetInfo()->get('id_d');
        $id_e = $this->getGetInfo()->get('id_e');
        if ($id_job) {
            $job = $this->getJobQueueSQL()->getJob($id_job);
            if ($job === null) {
                $this->setLastError('Impossible de trouver le travail à supprimer');
            } else {
                $this->checkDroitFor($job->id_e, DroitService::DROIT_DAEMON, DroitType::EDITION);
                $this->getJobQueueSQL()->deleteJob($job->id_job);
                $this->setLastMessage('Le travail a été supprimé');
            }
        } else {
            $this->setLastError('Identifiant de travail manquant');
        }
        $this->redirect("Document/detail?id_d=$id_document&id_e=$id_e");
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function configurationAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $recuperateur = $this->getGetInfo();
        $offset = $recuperateur->getInt('offset', 0);
        $search = $recuperateur->get('search', '');
        $this->setViewParameter('search', $search);
        $this->setViewParameter('offset', $offset);
        $entity_list = $this->getDaemonSQL()->getAllEntiteInfo($offset, $search);
        $this->setViewParameter('entity_list', $entity_list);

        $nb_workers = $this->getDaemonSQL()->getNbWorkers();
        $nb_allocated_workers = $this->getDaemonSQL()->getNbAllocatedWorkers();
        $nb_shared_workers = $nb_workers - $nb_allocated_workers;

        $this->setViewParameter('nb_workers', $nb_workers);
        $this->setViewParameter('nb_allocated_workers', $nb_allocated_workers);
        $this->setViewParameter('nb_shared_workers', $nb_shared_workers);
        $this->setViewParameter('global_daemon_status', $this->getDaemonManager()->status());
        $this->setViewParameter('page_title', 'Configuration des gestionnaires de tâches');
        $this->setViewParameter('template_milieu', 'DaemonConfiguration');
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_CONFIGURATION);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function editConfigurationAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $this->setViewParameter('nb_workers', $this->getDaemonSQL()->getNbWorkers());
        $this->setViewParameter('page_title', 'Configuration des gestionnaires de tâches');
        $this->setViewParameter('template_milieu', 'DaemonEditConfiguration');
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_CONFIGURATION);
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doEditConfigurationAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $nb_workers = (int)$this->getPostInfo()->get('nb_workers');
        if ($nb_workers < 1) {
            $this->setLastError('Le nombre de processus doit être supérieur ou égal à 1');
            $this->redirect('Daemon/editConfiguration');
        }
        $allocatedWorkers = $this->getDaemonSQL()->getNbAllocatedWorkers();
        if ($allocatedWorkers > $nb_workers) {
            $this->setLastError(
                "Impossible de réduire le nombre de processus à $nb_workers 
            car $allocatedWorkers processus sont déjà alloués à des gestionnaires de tâches actifs.
            <br>Veuillez libérer des processus avant de réduire la configuration."
            );
        } else {
            $this->getDaemonSQL()->setNbWorkers($nb_workers);
            $this->getDaemonSQL()->refreshAvailableWorkers();
            $this->setLastMessage('La configuration des processus à été mise à jour');
        }
        $this->redirect('Daemon/configuration');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function allocateAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $recuperateur = $this->getPostInfo();
        $allocatedWorkers = $recuperateur->get('data', []);
        $nb_workers_to_allocate = array_sum($allocatedWorkers);
        $nb_workers = $this->getDaemonSQL()->getNbWorkers();
        if ($nb_workers_to_allocate >= $nb_workers) {
            $this->setLastError('Au moins un processus partagé doit rester disponible.');
        } else {
            foreach ($allocatedWorkers as $id_daemon => $nb_workers_daemon) {
                if ($id_daemon === DaemonSQL::GLOBAL_DAEMON) {
                    continue;
                }
                if ((int)$nb_workers_daemon < 1) {
                    $this->setLastError('Chaque gestionnaire de tâches doit avoir au moins 1 processus alloué');
                    $this->redirect('Daemon/configuration');
                }
                $this->getDaemonManager()->allocateWorkers($id_daemon, (int)$nb_workers_daemon);
            }
            $this->setLastMessage('Processus alloués avec succès');
        }
        $this->redirect('Daemon/configuration');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function deleteDaemonAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $id_daemon = $this->getGetInfo()->getInt('id_daemon');
        if ($id_daemon === DaemonSQL::GLOBAL_DAEMON) {
            $this->setLastError('Impossible de supprimer le gestionnaire de tâches global');
            $this->redirect('Daemon/configuration');
        }
        $daemon = $this->getDaemonSQL()->getDaemon($id_daemon);
        if ($daemon === null) {
            $this->setLastError('Impossible de trouver le gestionnaire de tâches');
            $this->redirect('Daemon/configuration');
        }
        $this->setViewParameter('page_title', 'Suppression du gestionnaire de tâches');
        $this->setViewParameter('template_milieu', 'DaemonDelete');
        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_CONFIGURATION);
        $this->setViewParameter('daemon', $daemon);
        $this->setViewParameter('entite', $this->getEntiteSQL()->getInfo($daemon->id_e));
        $this->renderDefault();
    }

    /**
     * @throws UnrecoverableException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doDeleteDaemonAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $recuperateur = $this->getPostInfo();
        $id_daemon = $recuperateur->getInt('id_d');
        $daemon = $this->getDaemonSQL()->getDaemon($id_daemon);
        if ($daemon === null) {
            $this->setLastError('Impossible de trouver le gestionnaire de tâches');
            $this->redirect('Daemon/configuration');
        }
        $this->getDaemonManager()->removeDaemon($daemon->id_daemon);
        $this->setLastMessage('Gestionnaire de tâches supprimé avec succès');
        $this->redirect('Daemon/configuration');
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     */
    public function daemonStartAction(): void
    {
        $id_daemon = $this->getGetInfo()->getInt('id_daemon');
        $return_url = $this->getGetInfo()->get('return_url', 'Daemon/configuration');
        $daemon = $this->getDaemonSQL()->getDaemon($id_daemon);
        if ($daemon === null) {
            $this->setLastError('Impossible de trouver le gestionnaire de tâches');
        } else {
            $this->checkDroitFor($daemon->id_e ?? EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
            if ($this->getDaemonManager()->status() === DaemonManager::IS_STOPPED) {
                $this->setLastError('Le gestionnaire de tâches global est arrêté');
            } else {
                $this->getDaemonManager()->startDaemon($id_daemon);
                $this->setLastMessage('Le gestionnaire de tâches a été lancé sur l\'entité');
            }
        }
        $this->redirect($return_url);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     */
    public function daemonStopAction(): void
    {
        $id_daemon = $this->getGetInfo()->getInt('id_daemon');
        $return_url = $this->getGetInfo()->get('return_url', 'Daemon/configuration');
        $daemon = $this->getDaemonSQL()->getDaemon($id_daemon);
        if ($daemon === null) {
            $this->setLastError('Impossible de trouver le gestionnaire de tâches');
        } else {
            $this->checkDroitFor($daemon->id_e ?? EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
            if ($this->getDaemonManager()->status() === DaemonManager::IS_STOPPED) {
                $this->setLastError('Le gestionnaire de tâches global est arrêté');
            } else {
                $this->getDaemonManager()->stopDaemon($id_daemon);
                $this->setLastMessage('Le gestionnaire de tâches a été arrêté sur l\'entité');
            }
        }
        $this->redirect($return_url);
    }


    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     * @throws JsonException
     */
    public function createAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $entityUtilitiesService = $this->getInstance(EntityUtilitiesService::class);
        $arbreFille = $this->getRoleUtilisateur()->getArbreFille($this->getId_u(), DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION));
        $entity_tree = $entityUtilitiesService->buildEntityTreeselectOptions($arbreFille);
        $this->setViewParameter('entity_treeselect_data', \json_encode($entity_tree, \JSON_THROW_ON_ERROR));

        $this->setMenuGaucheSelect(MenuGaucheService::DAEMON_CONFIGURATION);
        $this->setViewParameter('nb_free_workers', $this->getDaemonSQL()->getNbSharedWorkers() - 1);
        $this->setViewParameter('template_milieu', 'DaemonCreate');
        $this->setViewParameter('page_title', 'Création d\'un gestionnaire de tâches');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     */
    public function doCreateAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);

        $recuperateur = $this->getPostInfo();
        $id_e = $recuperateur->getInt('id_e');
        $nb_allocated_workers = min(
            $recuperateur->getInt('nb_allocated_workers'),
            $this->getDaemonSQL()->getNbSharedWorkers() - 1
        );
        if ($id_e === EntiteSQL::ID_E_ENTITE_RACINE || $this->getDaemonSQL()->getDaemonByEntity($id_e) !== null) {
            $this->setLastError('Un gestionnaire de tâches existe déjà pour cette entité');
            $this->redirect('Daemon/configuration');
        }
        $daemon_admin_email = $recuperateur->get('daemon_admin_email');
        if ($daemon_admin_email === '') {
            $this->setLastError('L\'email de l\'administrateur du gestionnaire de tâches est requis');
            $this->redirect('Daemon/create');
        }
        try {
            $this->getDaemonManager()->checkRFC2822Email($daemon_admin_email);
        } catch (UnrecoverableException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect('Daemon/create');
        }

        if ($nb_allocated_workers < 1) {
            $this->setLastError('Le nombre de processus à allouer doit être supérieur ou égal à 1');
            $this->redirect('Daemon/create');
        }

        $late_jobs_threshold = $recuperateur->getInt('late_jobs_threshold', 1);
        if ($late_jobs_threshold < 1) {
            $this->setLastError('Le seuil de travaux doit etre supérieur ou égal à 1');
            $this->redirect('Daemon/create');
        }

        $this->getDaemonManager()->addDaemon($id_e, $nb_allocated_workers, $daemon_admin_email, $late_jobs_threshold);
        $this->setLastMessage('Le gestionnaire de tâches a été créé avec succès');
        $this->redirect("Daemon/configuration?id_e=$id_e");
    }
}
