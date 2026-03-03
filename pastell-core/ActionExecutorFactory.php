<?php

use Monolog\Logger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class ActionExecutorFactory
{
    public const ACTION_FOLDERNAME = "action";
    private const LOCK_TTL_IN_SECONDS = 60 * 60; /* One hour */

    private $lastMessage;
    private $lastMessageString;
    private $lastException;
    private ActionExecutor $lastActionClass;

    public function __construct(
        private readonly ObjectInstancier $objectInstancier
    ) {
    }

    public function getLastMessage()
    {
        return $this->lastMessage;
    }

    public function getLastMessageString()
    {
        if (isset($this->lastMessageString) && ($this->lastMessageString !== false)) {
            return $this->lastMessageString;
        }
        return $this->getLastMessage();
    }

    public function getLastException(): ?Exception
    {
        return $this->lastException;
    }

    public function getLogger(): Logger
    {
        return $this->objectInstancier->getInstance(Logger::class);
    }

    /**
     * @return JobManager
     */
    public function getJobManager()
    {
        return $this->objectInstancier->getInstance(JobManager::class);
    }

    private function getLock(string $lock_name): LockInterface
    {
        $lockFactory = $this->objectInstancier->getInstance(LockFactory::class);
        return $lockFactory->createLock($lock_name, self::LOCK_TTL_IN_SECONDS);
    }

    /**
     * @deprecated It's shit, don't use it
     */
    public function setLastClassAction(ActionExecutor $actionExecutor): void
    {
        $this->lastActionClass = $actionExecutor;
    }

    public function executeOnConnecteur($id_ce, $id_u, $action_name, $from_api = false, $action_params = [], $id_worker = 0): ?bool
    {
        $lock = $this->getLock("connecteur-$id_ce");
        if (! $lock->acquire()) {
            $this->getLogger()->notice(
                \sprintf(
                    'executeOnConnecteur : unable to lock action on connecteur (id_ce=%s, id_u=%s, action_name=%s)',
                    $id_ce,
                    $id_u,
                    $action_name
                )
            );
            $this->lastMessage = "Une action est déjà en cours de réalisation sur ce connecteur";
            return false;
        }
        try {
            $result = $this->executeOnConnecteurCritical($id_ce, $id_u, $action_name, $from_api, $action_params, $id_worker);
        } finally {
            $lock->release();
        }
        return $result;
    }

    private function executeOnConnecteurCritical($id_ce, $id_u, $action_name, $from_api = false, $action_params = [], $id_worker = 0): ?bool
    {
        try {
            $this->getLogger()->info("executeOnConnecteur - appel - id_ce=$id_ce,id_u=$id_u,action_name=$action_name");
            $this->getLogger()->pushProcessor(function ($record) use ($id_ce, $id_u, $action_name) {
                $record['extra']['id_ce'] = $id_ce;
                $record['extra']['id_u'] = $id_u;
                $record['extra']['action_name'] = $action_name;
                return $record;
            });
            /** @var WorkerSQL $workerSQL */
            $workerSQL = $this->objectInstancier->getInstance(WorkerSQL::class);
            $id_worker_en_cours  = $workerSQL->getActionEnCoursForConnecteur($id_ce, $action_name);
            if ($id_worker_en_cours != $id_worker) {
                throw new Exception("Une action est déjà en cours de réalisation sur ce connecteur");
            }
            $result = $this->executeOnConnecteurThrow($id_ce, $id_u, $action_name, $from_api, $action_params);
        } catch (Exception $e) {
            $this->lastMessage = $e->getMessage();
            $result =  false;
        }

        $lastMessageString = $this->getLastMessageString();
        try {
            $this->getJobManager()->setJobForConnecteur($id_ce, $action_name, $lastMessageString);
        } catch (Exception $e) {
            $this->lastMessage = "L'action n'a pas pu s'exécuter en totalité.\nErreur : {$e->getMessage()}\nRésultat partiel : $lastMessageString";
            $result =  false;
        }

        $this->getLogger()->info(
            "executeOnConnecteur - fin - id_ce=$id_ce,id_u=$id_u,action_name=$action_name : " .
            ($result ? 'OK' : 'KO') . ' - ' .
            \json_encode($this->lastMessage, \JSON_THROW_ON_ERROR)
        );
        $this->getLogger()->popProcessor();
        return $result;
    }

    public function executeOnDocument(
        $id_e,
        $id_u,
        $id_d,
        $action_name,
        $id_destinataire = [],
        $from_api = false,
        $action_params = [],
        $id_worker = 0
    ): ?bool {
        $lock = $this->getLock("document-$id_d");
        if (! $lock->acquire()) {
            $this->getLogger()->notice(
                \sprintf(
                    'executeOnDocument : unable to lock action on document (id_e=%s, id_u=%s, id_d=%s, action_name=%s)',
                    $id_e,
                    $id_u,
                    $id_d,
                    $action_name
                )
            );
            $this->lastMessage = "Une action est déjà en cours de réalisation sur ce document";
            return false;
        }
        try {
            $result = $this->executeOnDocumentCritical($id_e, $id_u, $id_d, $action_name, $id_destinataire, $from_api, $action_params, $id_worker);
        } finally {
            $lock->release();
        }
        return $result;
    }


    public function executeOnDocumentCritical(
        $id_e,
        $id_u,
        $id_d,
        $action_name,
        $id_destinataire = [],
        $from_api = false,
        $action_params = [],
        $id_worker = 0,
        bool $updateJobQueueAfterExecution = true,
    ): ?bool {
        try {
            $this->getLogger()->info(
                "executeOnDocument - appel - id_e=$id_e,id_d=$id_d,id_u=$id_u,action_name=$action_name"
            );
            $this->getLogger()->pushProcessor(function ($record) use ($id_e, $id_d, $id_u, $action_name) {
                $record['extra']['id_e'] = $id_e;
                $record['extra']['id_d'] = $id_d;
                $record['extra']['id_u'] = $id_u;
                $record['extra']['action_name'] = $action_name;
                return $record;
            });

            $result = $this->executeOnDocumentThrow($id_d, $id_e, $id_u, $action_name, $id_destinataire, $from_api, $action_params, $id_worker);
        } catch (UnrecoverableException $e) {
            $jobQueue = $this->objectInstancier->getInstance(JobQueueSQL::class);
            $id_job = $jobQueue->getJobIdForDocumentAndAction($id_e, $id_d, $action_name);
            if ($id_job) {
                $jobQueue->lock($id_job);
            }
            $this->lastMessage = $e->getMessage();
            $result = false;
            $this->lastException = $e;
        } catch (Exception $e) {
            $this->lastMessage = $e->getMessage();
            $result = false;
            $this->lastException = $e;
        }
        if (
            $updateJobQueueAfterExecution &&
            (isset($this->lastActionClass) && $this->lastActionClass->updateJobQueueAfterExecution())
        ) {
            $this->getJobManager()->setJobForDocument($id_e, $id_d, $this->getLastMessageString(), $action_name);
        } else {
            $this->getJobManager()->deleteJobForDocument($id_e, $id_d, $action_name);
        }
        $this->getLogger()->info(
            "executeOnDocument - fin - id_e=$id_e,id_d=$id_d,id_u=$id_u,action_name=$action_name - " .
            ($result ? 'OK' : 'KO') . ' - ' .
            \json_encode($this->lastMessage, \JSON_THROW_ON_ERROR)
        );
        $this->getLogger()->popProcessor();
        return $result;
    }

    public function displayChoice($id_e, $id_u, $id_d, $action_name, $from_api, $field, $page = 0)
    {

        $infoDocument = $this->objectInstancier->getInstance(DocumentSQL::class)->getInfo($id_d);
        $documentType = $this->objectInstancier
            ->getInstance(DocumentTypeFactory::class)
            ->getFluxDocumentType($infoDocument['type']);

        $action_class_name = $this->getActionClassName($documentType, $action_name);

        /** @var ChoiceActionExecutor $actionClass */
        $actionClass = $this->getInstance($action_class_name, $id_e, $id_u, $action_name);
        $actionClass->setDocumentId($infoDocument['type'], $id_d);
        $actionClass->setFromApi($from_api);
        $actionClass->setField($field);
        $actionClass->setPage($page ?: 0);


        if ($from_api) {
            $result = $actionClass->displayAPI();
        } else {
            $result = $actionClass->display();
        }
        return $result;
    }

    public function getChoiceForSearch($id_e, $id_u, $type, $action_name, $field)
    {
        $documentType = $this->objectInstancier->getInstance(DocumentTypeFactory::class)->getFluxDocumentType($type);
        $action_class_name = $this->getActionClassName($documentType, $action_name);
        /** @var ChoiceActionExecutor $actionClass */
        $actionClass = $this->getInstance($action_class_name, $id_e, $id_u, $action_name);
        $actionClass->setField($field);
        $actionClass->setDocumentId($type, 0);

        return $actionClass->displayChoiceForSearch();
    }

    public function isChoiceEnabled($id_e, $id_u, $id_d, $action_name)
    {
        $infoDocument = $this->objectInstancier->getInstance(DocumentSQL::class)->getInfo($id_d);

        $documentType = $this->objectInstancier
            ->getInstance(DocumentTypeFactory::class)
            ->getFluxDocumentType($infoDocument['type']);

        $action_class_name = $this->getActionClassName($documentType, $action_name);

        /** @var ChoiceActionExecutor $actionClass */
        $actionClass = $this->getInstance($action_class_name, $id_e, $id_u, $action_name);
        $actionClass->setDocumentId($infoDocument['type'], $id_d);
        return $actionClass->isEnabled();
    }

    //TODO simplifier le action_name peut être déduit du field
    public function displayChoiceOnConnecteur($id_ce, $id_u, $action_name, $field, $is_api = false)
    {
        $connecteur_entite_info = $this->objectInstancier->getInstance(ConnecteurEntiteSQL::class)->getInfo($id_ce);
        if ($connecteur_entite_info['id_e']) {
            $documentType = $this->objectInstancier
                ->getInstance(DocumentTypeFactory::class)
                ->getEntiteDocumentType($connecteur_entite_info['id_connecteur']);
        } else {
            $documentType = $this->objectInstancier
                ->getInstance(DocumentTypeFactory::class)
                ->getGlobalDocumentType($connecteur_entite_info['id_connecteur']);
        }

        $action_class_name = $this->getActionClassName($documentType, $action_name);
        /** @var ChoiceActionExecutor $actionClass */
        $actionClass = $this->getInstance($action_class_name, $connecteur_entite_info['id_e'], $id_u, $action_name);
        $actionClass->setConnecteurId($connecteur_entite_info['id_connecteur'], $id_ce);
        $actionClass->setField($field);
        try {
            if ($is_api) {
                $result = $actionClass->displayAPI();
            } else {
                $result = $actionClass->display();
            }
        } catch (Exception $e) {
            $this->lastMessage = $e->getMessage();
            return false;
        }
        $this->lastMessage = $actionClass->getLastMessage();
        return $result;
    }

    public function goChoice($id_e, $id_u, $id_d, $action_name, $from_api, $field, int $page = 0, $post_data = false)
    {
        $infoDocument = $this->objectInstancier->getInstance(DocumentSQL::class)->getInfo($id_d);
        $documentType = $this->objectInstancier
            ->getInstance(DocumentTypeFactory::class)
            ->getFluxDocumentType($infoDocument['type']);

        $action_class_name = $this->getActionClassName($documentType, $action_name);
        /** @var ChoiceActionExecutor $actionClass */
        $actionClass = $this->getInstance($action_class_name, $id_e, $id_u, $action_name);
        $actionClass->setDocumentId($infoDocument['type'], $id_d);
        $actionClass->setFromApi($from_api);
        $actionClass->setField($field);
        $actionClass->setPage($page ?: 0);
        if ($post_data) {
            $actionClass->setRecuperateur(new Recuperateur($post_data));
        }

        $actionClass->go();

        if (! $from_api) {
            $actionClass->redirectToFormulaire();
        }
    }

    public function goChoiceOnConnecteur($id_ce, $id_u, $action_name, $field, $is_api = false, $post_data = [])
    {

        $connecteur_entite_info = $this->objectInstancier->getInstance(ConnecteurEntiteSQL::class)->getInfo($id_ce);

        if ($connecteur_entite_info['id_e']) {
            $documentType = $this->objectInstancier
                ->getInstance(DocumentTypeFactory::class)
                ->getEntiteDocumentType($connecteur_entite_info['id_connecteur']);
        } else {
            $documentType = $this->objectInstancier
                ->getInstance(DocumentTypeFactory::class)
                ->getGlobalDocumentType($connecteur_entite_info['id_connecteur']);
        }

        $action_class_name = $this->getActionClassName($documentType, $action_name);

        /** @var ChoiceActionExecutor $actionClass */
        $actionClass = $this->getInstance($action_class_name, $connecteur_entite_info['id_e'], $id_u, $action_name);
        $actionClass->setConnecteurId($connecteur_entite_info['id_connecteur'], $id_ce);
        $actionClass->setField($field);
        if (!empty($post_data)) {
            $actionClass->setRecuperateur(new Recuperateur($post_data));
        }
        try {
            $actionClass->go();
            $has_error = false;
        } catch (Exception $e) {
            $this->lastMessage = $e->getMessage() ;
            $has_error = true;
        }
        if (! $is_api) {
            $actionClass->redirectToConnecteurFormulaire();
        }
        return ! $has_error;
    }

    public function executeOnDocumentThrow(
        $id_d,
        $id_e,
        $id_u,
        $action_name,
        $id_destinataire,
        $from_api,
        $action_params,
        $id_worker
    ) {
        $actionClass = $this->getActionClass(
            $id_d,
            $id_e,
            $id_u,
            $action_name,
            $id_destinataire,
            $from_api,
            $action_params,
            $id_worker
        );
        $this->lastActionClass = $actionClass;
        $result = $actionClass->go();
        $this->lastMessageString = $actionClass->getLastMessageString();
        $this->lastMessage = $actionClass->getLastMessage();
        return $result;
    }

    private function getActionClass($id_d, $id_e, $id_u, $action_name, $id_destinataire, $from_api, $action_params, $id_worker)
    {
        $infoDocument = $this->objectInstancier->getInstance(DocumentSQL::class)->getInfo($id_d);
        $documentType = $this->objectInstancier
            ->getInstance(DocumentTypeFactory::class)
            ->getFluxDocumentType($infoDocument['type']);

        $action_class_name = $this->getActionClassName($documentType, $action_name);

        $actionClass = $this->getInstance($action_class_name, $id_e, $id_u, $action_name);
        $actionClass->setDocumentId($infoDocument['type'], $id_d);
        $actionClass->setDestinataireId($id_destinataire);
        $actionClass->setActionParams($action_params);
        $actionClass->setFromAPI($from_api);
        $actionClass->setIdWorker($id_worker);

        return $actionClass;
    }

    private function executeOnConnecteurThrow($id_ce, $id_u, $action_name, $from_api = false, $action_params = [])
    {
        $connecteur_entite_info = $this->objectInstancier->getInstance(ConnecteurEntiteSQL::class)->getInfo($id_ce);
        if ($connecteur_entite_info['id_e']) {
            $documentType = $this->objectInstancier
                ->getInstance(DocumentTypeFactory::class)
                ->getEntiteDocumentType($connecteur_entite_info['id_connecteur']);
        } else {
            $documentType = $this->objectInstancier->getInstance(DocumentTypeFactory::class)
                ->getGlobalDocumentType($connecteur_entite_info['id_connecteur']);
        }

        $action_class_name = $this->getActionClassName($documentType, $action_name);

        $actionClass = $this->getInstance($action_class_name, $connecteur_entite_info['id_e'], $id_u, $action_name);
        $actionClass->setConnecteurId($connecteur_entite_info['id_connecteur'], $id_ce);
        $actionClass->setActionParams($action_params);
        $actionClass->setFromAPI($from_api);
        $result = $actionClass->go();
        $this->lastMessageString = $actionClass->getLastMessageString();
        $this->lastMessage = $actionClass->getLastMessage();
        return $result;
    }

    /**
     * @param DocumentType $documentType
     * @param $action_name
     * @return string
     * @throws UnrecoverableException
     */
    private function getActionClassName(DocumentType $documentType, $action_name): string
    {
        $theAction = $documentType->getAction();
        $action_class_name = $theAction->getActionClass($action_name);
        if ($action_class_name) {
            return $action_class_name;
        }

        $default_action_class_map = [
            CreationAction::ACTION_ID => CreationAction::class,
            ModificationAction::ACTION_ID => ModificationAction::class,
            FatalError::ACTION_ID => FatalError::class
        ];
        if (isset($default_action_class_map[$action_name])) {
            return $default_action_class_map[$action_name];
        }

        throw new UnrecoverableException("L'action $action_name n'existe pas.");
    }

    /**
     * @throws UnrecoverableException
     */
    private function getInstance(string $action_class_name, $id_e, $id_u, string $action_name): \ActionExecutor
    {
        $actionClass = $this->objectInstancier->newInstance($action_class_name);

        if (!$actionClass instanceof ActionExecutor) {
            throw new UnrecoverableException("The action needs to extends : " . ActionExecutor::class);
        }
        $actionClass->setEntiteId($id_e);
        $actionClass->setUtilisateurId($id_u);
        $actionClass->setAction($action_name);
        $actionClass->setLogger($this->getLogger());
        return $actionClass;
    }

    public function executeLotDocument($id_e, $id_u, array $all_id_d, $action_name, $id_destinataire = [], $from_api = false, $action_params = [], $id_worker = 0)
    {
        $actionClass = $this->getActionClass($all_id_d[0], $id_e, $id_u, $action_name, $id_destinataire, $from_api, $action_params, $id_worker);
        $actionClass->goLot($all_id_d);
    }
}
