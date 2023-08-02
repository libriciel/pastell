<?php

use Monolog\Logger;

abstract class ActionExecutor
{
    protected $id_d;
    protected $id_e;
    protected $id_u;
    protected $action;
    protected $id_destinataire;
    protected $from_api;
    protected $id_ce;
    protected $type;
    protected $action_params;
    protected $id_worker;

    protected $objectInstancier;
    private ?DonneesFormulaire $docDonneesFormulaire = null;
    private $connecteurs;
    private $connecteurConfigs;

    private $lastMessage;
    private $lastMessageString;

    abstract public function go();

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
    }

    public function getSiteBase(): string
    {
        return $this->objectInstancier->getInstance('site_base');
    }

    public function setEntiteId($id_e)
    {
        $this->id_e = $id_e;
    }

    public function setUtilisateurId($id_u)
    {
        $this->id_u = $id_u;
    }

    public function setAction($action_name)
    {
        $this->action = $action_name;
    }

    public function setConnecteurId($type, $id_ce)
    {
        $this->id_ce = $id_ce;
        $this->type = $type;
    }

    public function setDocumentId($type, $id_d)
    {
        $this->id_d = $id_d;
        $this->type = $type;
    }

    public function clearCache(): void
    {
        $this->connecteurs = false;
        $this->docDonneesFormulaire = null;
        $this->connecteurConfigs = [];
    }

    public function setDestinataireId(array $id_destinataire)
    {
        $this->id_destinataire = $id_destinataire;
    }

    public function setActionParams(array $action_params)
    {
        $this->action_params = $action_params;
    }

    public function setFromApi($from_api)
    {
        $this->from_api = $from_api;
    }

    public function setIdWorker($id_worker)
    {
        $this->id_worker = $id_worker;
    }

    public function getLastMessage()
    {
        return $this->lastMessage;
    }

    public function setLastMessage($message)
    {
        $this->lastMessage = $message;
    }

    public function getLastMessageString()
    {
        return $this->lastMessageString;
    }

    public function setLastMessageString($message)
    {
        $this->lastMessageString = $message;
    }

    /**
     * @throws NotFoundException
     */
    public function getDonneesFormulaire(): DonneesFormulaire
    {
        if ($this->docDonneesFormulaire === null) {
            $this->docDonneesFormulaire = $this->getDonneesFormulaireFactory()->get($this->id_d);
        }
        return $this->docDonneesFormulaire;
    }

    /**
     * Permet de récupérer l'objet Formulaire configuré pour ce DonneesFormulaire
     * @return Formulaire
     */
    public function getFormulaire()
    {
        return $this->docDonneesFormulaire->getFormulaire();
    }

    /**
     * @return Journal
     */
    public function getJournal()
    {
        return $this->objectInstancier->getInstance(Journal::class);
    }

    /**
     * @return DonneesFormulaireFactory
     */
    public function getDonneesFormulaireFactory()
    {
        return $this->objectInstancier->getInstance(DonneesFormulaireFactory::class);
    }

    /**
     * @return DocumentEntite
     */
    public function getDocumentEntite()
    {
        return $this->objectInstancier->getInstance(DocumentEntite::class);
    }

    public function getDocument(): DocumentSQL
    {
        return $this->objectInstancier->getInstance(DocumentSQL::class);
    }

    /**
     * @return DocumentActionEntite
     */
    public function getDocumentActionEntite()
    {
        return $this->objectInstancier->getInstance(DocumentActionEntite::class);
    }

    /**
     * @return DocumentTypeFactory
     */
    public function getDocumentTypeFactory(): DocumentTypeFactory
    {
        return $this->objectInstancier->getInstance(DocumentTypeFactory::class);
    }

    /**
     * @return EntiteSQL
     */
    public function getEntiteSQL()
    {
        return $this->objectInstancier->getInstance(EntiteSQL::class);
    }

    /**
     * @return SQLQuery
     */
    public function getSQLQuery()
    {
        return $this->objectInstancier->getInstance(SQLQuery::class);
    }

    /**
     * @return NotificationMail
     */
    public function getNotificationMail()
    {
        return $this->objectInstancier->getInstance(NotificationMail::class);
    }

    public function isGlobalConnecteur(int $id_ce): int
    {
        return $this->objectInstancier->getInstance(ConnecteurEntiteSQL::class)->getInfo($id_ce)['global'];
    }

    public function getDocumentType(): DocumentType
    {
        if ($this->isConnectorAction()) {
            $documentType = $this->isGlobalConnecteur($this->id_ce) ?
                $this->getDocumentTypeFactory()->getGlobalDocumentType($this->type)
                : $this->getDocumentTypeFactory()->getEntiteDocumentType($this->type);
        } else {
            $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($this->type);
        }
        return $documentType;
    }

    public function getActionName()
    {
        return $this->getDocumentType()->getAction()->getActionName($this->action);
    }


    /**
     * @param $type_connecteur
     * @param int $num_same_connecteur
     * @return mixed
     * @throws UnrecoverableException
     */
    public function getConnecteurId($type_connecteur, $num_same_connecteur = 0)
    {
        $num_same_connecteur = $this->getDocumentType()
            ->getAction()
            ->getProperties($this->action, 'num-same-connecteur') ?: $num_same_connecteur;

        $id_ce = $this->getConnecteurFactory()->getConnecteurId($this->id_e, $this->type, $type_connecteur, $num_same_connecteur);
        if (!$id_ce) {
            throw new UnrecoverableException("Aucun connecteur de type $type_connecteur n'est associé au type de dossier {$this->type}");
        }
        return $id_ce;
    }


    /**
     * @param $type_connecteur
     * @param int $num_same_connecteur
     * @return Connecteur|false
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function getConnecteur($type_connecteur, $num_same_connecteur = 0): Connecteur|false
    {
        $num_same_connecteur = $this->getDocumentType()
            ->getAction()
            ->getProperties($this->action, 'num-same-connecteur') ?: $num_same_connecteur;

        if (isset($this->connecteurs[$type_connecteur][$num_same_connecteur])) {
            return $this->connecteurs[$type_connecteur][$num_same_connecteur] ;
        }

        $id_ce = $this->getConnecteurId($type_connecteur, $num_same_connecteur);
        $connecteur = $this->getConnecteurFactory()->getConnecteurById($id_ce);

        $this->checkValidityConnector();

        if ($this->id_d) {
            $connecteur->setDocDonneesFormulaire($this->getDonneesFormulaire());
        }

        $this->connecteurs[$type_connecteur][$num_same_connecteur] = $connecteur;
        return $connecteur;
    }

    /**
     * @throws Exception
     */
    private function checkValidityConnector(): void
    {
        //TODO delete condition when used in prod
        if (
            $this->objectInstancier->getInstance('useExternalStorageForPasswordConnector')
            && isset($this->id_ce)
        ) {
            $error = $this->getConnecteurConfig($this->id_ce)->getLastError();
            if ($error) {
                throw new UnrecoverableException($error);
            }
        }
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function getConnecteurOrFail(string $type_connecteur, int $num_same_connecteur = 0): Connecteur
    {
        $connecteur = $this->getConnecteur($type_connecteur, $num_same_connecteur);
        if (!$connecteur) {
            throw new UnrecoverableException("Aucun connecteur $type_connecteur disponible");
        }
        return $connecteur;
    }

    /**
     *
     * @param string $type_connecteur
     * @throws Exception
     * @return DonneesFormulaire
     */
    public function getConnecteurConfigByType($type_connecteur)
    {
        $num_same_connecteur = $this->getDocumentType()
            ->getAction()
            ->getProperties($this->action, 'num-same-connecteur') ?: 0;

        if (isset($this->connecteurConfigs[$type_connecteur][$num_same_connecteur])) {
            return $this->connecteurConfigs[$type_connecteur][$num_same_connecteur];
        }
        $id_ce = $this->getConnecteurId($type_connecteur);
        $connecteurConfig = $this->getConnecteurConfig($id_ce);
        $this->connecteurConfigs[$type_connecteur][$num_same_connecteur] = $connecteurConfig;
        return $connecteurConfig;
    }


    /**
     * @return DonneesFormulaire
     * @param int $id_ce
     */
    public function getConnecteurConfig($id_ce)
    {
        return $this->getConnecteurFactory()->getConnecteurConfig($id_ce);
    }

    /**
     * @return DonneesFormulaire
     */
    public function getConnecteurProperties()
    {
        return $this->getConnecteurConfig($this->id_ce);
    }

    /**
     *
     * @throws Exception
     * @return Connecteur
     */
    public function getMyConnecteur()
    {
        if (! $this->id_ce) {
            throw new Exception("Cette action n'est pas une action de connecteur.");
        }
        $connecteur = $this->getConnecteurFactory()->getConnecteurById($this->id_ce);
        $this->checkValidityConnector();
        return $connecteur;
    }

    /**
     * @return ConnecteurFactory
     */
    public function getConnecteurFactory()
    {
        return $this->objectInstancier->getInstance(ConnecteurFactory::class);
    }

    public function getGlobalConnecteur($type)
    {
        return $this->getConnecteurFactory()->getGlobalConnecteur($type);
    }

    public function getActionChange(): ActionChange
    {
        return $this->objectInstancier->getInstance(ActionChange::class);
    }

    /***** Fonction utilitaire *****/

    public function addActionOK($message = ''): void
    {
        $this->changeAction($this->action, $message);
    }

    public function changeAction($action, $message): void
    {
        $this->getActionChange()->addAction(
            $this->id_d,
            $this->id_e,
            $this->id_u,
            $action,
            $message
        );
        $this->setLastMessage($message);
    }

    public function changeOrUpdateAction(
        string $action,
        string $message,
        bool $updateDate = false,
    ): void {
        $this->getActionChange()->addOrUpdateAction(
            $this->id_d,
            $this->id_e,
            $this->id_u,
            $action,
            $message,
            $updateDate
        );
        $this->setLastMessage($message);
    }

    public function notify($actionName, $type, $message)
    {
        $this->getNotificationMail()->notify($this->id_e, $this->id_d, $actionName, $type, $message);
    }

    /**
     * @throws Exception
     */
    public function redirect(string $to)
    {
        if (! $this->from_api) {
            $location = $this->getSiteBase() . '/' . \ltrim($to, '/');
            header_wrapper("Location: $location");
            exit_wrapper();
        }
    }

    /**
     * Méthode standard pour le traitement par lot : on enregistre dans la job queue les travaux qui s'éxecuteront de manière asynchrone
     * @param array $all_id_d
     */
    public function goLot(array $all_id_d)
    {
        foreach ($all_id_d as $id_d) {
            $this->objectInstancier->getInstance(JobManager::class)->setTraitementLot(
                $this->id_e,
                $id_d,
                $this->id_u,
                $this->action
            );
            $this->objectInstancier->getInstance(Journal::class)->add(
                Journal::DOCUMENT_TRAITEMENT_LOT,
                $this->id_e,
                $id_d,
                $this->action,
                "Programmation dans le cadre d'un traitement par lot"
            );
        }
        return true;
    }

    //Lors d'un traitement par lot spécifique (synchrone par exemple), il est nécessaire de réactiver le job manager pour le docuemnt en question
    public function setJobManagerForLot(array $all_id_d)
    {
        /** @var JobManager $jobManager */
        $jobManager = $this->objectInstancier->getInstance(JobManager::class);

        foreach ($all_id_d as $id_d) {
            $jobManager->setJobForDocument($this->id_e, $id_d, "suite traitement par lot");
        }
    }

    private $logger;
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return Monolog\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    public function getIdMapping(): StringMapper
    {
        $connecteur_type_mapping = $this->getDocumentType()->getAction()->getProperties(
            $this->action,
            Action::CONNECTEUR_TYPE_MAPPING
        ) ?: [];
        $stringMapper = new StringMapper();
        $stringMapper->setMapping($connecteur_type_mapping);
        return $stringMapper;
    }


    /**
     * @return ConnecteurTypeActionExecutor|ConnecteurTypeChoiceActionExecutor
     * @throws RecoverableException
     */
    protected function getConnecteurTypeActionExecutor()
    {
        $documentType = $this->getDocumentType();
        $connecteur_type = $documentType->getAction()->getProperties($this->action, 'connecteur-type');
        if (!$connecteur_type) {
            throw new RecoverableException("Aucun connecteur type n'a été défini pour l'action {$this->action}");
        }

        $connecteur_type_action = $documentType->getAction()->getProperties($this->action, 'connecteur-type-action');
        if (!$connecteur_type_action) {
            throw new RecoverableException(
                "Aucune action n'a été défini pour l'action {$this->action} (connecteur-type : $connecteur_type)"
            );
        }

        $connecteurTypeFactory = $this->objectInstancier->getInstance(ConnecteurTypeFactory::class);
        $connecteurTypeActionExecutor =
            $connecteurTypeFactory->getActionExecutor($connecteur_type, $connecteur_type_action);

        if (!$connecteurTypeActionExecutor) {
            throw new RecoverableException(
                "Impossible d'instancier une classe pour l'action : $connecteur_type:$connecteur_type_action"
            );
        }

        $connecteurTypeActionExecutor->setEntiteId($this->id_e);
        $connecteurTypeActionExecutor->setUtilisateurId($this->id_u);

        $connecteurTypeActionExecutor->setAction($this->action);

        $connecteurTypeActionExecutor->setDocumentId($this->type, $this->id_d);
        $connecteurTypeActionExecutor->setConnecteurId($this->type, $this->id_ce);
        $connecteurTypeActionExecutor->setDestinataireId($this->id_destinataire ?: []);
        $connecteurTypeActionExecutor->setActionParams($this->action_params ?: []);
        $connecteurTypeActionExecutor->setFromApi($this->from_api);
        $connecteurTypeActionExecutor->setIdWorker($this->id_worker);

        $connecteurTypeActionExecutor->setMapping($documentType->getAction()->getConnecteurTypeMapping($this->action));
        $connecteurTypeActionExecutor
            ->setTransformations($documentType->getAction()->getTransformations($this->action));

        $connecteur_type_data_seda_class_name
            = $documentType->getAction()->getConnecteurTypeDataSedaClassName($this->action);
        if (!$connecteur_type_data_seda_class_name) {
            $connecteur_type_data_seda_class_name = 'FluxDataSedaDefault';
        }
        $connecteurTypeActionExecutor->setDataSedaClassName($connecteur_type_data_seda_class_name);

        return $connecteurTypeActionExecutor;
    }

    private function isConnectorAction(): bool
    {
        return is_null($this->id_d) && $this->id_ce;
    }

    public function updateJobQueueAfterExecution(): bool
    {
        return true;
    }
}
