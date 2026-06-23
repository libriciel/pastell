<?php

use Monolog\Logger;
use Pastell\Security\LibricielFeedbackReader;
use Pastell\Service\Document\DocumentEmailService;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Menu\MenuGaucheService;
use Pastell\Service\Module\ModuleListService;

class PastellControler extends Controler
{
    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function _beforeAction()
    {
        if (! $this->getAuthentification()->isConnected()) {
            $request_uri = $_SERVER['REQUEST_URI'];
            if ($this->getGetInfo()->get(FrontController::PAGE_REQUEST)) {
                $this->setLastError("Veuillez saisir vos identifiants de connexion pour accéder à cette page.");
            }
            $this->redirect("/Connexion/connexion?request_uri=" . urlencode($request_uri));
        }
        if (! $this->getUtilisateur()->isEnabled($this->getAuthentification()->getId())) {
            $request_uri = $_SERVER['REQUEST_URI'];
            $this->setLastError('Votre compte a été désactivé');
            $this->redirect('/Connexion/connexion?request_uri=' . urlencode($request_uri));
        }
    }

    protected function setDroitLectureOnConnecteur(int $id_e): void
    {
        $this->setViewParameter('droit_lecture_on_connecteur', $this->getDroitService()->hasDroitConnecteurLecture(
            $id_e,
            $this->getId_u()
        ));
    }

    protected function setCanActOnConnector(int $entityId): void
    {
        $this->setViewParameter(
            'canActOnConnector',
            $this->getDroitService()->hasConnectorActionPermission(
                $entityId,
                $this->getId_u(),
            )
        );
    }

    protected function setCanEditConnector(int $entityId): void
    {
        $this->setViewParameter(
            'canEditConnector',
            $this->getDroitService()->hasDroitConnecteurEdition(
                $entityId,
                $this->getId_u(),
            )
        );
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    protected function setDroitsDaemon(int $id_e): void
    {
        $this->setViewParameter(
            'daemon_global_lecture',
            $this->hasDroit(
                EntiteSQL::ID_E_ENTITE_RACINE,
                DroitService::getDroitLecture(DroitService::DROIT_DAEMON)
            )
        );
        $this->setViewParameter(
            'daemon_lecture',
            $this->hasDroit($id_e, DroitService::getDroitLecture(DroitService::DROIT_DAEMON))
        );
        $this->setViewParameter(
            'daemon_edition',
            $this->hasDroit($id_e, DroitService::getDroitEdition(DroitService::DROIT_DAEMON))
        );
        $this->setViewParameter('daemon_exists', $this->getDaemonSQL()->getDaemonByEntity($id_e));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function hasConnecteurDroitEdition(int $id_e): void
    {
        $this->verifDroit($id_e, DroitService::getDroitEdition(DroitService::DROIT_CONNECTEUR));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function hasConnecteurDroitLecture(int $id_e): void
    {
        $this->verifDroit($id_e, DroitService::getDroitLecture(DroitService::DROIT_CONNECTEUR));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function hasConnectorActionPermission(int $entityId): void
    {
        $this->verifDroit(
            $entityId,
            DroitService::getDroitAction(DroitService::DROIT_CONNECTEUR),
        );
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function hasUtilisateurDroitLecture(int $id_e): void
    {
        $this->verifDroit($id_e, DroitService::getDroitLecture(DroitService::DROIT_UTILISATEUR));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function hasEntiteDroitLecture(int $id_e): void
    {
        $this->verifDroit($id_e, DroitService::getDroitLecture(DroitService::DROIT_ENTITE));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function hasDroitEdition($id_e)
    {
        $this->verifDroit($id_e, DroitService::getDroitEdition(DroitService::DROIT_ENTITE));
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function verifDroit($id_e, $droit, $redirect_to = ""): bool
    {
        if ($id_e && ! $this->getEntiteSQL()->getInfo($id_e)) {
            if ($this->hasDroit(0, $droit)) {
                return true;
            }
            $this->setLastError("L'entité $id_e n'existe pas");
            $this->redirect("/index.php");
        }

        if (! $this->hasDroit($id_e, $droit)) {
            $this->setLastError("Vous n'avez pas les droits nécessaires ($id_e:$droit) pour accéder à cette page");
            $this->redirect($redirect_to);
        }

        return true;
    }

    public function hasDroit($id_e, $droit): bool
    {
        if (! $this->getId_u()) {
            return true;
        }
        return $this->getRoleUtilisateur()->hasDroit($this->getId_u(), $droit, $id_e);
    }

    public function getId_u()
    {
        return $this->getAuthentification()->getId();
    }

    public function setMenuGaucheSelect(string $menu_gauche_id): void
    {
        $this->setViewParameter('menu_gauche_select', $menu_gauche_id);
    }

    public function setNavigationInfo($id_e, $url)
    {
        $listeCollectivite = $this->getRoleUtilisateur()->getEntiteWithSomeDroit($this->getId_u());
        if (! $listeCollectivite) {
            $this->setViewParameter('navigation', []);
            $this->setViewParameter('navigation_url', $url);
            return;
        }

        $ancestors = $this->getEntiteSQL()->getAncetreNav($id_e, $listeCollectivite);
        $navigation = [];
        $rootNav = [
            'is_root' => true,
            'id_e' => 0,
            'name' => $this->getEntiteSQL()->getDenomination(0),
            'children' => $this->getEntiteSQL()->getFilleInfoNavigation(0, $listeCollectivite),
            'is_last' => true,
        ];

        if ($id_e == 0) {
            $navigation[] = $rootNav;
        } else {
            $rootNav['is_last'] = false;
            $navigation[] = $rootNav;

            foreach ($ancestors as $ancestor) {
                $navigation[] = [
                    'is_root' => false,
                    'id_e' => $ancestor['id_e'],
                    'name' => $this->getEntiteSQL()->getDenomination($ancestor['id_e']),
                    'same_level_entities' => $this->getEntiteSQL()->getSiblingsWithPermission(
                        $ancestor['id_e'],
                        $this->getId_u()
                    ),
                    'is_last' => false,
                    'has_children' => true,
                ];
            }

            $navigation[] = [
                'is_root' => false,
                'id_e' => $id_e,
                'name' => $this->getEntiteSQL()->getDenomination($id_e),
                'same_level_entities' => $this->getEntiteSQL()->getSiblingsWithPermission(
                    $id_e,
                    $this->getId_u()
                ),
                'children' => $this->getEntiteSQL()->getFilleInfoNavigation($id_e, $listeCollectivite),
                'has_children' => false,
                'is_last' => true,
            ];
        }

        $this->setViewParameter('navigation', $navigation);
        $this->setViewParameter('navigation_url', $url);
    }



    public function render(string $template): void
    {
        $this->setViewParameter('sqlQuery', $this->getSQLQuery());
        $this->setViewParameter('objectInstancier', $this->getObjectInstancier());
        $this->setViewParameter('manifest_info', $this->getManifestFactory()
            ->getPastellManifest()
            ->getInfo());
        parent::render($template);
    }

    /**
     * @throws NotFoundException
     */
    public function renderDefault(): void
    {
        $this->setBreadcrumbs();
        $this->setViewParameter('all_module', $this->getAllModule());
        $this->setViewParameter('authentification', $this->getInstance(Authentification::class));
        $this->setViewParameter('libricielFeedback', $this->getInstance(LibricielFeedbackReader::class));

        $this->setViewParameter('roleUtilisateur', $this->getRoleUtilisateur());
        $this->setViewParameter('sqlQuery', $this->getSQLQuery());
        $this->setViewParameter('objectInstancier', $this->getObjectInstancier());
        $this->setViewParameter('manifest_info', $this->getManifestFactory()->getPastellManifest()->getInfo());

        $this->setViewParameter('timer', $this->getInstance(PastellTimer::class));
        $this->setViewParameter('menu_gauche_template', 'MenuGauche');

        if (!$this->isViewParameter('menu')) {
            $this->setViewParameter('pages_without_left_menu', true);
        }
        if (!$this->isViewParameter('menu_gauche_select')) {
            $pageRequest = $this->getGetInfo()->get(FrontController::PAGE_REQUEST);
            if ($pageRequest) {
                $this->setMenuGaucheSelect($pageRequest);
            }
        }
        if (!$this->isViewParameter('navigation_url')) {
            $this->setViewParameter('navigation_url', "Document/index");
        }

        /** @var DaemonManager $daemonManager */
        $daemonManager = $this->getInstance(DaemonManager::class);

        if (
            $this->getRoleUtilisateur()->hasDroit($this->getId_u(), DroitService::getDroitLecture(DroitService::DROIT_DAEMON), 0)
        ) {
            $this->setViewParameter(
                'nb_job_lock',
                $this->getObjectInstancier()
                    ->getInstance(JobQueueSQL::class)
                    ->getNbLockSinceOneHour()
            );

            $this->setViewParameter('daemon_stopped_warning', $daemonManager->status() === DaemonManager::IS_STOPPED);
        }
        $this->setViewParameter('helpURL', $this->getHelpURL());
        parent::renderDefault();
    }

    public function setEntiteMenuGauche(int $id_e): void
    {
        $this->setViewParameter(
            'menu',
            $this->getInstance(MenuGaucheService::class)->getEntiteMenu($id_e, $this->getId_u())
        );
    }

    /**
     * @throws NotFoundException
     */
    public function setDocumentMenuGauche(int $id_e): void
    {
        $all_module = $this->getAllModule();
        $this->setViewParameter(
            'menu',
            $this->getInstance(MenuGaucheService::class)->getDocumentMenu($all_module, $id_e)
        );
    }

    private function getHelpURL(): string
    {
        $connecteurFactory = $this->getObjectInstancier()->getInstance(ConnecteurFactory::class);
        $donneesFormulaire = $connecteurFactory->getGlobalConnecteurConfig('help-url');
        if ($donneesFormulaire === false) {
            return '';
        }
        return $donneesFormulaire->get('help_url');
    }

    public function setBreadcrumbs()
    {
        if (! $this->isViewParameter('id_e_menu')) {
            $recuperateur = $this->getGetInfo();
            $this->setViewParameter('id_e_menu', $recuperateur->getInt('id_e'));
            $this->setViewParameter('type_e_menu', get_hecho(
                $recuperateur->get(
                    'type',
                    $this->isViewParameter('type_e_menu') ? $this->getViewParameterOrObject('type_e_menu') : ''
                )
            ));
        }

        $listeCollectivite = $this->getRoleUtilisateur()->getEntite($this->getId_u(), "entite:lecture");

        $this->setViewParameter('display_entite_racine', $this->getViewParameterOrObject('id_e_menu') != 0
        && (count($listeCollectivite) > 1 || (isset($listeCollectivite[0]) && $listeCollectivite[0] == 0)));
    }

    /**
     * @return array
     * @throws NotFoundException
     */
    public function getAllModule(): array
    {
        return $this->getInstance(ModuleListService::class)->getModuleListOrderByType($this->getId_u());
    }

    private InternalAPI $internalAPI;

    public function apiCall($method, $ressource, $data)
    {
        if (! isset($this->internalAPI)) {
            $this->internalAPI = $this->getObjectInstancier()->getInstance(InternalAPI::class);
            $this->internalAPI->setCallerType(InternalAPI::CALLER_TYPE_CONSOLE);
            $this->internalAPI->setFileUploader($this->getObjectInstancier()->getInstance(FileUploader::class));
            $this->internalAPI->setUtilisateurId($this->getId_u());
        }
        return $this->internalAPI->$method($ressource, $data);
    }

    protected function apiGet($ressource)
    {
        return $this->apiCall('get', $ressource, $this->getGetInfo()->getAll());
    }

    protected function apiPost($ressource)
    {
        return $this->apiCall('post', $ressource, $this->getPostInfo()->getAll());
    }

    protected function apiDelete($ressource)
    {
        return $this->apiCall('delete', $ressource, []);
    }

    protected function apiPatch($ressource)
    {
        return $this->apiCall('patch', $ressource, $this->getPostInfo()->getAll());
    }

    /* Récupération des objets */

    /**
     * @return SQLQuery
     */
    public function getSQLQuery(): SQLQuery
    {
        return $this->getInstance(SQLQuery::class);
    }

    /**
     * @return EntiteSQL
     */
    public function getEntiteSQL(): EntiteSQL
    {
        return $this->getInstance(EntiteSQL::class);
    }

    /**
     * @return RoleUtilisateur
     */
    public function getRoleUtilisateur(): RoleUtilisateur
    {
        return $this->getInstance(RoleUtilisateur::class);
    }

    /**
     * @return DroitService
     */
    public function getDroitService(): DroitService
    {
        return $this->getInstance(DroitService::class);
    }

    public function getDaemonManager(): DaemonManager
    {
        return $this->getInstance(DaemonManager::class);
    }

    public function getJobQueueSQL(): JobQueueSQL
    {
        return $this->getInstance(JobQueueSQL::class);
    }

    /**
     * @return Authentification
     */
    public function getAuthentification(): Authentification
    {
        return $this->getInstance(Authentification::class);
    }

    public function getDonneesFormulaireFactory(): DonneesFormulaireFactory
    {
        return $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);
    }

    /**
     * @return ConnecteurEntiteSQL
     */
    public function getConnecteurEntiteSQL(): ConnecteurEntiteSQL
    {
        return $this->getInstance(ConnecteurEntiteSQL::class);
    }

    /**
     * @return WorkerSQL
     */
    public function getWorkerSQL(): WorkerSQL
    {
        return $this->getInstance(WorkerSQL::class);
    }

    public function getDaemonSQL(): DaemonSQL
    {
        return $this->getInstance(DaemonSQL::class);
    }

    public function getConfigurationSQL(): ConfigurationSQL
    {
        return $this->getInstance(ConfigurationSQL::class);
    }

    /**
     * @return Journal
     */
    public function getJournal(): Journal
    {
        return $this->getInstance(Journal::class);
    }

    /**
     * @return DocumentTypeFactory
     */
    public function getDocumentTypeFactory(): DocumentTypeFactory
    {
        return $this->getInstance(DocumentTypeFactory::class);
    }

    /**
     * @return ConnecteurFactory
     */
    public function getConnecteurFactory(): ConnecteurFactory
    {
        return $this->getInstance(ConnecteurFactory::class);
    }

    /**
     * @return UtilisateurSQL
     */
    public function getUtilisateur(): UtilisateurSQL
    {
        return $this->getInstance(UtilisateurSQL::class);
    }

    /**
     * @return UtilisateurListe
     */
    public function getUtilisateurListe(): UtilisateurListe
    {
        return $this->getInstance(UtilisateurListe::class);
    }

    /**
     * @return ActionExecutorFactory
     */
    public function getActionExecutorFactory(): ActionExecutorFactory
    {
        return $this->getInstance(ActionExecutorFactory::class);
    }

    /**
     * @return ActionPossible
     */
    public function getActionPossible(): ActionPossible
    {
        return $this->getInstance(ActionPossible::class);
    }

    public function getDocumentSQL(): DocumentSQL
    {
        return $this->getInstance(DocumentSQL::class);
    }

    /**
     * @return DocumentEntite
     */
    public function getDocumentEntite(): DocumentEntite
    {
        return $this->getInstance(DocumentEntite::class);
    }

    public function getDocumentEmailService(): DocumentEmailService
    {
        return $this->getInstance(DocumentEmailService::class);
    }

    /**
     * @return ActionChange
     */
    public function getActionChange(): ActionChange
    {
        return $this->getInstance(ActionChange::class);
    }

    /**
     * @return RoleSQL
     */
    public function getRoleSQL(): RoleSQL
    {
        return $this->getInstance(RoleSQL::class);
    }

    /**
     * @return EntiteListe
     */
    public function getEntiteListe(): EntiteListe
    {
        return $this->getInstance(EntiteListe::class);
    }

    /**
     * @return ConnecteurDefinitionFiles
     */
    public function getConnecteurDefinitionFiles(): ConnecteurDefinitionFiles
    {
        return $this->getInstance(ConnecteurDefinitionFiles::class);
    }

    /**
     * @return FluxEntiteSQL
     */
    public function getFluxEntiteSQL(): FluxEntiteSQL
    {
        return $this->getInstance(FluxEntiteSQL::class);
    }

    /**
     * @return FluxDefinitionFiles
     */
    public function getFluxDefinitionFiles(): FluxDefinitionFiles
    {
        return $this->getInstance(FluxDefinitionFiles::class);
    }

    public function getManifestFactory()
    {
        return $this->getInstance(ManifestFactory::class);
    }

    /**
     * @return Extensions
     */
    public function getExtensions(): Extensions
    {
        return $this->getInstance(Extensions::class);
    }

    /**
     * @return ExtensionSQL
     */
    public function getExtensionSQL(): ExtensionSQL
    {
        return $this->getInstance(ExtensionSQL::class);
    }

    /**
     * @return Monolog\Logger
     */
    public function getLogger(): Logger
    {
        return $this->getInstance(Logger::class);
    }

    public function getSiteBase(): string
    {
        return $this->getObjectInstancier()->getInstance('site_base');
    }

    public function getLoginPageConfiguration(): string
    {
        $result = '';
        if (file_exists(LOGIN_PAGE_CONFIGURATION_LOCATION)) {
            $raw = file_get_contents(LOGIN_PAGE_CONFIGURATION_LOCATION);
            $raw = trim($raw);
            try {
                $result = json_encode(
                    json_decode($raw, true, 512, JSON_THROW_ON_ERROR),
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                );
            } catch (JsonException) {
            }
        }
        return $result;
    }
}
