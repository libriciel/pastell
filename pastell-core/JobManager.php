<?php

class JobManager
{
    public const DEFAULT_NEXT_TRY_IN_MINUTES = 1;
    public const DEFAULT_ID_VERROU = "DEFAULT_VERROU_ID";

    private $jobQueueSQL;
    private $document;
    private $documentActionEntite;
    private $documentTypeFactory;
    private $fluxEntiteSQL;
    private $connecteurEntiteSQL;
    private $connecteurFrequenceSQL;

    private $disable_job_queue;

    private $logger;

    public function __construct(
        JobQueueSQL $jobQueueSQL,
        DocumentSQL $document,
        DocumentActionEntite $documentActionEntite,
        DocumentTypeFactory $documentTypeFactory,
        FluxEntiteSQL $fluxEntiteSQL,
        ConnecteurEntiteSQL $connecteurEntiteSQL,
        ConnecteurFrequenceSQL $connecteurFrequenceSQL,
        Monolog\Logger $logger,
        $disable_job_queue = false
    ) {
        $this->jobQueueSQL = $jobQueueSQL;
        $this->document = $document;
        $this->documentActionEntite = $documentActionEntite;
        $this->documentTypeFactory = $documentTypeFactory;
        $this->fluxEntiteSQL = $fluxEntiteSQL;
        $this->connecteurEntiteSQL = $connecteurEntiteSQL;
        $this->connecteurFrequenceSQL = $connecteurFrequenceSQL;
        $this->disable_job_queue = $disable_job_queue;
        $this->logger = $logger;
    }

    public function setDisableJobQueue($disable_job_queue)
    {
        $this->disable_job_queue = $disable_job_queue;
    }


    public function setJobForDocument($id_e, $id_d, $last_message, string $action_name = null)
    {
        if ($this->disable_job_queue) {
            return true;
        }

        if (isset($action_name)) {
            $id_job = $this->jobQueueSQL->getJobIdForDocumentAndAction($id_e, $id_d, $action_name);
        } else {
            $id_job = $this->jobQueueSQL->getJobIdForDocument($id_e, $id_d);
        }

        $infoDocument = $this->document->getInfo($id_d);
        $etat_source = $this->documentActionEntite->getLastAction($id_e, $id_d);
        $etat_cible = $this->documentTypeFactory->getFluxDocumentType($infoDocument['type'])->getAction()->getActionAutomatique($etat_source);
        $job = $this->jobQueueSQL->getJob($id_job);

        if (!$etat_cible) {
            if ($job) {
                $this->jobQueueSQL->deleteJob($id_job);
            }
            return false;
        }

        if ($job && $job->etat_cible != $etat_cible) {
            $this->jobQueueSQL->deleteJob($id_job);
            $id_job = false;
        }

        if (!$id_job) {
            return $this->createJobForDocument($id_e, $id_d, 0, $last_message, $etat_cible);
        }

        $this->updateJob($id_job, $last_message);
        return $id_job;
    }

    public function deleteJobForDocument(int $id_e, string $id_d, string $action_name = null): void
    {
        if (isset($action_name)) {
            $id_job = $this->jobQueueSQL->getJobIdForDocumentAndAction($id_e, $id_d, $action_name);
        } else {
            $id_job = $this->jobQueueSQL->getJobIdForDocument($id_e, $id_d);
        }
        if ($id_job) {
            $this->jobQueueSQL->deleteJob($id_job);
        }
    }

    public function setTraitementLot($id_e, $id_d, $id_u, $action, string $verrou = '')
    {
        if ($this->disable_job_queue) {
            return true;
        }
        return $this->createJobForDocument($id_e, $id_d, $id_u, "Action programmée sur le document", $action, $verrou);
    }

    public function setJobForConnecteur($id_ce, $action_name, $last_message)
    {
        if ($this->disable_job_queue) {
            return true;
        }

        $info_connecteur = $this->connecteurEntiteSQL->getInfo($id_ce);

        $documentType = ($info_connecteur['global']) ?
            $this->documentTypeFactory->getGlobalDocumentType($info_connecteur['id_connecteur'])
            : $this->documentTypeFactory->getEntiteDocumentType($info_connecteur['id_connecteur']);

        $all_action = $documentType->getAction()->getAutoAction();
        if (empty($all_action[$action_name])) {
            return false;
        }

        $id_job = $this->jobQueueSQL->getJobIdForConnecteur($id_ce, $action_name);

        if (!$id_job) {
            return $this->createJobForConnecteur($id_ce, $action_name);
        }

        $this->updateJob($id_job, $last_message);
        return $id_job;
    }

    private function createJobForDocument($id_e, $id_d, $id_u = 0, $last_message = '', $action = '', string $verrou = '')
    {

        $job = new Job();
        $job->type = Job::TYPE_DOCUMENT;
        $job->id_e = $id_e;
        $job->id_d = $id_d;
        $job->id_u = $id_u;
        $job->last_message = $last_message;
        $job->etat_source = $this->documentActionEntite->getLastAction($id_e, $id_d);
        $job->etat_cible = $action;
        $now = date('Y-m-d H:i:s');
        $job->next_try = $now;
        $connecteurFrequence = $this->getConnecteurFrequence($job);
        $job->id_verrou = $verrou ?: $connecteurFrequence->id_verrou;
        $job->id_daemon = 1;
        $this->deleteDocument($id_e, $id_d);
        return $this->jobQueueSQL->createJob($job);
    }

    private function createJobForConnecteur($id_ce, $action_name)
    {
        $info = $this->connecteurEntiteSQL->getInfo($id_ce);
        $job = new Job();
        $job->type = Job::TYPE_CONNECTEUR;
        $job->id_e = $info['id_e'];
        $job->id_ce = $info['id_ce'];
        $job->etat_source = $action_name;
        $job->etat_cible = $action_name;
        $now = date('Y-m-d H:i:s');
        $job->next_try = $now;
        $connecteurFrequence = $this->getConnecteurFrequence($job);
        $job->id_verrou = $connecteurFrequence->id_verrou;
        $job->id_daemon = 1;
        return $this->jobQueueSQL->createJob($job);
    }

    private function updateJob($id_job, $last_message)
    {
        $job = $this->jobQueueSQL->getJob($id_job);
        $job->last_message = $last_message;
        $job->last_try = date('Y-m-d H:i:s');
        if ($job->nb_try == 0) {
            $job->first_try = date('Y-m-d H:i:s');
        }
        $connecteurFrequence = $this->getConnecteurFrequence($job);
        try {
            $job->next_try = $connecteurFrequence->getNextTry($job->nb_try);
        } catch (Exception $e) {
            $this->jobQueueSQL->lock($id_job);
            return;
        }
        $job->id_verrou = $connecteurFrequence->id_verrou;
        $job->nb_try++;
        $this->jobQueueSQL->updateJob($job);
    }

    public function getNearestConnecteurFrequence($id_ce)
    {
        $connecteurFrequence = $this->getConnecteurFrequenceByIdCe($id_ce);
        return $this->getNearestConnecteurFromConnecteur($connecteurFrequence);
    }

    private function getConnecteurFrequenceByIdCe($id_ce)
    {
        $connecteur_info = $this->connecteurEntiteSQL->getInfo($id_ce);
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ($connecteur_info['global']) ?
            ConnecteurFrequence::TYPE_GLOBAL : ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = $connecteur_info['type'];
        $connecteurFrequence->id_connecteur = $connecteur_info['id_connecteur'];
        $connecteurFrequence->id_ce = $connecteur_info['id_ce'];
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_CONNECTEUR;
        return $connecteurFrequence;
    }

    /**
     * @param $id_ce
     * @return ConnecteurFrequence[]
     */
    public function getNearestConnecteurForDocument($id_ce)
    {
        $connecteur_info = $this->connecteurEntiteSQL->getInfo($id_ce);
        $all_flux = $this->fluxEntiteSQL->getFluxByConnecteur($connecteur_info['id_ce']);
        $connecteurFrequence = $this->getConnecteurFrequenceByIdCe($id_ce);

        $connecteurFrequenceByFlux = [];

        if ($connecteurFrequence->type_connecteur === ConnecteurFrequence::TYPE_ENTITE) {
            foreach ($all_flux as $flux) {
                $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
                $connecteurFrequence->type_document = $flux;
                $connecteurFrequenceByFlux[$flux] =
                    $this->connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($connecteurFrequence);
                if (!$connecteurFrequenceByFlux[$flux]) {
                    $connecteurFrequence->id_verrou = self::DEFAULT_ID_VERROU;
                    $connecteurFrequence->expression = self::DEFAULT_NEXT_TRY_IN_MINUTES;
                    $connecteurFrequenceByFlux[$flux] = $connecteurFrequence;
                }
            }
        }
        return $connecteurFrequenceByFlux;
    }

    private function getNearestConnecteurFromConnecteur(ConnecteurFrequence $connecteurFrequence)
    {
        $connecteurResult = $this->connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($connecteurFrequence);
        if (!$connecteurResult) {
            $connecteurFrequence->id_verrou = self::DEFAULT_ID_VERROU;
            $connecteurFrequence->expression = self::DEFAULT_NEXT_TRY_IN_MINUTES;
            $connecteurResult = $connecteurFrequence;
        }
        return $connecteurResult;
    }

    private function getConnecteurFrequence(Job $job)
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteur_info = $this->getConnecteurEntiteId($job);

        if ($connecteur_info) {
            $connecteurFrequence->type_connecteur = ($connecteur_info['global']) ?
                ConnecteurFrequence::TYPE_GLOBAL : ConnecteurFrequence::TYPE_ENTITE;
            $connecteurFrequence->famille_connecteur = $connecteur_info['type'];
            $connecteurFrequence->id_connecteur = $connecteur_info['id_connecteur'];
            $connecteurFrequence->id_ce = $connecteur_info['id_ce'];
        }
        $connecteurFrequence->action_type = $job->type === Job::TYPE_CONNECTEUR ?
            ConnecteurFrequence::TYPE_ACTION_CONNECTEUR : ConnecteurFrequence::TYPE_ACTION_DOCUMENT;

        if ($job->id_d) {
            $infoDocument = $this->document->getInfo($job->id_d);
            $connecteurFrequence->type_document = $infoDocument['type'];
        }

        $connecteurFrequence->action = $job->etat_cible;

        return $this->getNearestConnecteurFromConnecteur($connecteurFrequence);
    }

    private function getConnecteurEntiteId(Job $job)
    {
        if ($job->type == Job::TYPE_CONNECTEUR) {
            return $this->connecteurEntiteSQL->getInfo($job->id_ce);
        }
        $infoDocument = $this->document->getInfo($job->id_d);
        $connecteur_type = $this->documentTypeFactory->getFluxDocumentType($infoDocument['type'])->getAction()->getProperties($job->etat_cible, 'connecteur-type');

        if (!$connecteur_type) {
            return false;
        }
        return $this->fluxEntiteSQL->getConnecteur($job->id_e, $infoDocument['type'], $connecteur_type);
    }

    public function deleteConnecteur($id_ce)
    {
        $this->jobQueueSQL->deleteConnecteur($id_ce);
    }

    public function deleteDocument($id_e, $id_d)
    {
        $this->jobQueueSQL->deleteDocument($id_e, $id_d);
    }

    public function deleteDocumentForAllEntities(string $id_d): void
    {
        $this->jobQueueSQL->deleteDocumentForAllEntities($id_d);
    }

    public function hasActionProgramme($id_e, $id_d)
    {
        return $this->jobQueueSQL->hasDocumentJob($id_e, $id_d);
    }

    public function setTraitementParLotBulk($id_e, $type, $etat_source, $etat_cible)
    {
        $document_list = $this->documentActionEntite->getDocument($id_e, $type, $etat_source);
        foreach ($document_list as $document_info) {
            $this->logger->info("[BULK ACTION $id_e,$type] {$document_info['id_d']} ({$document_info['titre']}): {$document_info['last_action']} -> $etat_cible");
            $this->setTraitementLot($id_e, $document_info['id_d'], 0, $etat_cible);
        }
    }
}
