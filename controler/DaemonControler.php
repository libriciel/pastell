<?php

declare(strict_types=1);

use Pastell\Service\Droit\DroitService;
use Symfony\Component\Process\Process;

class DaemonControler extends PastellControler
{
    public const NB_JOB_DISPLAYING = 50;

    public function _beforeAction()
    {
        parent::_beforeAction();
        $this->setViewParameter('menu_gauche_template', 'DaemonMenuGauche');
        $this->setViewParameter('menu_gauche_select', 'Daemon/index');
        $this->setViewParameter('dont_display_breacrumbs', true);
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

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function verrouAction(): void
    {
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitLecture(DroitService::DROIT_DAEMON)
        );
        $this->setViewParameter('job_queue_info_list', $this->getJobQueueSQL()->getCountJobByVerrouAndEtat());
        $this->setViewParameter('menu_gauche_select', 'Daemon/verrou');
        $this->setViewParameter('template_milieu', 'DaemonVerrou');
        $this->setViewParameter('page_title', "Gestionnaire de tâches : Files d'attente");
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitLecture(DroitService::DROIT_DAEMON)
        );
        $this->setDroitsDaemon(EntiteSQL::ID_E_ENTITE_RACINE);
        $this->setViewParameter('nb_worker_actif', $this->getWorkerSQL()->getNbActif());
        $this->setViewParameter('nb_workers', $this->getDaemonSQL()->getNbWorkers());
        $this->setViewParameter('job_stat_info', $this->getJobQueueSQL()->getStatInfo());
        $this->setViewParameter('daemon_pid', $this->getDaemonManager()->getDaemonPID());
        $this->setViewParameter('sub_title', 'Liste de tous les travaux');
        $this->setViewParameter('return_url', urlencode('Daemon/index'));
        $this->setViewParameter('job_list', $this->getJobQueueSQL()->getAllJobs());
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function globalDaemonStartAction(): void
    {
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
                $this->verifDroit($job->id_e, DroitService::getDroitEdition(DroitService::DROIT_DAEMON));
                $this->getJobQueueSQL()->lock($job->id_job);
                $this->setLastMessage('Le travail a été suspendu');
            }
        } elseif ($id_verrou || $etat_source || $etat_cible) {
            $this->verifDroit(
                EntiteSQL::ID_E_ENTITE_RACINE,
                DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
            );
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
                $this->verifDroit($job->id_e, DroitService::getDroitEdition(DroitService::DROIT_DAEMON));
                $this->getJobQueueSQL()->unlock($job->id_job);
                $this->setLastMessage('Le travail a été réactivé');
            }
        } elseif ($id_verrou || $etat_source || $etat_cible) {
            $this->verifDroit(
                EntiteSQL::ID_E_ENTITE_RACINE,
                DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
            );
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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

        $this->verifDroit($job->id_e, DroitService::getDroitEdition(DroitService::DROIT_DAEMON));
        $this->getJobQueueSQL()->lock($job->id_job);

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
        $this->setViewParameter('menu_gauche_select', 'Daemon/job');

        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
        $this->setViewParameter('twigTemplate', 'daemon/job.html.twig');
        $this->setViewParameter('page_title', 'Gestionnaire de tâches');
        $filtre = $recuperateur->get('filtre', '');
        if ($filtre) {
            $this->setViewParameter('page_url', "job?filtre=$filtre");
            $this->setViewParameter('menu_gauche_select', "Daemon/job?filtre=$filtre");
        } else {
            $this->setViewParameter('page_url', 'job');
        }

        $sub_title_array = [
            'actif' => 'Liste des travaux actifs',
            'lock' => 'Liste des travaux suspendus',
            'wait' => 'Liste des travaux en retard',
        ];

        $this->setViewParameter('sub_title', $sub_title_array[$filtre] ?? 'Liste de tous les travaux');
        $this->setViewParameter('unlock_all_action', 'app.legacy.daemon_unlockAll');

        $this->setViewParameter('offset', $recuperateur->getInt('offset', 0));
        $this->setViewParameter('limit', self::NB_JOB_DISPLAYING);
        $this->setViewParameter('filtre', $filtre);

        $this->setViewParameter(
            'return_url',
            "Daemon/job?filtre=$filtre&offset=" . $this->getViewParameterByKey('offset')
        );

        $this->setViewParameter('count', $this->getJobQueueSQL()->getNbJob($filtre));
        $this->setViewParameter(
            'job_list',
            $this->getJobQueueSQL()->getFilteredJobList(
                $this->getViewParameterByKey('limit'),
                $this->getViewParameterByKey('offset'),
                $filtre
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
        $id_job = $this->getGetInfo()->get('id_job');

        $this->setViewParameter('page_title', "Détail du travail #{$id_job}");
        /** @var JobQueueSQL $jobQueueSQL */
        $jobQueueSQL = $this->getViewParameterOrObject('JobQueueSQL');
        $this->setViewParameter('job_info', $jobQueueSQL->getJobInfo($id_job));
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );

        $this->setViewParameter('page_title', 'Configuration de la fréquence des connecteurs');
        $this->setViewParameter('template_milieu', 'DaemonFrequenceConfiguration');
        $this->setViewParameter('menu_gauche_select', 'Daemon/frequenceConfiguration');
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
        $id_cf = $this->getGetInfo()->getInt('id_cf');
        $connecteurFrequence = $this->getConnecteurFrequenceSQL()->getConnecteurFrequence(
            $id_cf
        ) ?: new ConnecteurFrequence();

        $this->setViewParameter('connecteurFrequence', $connecteurFrequence);

        $verbe = $connecteurFrequence->id_cf ? 'Modification' : 'Ajout';
        $this->setViewParameter('page_title', "$verbe d'une fréquence de connecteur");
        $this->setViewParameter('template_milieu', 'DaemonEditFrequence');
        $this->setViewParameter('menu_gauche_select', 'Daemon/frequenceConfiguration');
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
        $flux = $this->apiGet('/Flux');
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
    public function doEditFrequenceAction(): void
    {
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
        $id_cf = $this->getGetInfo()->getInt('id_cf');
        $connecteurFrequence = $this->verifConnecteur($id_cf);
        $this->setViewParameter('connecteurFrequence', $connecteurFrequence);
        $this->setViewParameter('page_title', "Détail sur la fréquence d'un connecteur");
        $this->setViewParameter('template_milieu', 'DaemonFrequenceDetail');
        $this->setViewParameter('menu_gauche_select', 'Daemon/frequenceConfiguration');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    private function verifConnecteur($id_cf): ConnecteurFrequence
    {
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
                $this->verifDroit($job->id_e, DroitService::getDroitEdition(DroitService::DROIT_DAEMON));
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
                $this->verifDroit($job->id_e, DroitService::getDroitEdition(DroitService::DROIT_DAEMON));
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
        $this->setViewParameter('menu_gauche_select', 'Daemon/configuration');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function editConfigurationAction(): void
    {
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
        $this->setViewParameter('nb_workers', $this->getDaemonSQL()->getNbWorkers());
        $this->setViewParameter('page_title', 'Configuration des gestionnaires de tâches');
        $this->setViewParameter('template_milieu', 'DaemonEditConfiguration');
        $this->setViewParameter('menu_gauche_select', 'Daemon/configuration');
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doEditConfigurationAction(): void
    {
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
        $nb_workers = (int)$this->getPostInfo()->get('nb_workers');
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
        $this->setViewParameter('menu_gauche_select', 'Daemon/editConfiguration');
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
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
            $this->verifDroit(
                $daemon->id_e ?? EntiteSQL::ID_E_ENTITE_RACINE,
                DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
            );
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
            $this->verifDroit(
                $daemon->id_e ?? EntiteSQL::ID_E_ENTITE_RACINE,
                DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
            );
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
     */
    public function createAction(): void
    {
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
        $tree = $this->getRoleUtilisateur()->getEntityTree($this->getId_u(), 'entite:edition');

        $this->replaceArrayKeyRecursive($tree, 'denomination', 'name');
        $this->replaceArrayKeyRecursive($tree, 'id_e', 'value');
        array_unshift($tree, [
            'name' => 'Entité Racine',
            'value' => '0',
        ]);
        $this->setViewParameter(
            'tree',
            \json_encode($tree, \JSON_THROW_ON_ERROR)
        );
        $this->setViewParameter('nb_free_workers', $this->getDaemonSQL()->getNbSharedWorkers() - 1);
        $this->setViewParameter('template_milieu', 'DaemonCreate');
        $this->setViewParameter('page_title', 'Création d\'un gestionnaire de tâches');
        $this->renderDefault();
    }

    private function replaceArrayKeyRecursive(array &$array, string $oldName, string $newName): void
    {
        foreach ($array as &$element) {
            if (\is_array($element)) {
                $this->replaceArrayKeyRecursive($element, $oldName, $newName);
            }
            if (isset($element[$oldName])) {
                $element[$newName] = $element[$oldName];
                unset($element[$oldName]);
            }
            if (isset($element['children']) && \is_array($element['children'])) {
                $this->replaceArrayKeyRecursive($element['children'], $oldName, $newName);
            }
        }
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws UnrecoverableException
     */
    public function doCreateAction(): void
    {
        $this->verifDroit(
            EntiteSQL::ID_E_ENTITE_RACINE,
            DroitService::getDroitEdition(DroitService::DROIT_DAEMON)
        );
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
        $this->getDaemonManager()->addDaemon($id_e, $nb_allocated_workers);
        $this->setLastMessage('Le gestionnaire de tâches a été créé avec succès');
        $this->redirect("/Daemon/configuration?id_e=$id_e");
    }
}
