<?php

use Pastell\Configuration\ConnectorValidation;
use Pastell\Configuration\DocumentTypeValidation;
use Pastell\Mailer\Mailer;
use Pastell\Service\Connecteur\MissingConnecteurService;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\FeatureToggle\DisplayFeatureToggleInTestPage;
use Pastell\Service\FeatureToggleService;
use Pastell\Service\Pack\PackService;
use Pastell\System\HealthCheck;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;

class SystemControler extends PastellControler
{
    private const SYSTEM_INDEX_PAGE = "System/index";

    public function _beforeAction()
    {
        parent::_beforeAction();
        $this->setViewParameter('menu_gauche_template', "ConfigurationMenuGauche");
        $this->verifDroit(0, DroitService::getDroitLecture(DroitService::DROIT_SYSTEM));
        $this->setViewParameter('dont_display_breacrumbs', true);
    }

    private function needDroitEdition()
    {
        $this->verifDroit(0, DroitService::getDroitEdition(DroitService::DROIT_SYSTEM));
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function indexAction(): void
    {
        $this->setViewParameter(
            'droitEdition',
            $this->hasDroit(0, DroitService::getDroitEdition(DroitService::DROIT_SYSTEM))
        );

        /** @var HealthCheck $healthCheck */
        $healthCheck = $this->getInstance(HealthCheck::class);
        $this->setViewParameter('checkWorkspace', $healthCheck->check(HealthCheck::WORKSPACE_CHECK));
        $this->setViewParameter('checkJournal', $healthCheck->check(HealthCheck::JOURNAL_CHECK));
        $this->setViewParameter('checkRedis', $healthCheck->check(HealthCheck::REDIS_CHECK));
        $this->setViewParameter('checkPhpConfiguration', $healthCheck->check(HealthCheck::PHP_CONFIGURATION_CHECK));
        $this->setViewParameter('checkPhpExtensions', $healthCheck->check(HealthCheck::PHP_EXTENSIONS_CHECK));
        $this->setViewParameter('checkExpectedElements', $healthCheck->check(HealthCheck::EXPECTED_ELEMENTS_CHECK));
        $this->setViewParameter('checkCommands', $healthCheck->check(HealthCheck::COMMAND_CHECK));
        $this->setViewParameter('checkConstants', $healthCheck->check(HealthCheck::CONSTANTS_CHECK));
        $this->setViewParameter('checkDatetime', $healthCheck->check(HealthCheck::DATETIME_CHECK));
        $this->setViewParameter('checkDatabaseSchema', $healthCheck->check(HealthCheck::DATABASE_SCHEMA_CHECK)[0]);
        $this->setViewParameter('checkDatabaseEncoding', $healthCheck->check(HealthCheck::DATABASE_ENCODING_CHECK)[0]);
        $this->setViewParameter('checkCrashedTables', $healthCheck->check(HealthCheck::CRASHED_TABLES_CHECK)[0]);
        $this->setViewParameter('checkDaemon', $healthCheck->check(HealthCheck::DAEMON_CHECK)[0]);
        $this->setViewParameter(
            'checkMissingConnectors',
            $healthCheck->check(HealthCheck::MISSING_CONNECTORS_CHECK)[0]
        );
        $this->setViewParameter('checkMissingModules', $healthCheck->check(HealthCheck::MISSING_MODULES_CHECK)[0]);

        $packService = $this->getInstance(PackService::class);
        $this->setViewParameter('listPack', $packService->getListPack());

        $this->setViewParameter('manifest_info', $this->getManifestFactory()->getPastellManifest()->getInfo());

        $this->setViewParameter('feature_toggle', $this->getObjectInstancier()
            ->getInstance(FeatureToggleService::class)
            ->getAllOptionalFeatures());
        $this->setViewParameter('display_feature_toggle_in_test_page', $this->getObjectInstancier()
            ->getInstance(FeatureToggleService::class)
            ->isEnabled(DisplayFeatureToggleInTestPage::class));
        $this->setViewParameter('admin_email', implode(', ', $this->getConfigurationSQL()->getAdminEmails()));
        $this->setViewParameter(
            'libelle_plateforme_mail',
            $this->getConfigurationSQL()->getLibellePlateformeMail()
        );
        $this->setViewParameter('page_title', 'Test du système');
        $this->setViewParameter('menu_gauche_select', self::SYSTEM_INDEX_PAGE);
        $this->setViewParameter('twigTemplate', 'system/index.html.twig');
        $this->renderDefault();
    }

    public function getPageNumber($page_name): int
    {
        $tab_number = [
        "system" => 0,
                                "flux" => 1,
                                "definition" => 2,
                                "connecteurs" => 4
        ];
        return $tab_number[$page_name];
    }

    /**
     * @throws NotFoundException
     */
    public function fluxAction()
    {
        $all_flux = [];
        $all_flux_restricted = [];

        foreach ($this->getFluxDefinitionFiles()->getAll() as $id_flux => $flux) {
            $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($id_flux);
            $all_flux[$id_flux]['nom'] = $documentType->getName();
            $all_flux[$id_flux]['type'] = $documentType->getType();
            $all_flux[$id_flux]['list_restriction_pack'] = $documentType->getListRestrictionPack();
            $definition_path = $this->getFluxDefinitionFiles()->getDefinitionPath($id_flux);
            $all_flux[$id_flux]['is_valide'] =
                $this->getDocumentTypeValidation()->isDefinitionFileValid($definition_path);
        }
        $this->setViewParameter('all_flux', $all_flux);

        foreach ($this->getFluxDefinitionFiles()->getAllRestricted() as $id_flux) {
            $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($id_flux);
            $all_flux_restricted[$id_flux]['nom'] = $documentType->getName();
            $all_flux_restricted[$id_flux]['type'] = $documentType->getType();
            $all_flux_restricted[$id_flux]['list_restriction_pack'] = $documentType->getListRestrictionPack();
        }
        $this->setViewParameter('all_flux_restricted', $all_flux_restricted);

        $this->setViewParameter('template_milieu', "SystemFlux");
        $this->setViewParameter('page_title', "Types de dossier disponibles sur la plateforme");
        $this->setViewParameter('menu_gauche_select', "System/flux");
        $this->renderDefault();
    }


    private function getDocumentTypeValidation(): DocumentTypeValidation
    {
        return $this->getObjectInstancier()->getInstance(DocumentTypeValidation::class);
    }

    private function getAllActionInfo(DocumentType $documentType): array
    {
        $all_action = [];
        $action = $documentType->getAction();
        $action_list = $action->getAll();
        sort($action_list);
        foreach ($action_list as $action_name) {
            $class_name = $action->getActionClass($action_name);
            $element = [
                'id' => $action_name,
                'name' => $action->getActionName($action_name),
                'do_name' => $action->getDoActionName($action_name),
                'class' => $class_name,
                'action_auto' => $action->getActionAutomatique($action_name),
                'path' => $this->getClassLocation($class_name),
            ];

            $all_action[] = $element;
        }
        return $all_action;
    }

    private function getFormsElement(DocumentType $documentType): array
    {
        $formulaire = $documentType->getFormulaire();

        $allFields = $formulaire->getAllFields();
        $form_fields = [];
        foreach ($allFields as $field) {
            $form_fields[$field->getName()] = $field->getAllProperties();
        }
        return $form_fields;
    }

    /**
     * @throws NotFoundException
     */
    public function fluxDetailAction()
    {
        $id = $this->getGetInfo()->get('id');
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($id);
        $name = $documentType->getName();
        $this->setViewParameter('description', $documentType->getDescription());
        $this->setViewParameter('list_restriction_pack', $documentType->getListRestrictionPack());
        $this->setViewParameter('all_connecteur', $documentType->getConnecteur());

        $this->setViewParameter('all_action', $this->getAllActionInfo($documentType));

        $this->setViewParameter('formulaire_fields', $this->getFormsElement($documentType));

        $document_type_is_validate = false;
        $validation_error = false;
        try {
            $document_type_is_validate = $this->isDocumentTypeValid($id);
        } catch (Exception $e) {
            $definitionFile = $this->getFluxDefinitionFiles()->getDefinitionPath($id);
            $validation_error = $this->getDocumentTypeValidation()->getErrorList($definitionFile);
        }

        $this->setViewParameter('document_type_is_validate', $document_type_is_validate);
        $this->setViewParameter('validation_error', $validation_error);

        $this->setViewParameter('page_title', "Détail du type de dossier « $name » ($id)");
        $this->setViewParameter('template_milieu', 'SystemFluxDetail');
        $this->setViewParameter('menu_gauche_select', 'System/flux');

        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     */
    public function definitionAction()
    {
        $this->setViewParameter('flux_definition', $this->getDocumentTypeValidation()->getModuleDefinition());
        $this->setViewParameter('page_title', 'Définition des types de dossier');
        $this->setViewParameter('template_milieu', 'SystemFluxDef');
        $this->setViewParameter('menu_gauche_select', 'System/definition');
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     */
    public function connecteurAction()
    {
        $all_connecteur_globaux = [];
        $all_connecteur_globaux_restricted = [];
        $all_connecteur_entite = [];
        $all_connecteur_entite_restricted = [];

        $connectorValidation = $this->getObjectInstancier()->getInstance(ConnectorValidation::class);

        foreach ($this->getConnecteurDefinitionFiles()->getAllGlobal() as $id_connecteur => $connecteur) {
            $documentType = $this->getDocumentTypeFactory()->getGlobalDocumentType($id_connecteur);
            $all_connecteur_globaux[$id_connecteur]['nom'] = $documentType->getName();
            $all_connecteur_globaux[$id_connecteur]['description'] = $documentType->getDescription();
            $all_connecteur_globaux[$id_connecteur]['list_restriction_pack'] = $documentType->getListRestrictionPack();
            $definitionFile = $this->getConnecteurDefinitionFiles()->getDefinitionPath($id_connecteur, true);
            $all_connecteur_globaux[$id_connecteur]['is_valid'] =
                $connectorValidation->isDefinitionFileValid($definitionFile);
        }
        $this->setViewParameter('all_connecteur_globaux', $all_connecteur_globaux);

        foreach ($this->getConnecteurDefinitionFiles()->getAll() as $id_connecteur => $connecteur) {
            $documentType = $this->getDocumentTypeFactory()->getEntiteDocumentType($id_connecteur);
            $all_connecteur_entite[$id_connecteur]['nom'] = $documentType->getName();
            $all_connecteur_entite[$id_connecteur]['description'] = $documentType->getDescription();
            $all_connecteur_entite[$id_connecteur]['list_restriction_pack'] = $documentType->getListRestrictionPack();
            $definitionFile = $this->getConnecteurDefinitionFiles()->getDefinitionPath($id_connecteur, false);
            $all_connecteur_entite[$id_connecteur]['is_valid'] =
                $connectorValidation->isDefinitionFileValid($definitionFile);
        }
        $this->setViewParameter('all_connecteur_entite', $all_connecteur_entite);

        foreach ($this->getConnecteurDefinitionFiles()->getAllRestricted(true) as $id_connecteur) {
            $documentType = $this->getDocumentTypeFactory()->getGlobalDocumentType($id_connecteur);
            $all_connecteur_globaux_restricted[$id_connecteur]['nom'] = $documentType->getName();
            $all_connecteur_globaux_restricted[$id_connecteur]['list_restriction_pack'] = $documentType->getListRestrictionPack();
        }
        $this->setViewParameter('all_connecteur_globaux_restricted', $all_connecteur_globaux_restricted);

        foreach ($this->getConnecteurDefinitionFiles()->getAllRestricted() as $id_connecteur) {
            $documentType = $this->getDocumentTypeFactory()->getEntiteDocumentType($id_connecteur);
            $all_connecteur_entite_restricted[$id_connecteur]['nom'] = $documentType->getName();
            $all_connecteur_entite_restricted[$id_connecteur]['list_restriction_pack'] = $documentType->getListRestrictionPack();
        }
        $this->setViewParameter('all_connecteur_entite_restricted', $all_connecteur_entite_restricted);

        $this->setViewParameter('page_title', "Connecteurs disponibles");
        $this->setViewParameter('template_milieu', "SystemConnecteurList");
        $this->setViewParameter('menu_gauche_select', "System/connecteur");
        $this->renderDefault();
    }


    /**
     * @param $id_flux
     * @return bool
     * @throws Exception
     */
    public function isDocumentTypeValid($id_flux): bool
    {
        $definition_path = $this->getFluxDefinitionFiles()->getDefinitionPath($id_flux);
        return $this->isDocumentTypeValidByDefinitionPath($definition_path);
    }

    /**
     * @param $definition_path
     * @return bool
     * @throws Exception
     */
    public function isDocumentTypeValidByDefinitionPath($definition_path): bool
    {
        $documentTypeValidation = $this->getDocumentTypeValidation();
        if (! $documentTypeValidation->isDefinitionFileValid($definition_path)) {
            throw new UnrecoverableException(implode("\n", $documentTypeValidation->getErrorList($definition_path))) ;
        }
        return true;
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function mailTestAction(): void
    {
        $this->verifDroit(0, DroitService::getDroitLecture(DroitService::DROIT_SYSTEM));
        $emails = $this->getPostInfo()->get('email');
        if (! $emails) {
            $this->setLastError('Merci de spécifier un email');
            $this->redirect(self::SYSTEM_INDEX_PAGE);
        }
        $emailSent = '';
        $emailNotSent = '';
        $emails = \explode(',', $emails);
        foreach ($emails as $email) {
            $plateforme_mail = $this->getInstance('plateforme_mail');
            $libelle_plateforme_mail = $this->getConfigurationSQL()->getLibellePlateformeMail();
            $templatedEmail = new TemplatedEmail()
                ->from(new Address($plateforme_mail, $libelle_plateforme_mail))
                ->to(new Address($email))
                ->subject('[Pastell] Mail de test')
                ->htmlTemplate('test_system.html.twig')
                ->context(['SITE_BASE' => $this->getSiteBase()])
                ->attachFromPath(
                    $this->getInstance('data_dir') . '/connector/iparapheur/test.pdf'
                );
            try {
                $pastellMailer = $this->getObjectInstancier()->getInstance(Mailer::class);
                $pastellMailer->send($templatedEmail);
                $emailSent .= "Un email a été envoyé à l'adresse : " . get_hecho($email) . '<br>';
            } catch (TransportExceptionInterface $e) {
                $emailNotSent .= "Impossible d'envoyer le mail : " . $e->getMessage() . '<br>';
            }
        }
        if ($emailSent != '') {
            $this->setLastMessage($emailSent);
        }
        if ($emailNotSent != '') {
            $this->setLastError($emailNotSent);
        }
        $this->redirect(self::SYSTEM_INDEX_PAGE);
    }

    public function phpinfoAction(): void
    {
        $this->needDroitEdition();
        phpinfo();
    }

    /**
     * @throws Exception
     */
    public function connecteurDetailAction(): void
    {
        $id_connecteur = $this->getGetInfo()->get('id_connecteur');
        $scope = $this->getGetInfo()->get('scope');

        $connectorValidation = $this->getObjectInstancier()->getInstance(ConnectorValidation::class);

        if ($scope === 'global') {
            $documentType = $this->getDocumentTypeFactory()->getGlobalDocumentType($id_connecteur);
            $definitionFile = $this->getConnecteurDefinitionFiles()->getDefinitionPath($id_connecteur, true);
        } else {
            $documentType = $this->getDocumentTypeFactory()->getEntiteDocumentType($id_connecteur);
            $definitionFile = $this->getConnecteurDefinitionFiles()->getDefinitionPath($id_connecteur, false);
        }

        $name = $documentType->getName();
        $this->setViewParameter('description', $documentType->getDescription());
        $this->setViewParameter('list_restriction_pack', $documentType->getListRestrictionPack());
        $this->setViewParameter('all_action', $this->getAllActionInfo($documentType));
        $this->setViewParameter('formulaire_fields', $this->getFormsElement($documentType));

        $this->setViewParameter('isConnectorValid', $connectorValidation->isDefinitionFileValid($definitionFile));
        $this->setViewParameter('connectorError', $connectorValidation->getError($definitionFile));

        $this->setViewParameter(
            'page_title',
            "Détail du connecteur " . ($scope === 'global' ? 'global' : "d'entité") . " « $name » ($id_connecteur)"
        );
        $this->setViewParameter('menu_gauche_select', "System/connecteur");
        $this->setViewParameter('template_milieu', "SystemConnecteurDetail");
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function sendWarningAction()
    {
        $this->getLogger()->warning("Warning emis par System/Warning");
        $this->setLastMessage("Un warning a été généré");
        $this->redirect(self::SYSTEM_INDEX_PAGE);
    }

    public function sendFatalErrorAction()
    {
        trigger_error("Déclenchement manuel d'une erreur fatale !", E_USER_ERROR);
    }


    /**
     * @throws NotFoundException
     */
    public function loginPageConfigurationAction()
    {
        $this->setViewParameter('login_page_configuration', $this->getLoginPageConfiguration());
        $this->setViewParameter('page_title', '');
        $this->setViewParameter('menu_gauche_select', 'System/loginPageConfiguration');
        $this->setViewParameter('template_milieu', 'LoginPageConfiguration');
        $this->renderDefault();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function doLoginPageConfigurationAction()
    {
        $this->needDroitEdition();

        $result = file_put_contents(
            LOGIN_PAGE_CONFIGURATION_LOCATION,
            $this->getPostInfo()->get(LOGIN_PAGE_CONFIGURATION)
        );

        if ($result === false) {
            $this->setLastError("Impossible d'enregistrer la configuration de la page de connexion");
        } else {
            $this->setLastMessage('La configuration de la page de connexion a été enregistrée');
        }

        $this->redirect('System/loginPageConfiguration');
    }

    /**
     * @throws NotFoundException
     */
    public function missingConnecteurAction()
    {
        $this->setViewParameter('page_title', 'Connecteurs manquants');
        $this->setViewParameter('template_milieu', 'SystemMissingConnecteur');
        $this->setViewParameter('menu_gauche_select', self::SYSTEM_INDEX_PAGE);

        $detail_manquant_list = [];
        $connecteur_manquant_list = $this->getConnecteurFactory()->getManquant();
        foreach ($connecteur_manquant_list as $id_connecteur) {
            $id_ce_list = $this->getConnecteurEntiteSQL()->getAllById($id_connecteur);
            foreach ($id_ce_list as $connecteur_info) {
                $detail_manquant_list[$id_connecteur][] = $connecteur_info;
            }
        }
        $this->setViewParameter('connecteur_manquant_list', $detail_manquant_list);

        $this->renderDefault();
    }

    /**
     * @throws Exception
     */
    public function exportAllMissingConnecteurAction()
    {
        $this->needDroitEdition();

        $tmpFoder  = new TmpFolder();
        $tmp_folder = $tmpFoder->create();
        $zip_filepath = "$tmp_folder/pastell-all-missing-connecteur.zip";
        $this->getObjectInstancier()->getInstance(MissingConnecteurService::class)->exportAll($zip_filepath);
        $sendFileToBrowser = $this->getObjectInstancier()->getInstance(SendFileToBrowser::class);
        $sendFileToBrowser->sendData(
            file_get_contents($zip_filepath),
            "pastell-all-missing-connecteur.zip",
            "application/zip"
        );
        $tmpFoder->delete($tmp_folder);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function emptyCacheAction(): void
    {
        $this->needDroitEdition();

        $redisWrapper = $this->getObjectInstancier()->getInstance(RedisWrapper::class);
        $redisWrapper->flushAll();
        $this->setLastMessage("Le cache Redis a été vidé");
        $this->redirect(self::SYSTEM_INDEX_PAGE);
    }

    private function getClassLocation(string $className): string
    {
        if ($className === '') {
            return '';
        }
        if (class_exists($className)) {
            if (is_subclass_of($className, ActionExecutor::class)) {
                $message = (new ReflectionClass($className))->getFileName();
            } else {
                $message = $className . ' does not extends ' . ActionExecutor::class;
            }
        } else {
            $message = 'Unable to find class : ' . $className;
        }

        return $message;
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function editAdminEmailAction(): void
    {
        $this->verifDroit(0, DroitService::getDroitEdition(DroitService::DROIT_SYSTEM));
        $this->setViewParameter('page_title', 'Modification de la configuration ADMIN_EMAIL');
        $this->setViewParameter('template_milieu', 'SystemEditAdminEmail');
        $this->setViewParameter('menu_gauche_select', self::SYSTEM_INDEX_PAGE);
        $this->setViewParameter('admin_email', implode(', ', $this->getConfigurationSQL()->getAdminEmails()));
        $this->renderDefault();
    }


    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doEditAdminEmailAction(): void
    {
        $this->verifDroit(0, DroitService::getDroitEdition(DroitService::DROIT_SYSTEM));
        $admin_email = array_map('trim', explode(',', $this->getPostInfo()->get('admin_email')));
        try {
            $this->getObjectInstancier()->getInstance(ConfigurationSQL::class)->setAdminEmails($admin_email);
        } catch (InvalidArgumentException $e) {
            $this->setLastError($e->getMessage());
            $this->redirect('System/editAdminEmail');
        }
        $this->setLastMessage("L'adresse email d'administration a été modifiée");
        $this->redirect(self::SYSTEM_INDEX_PAGE);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function editLibellePlateformeMailAction(): void
    {
        $this->verifDroit(0, DroitService::getDroitEdition(DroitService::DROIT_SYSTEM));
        $this->setViewParameter('page_title', 'Modification de la configuration LIBELLE_PLATEFORME_MAIL');
        $this->setViewParameter('template_milieu', 'SystemEditLibellePlateformeEmail');
        $this->setViewParameter('menu_gauche_select', self::SYSTEM_INDEX_PAGE);
        $this->setViewParameter('libelle_plateforme_mail', $this->getConfigurationSQL()->getLibellePlateformeMail());
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doEditLibellePlateformeMailAction(): void
    {
        $this->verifDroit(0, DroitService::getDroitEdition(DroitService::DROIT_SYSTEM));
        $libelle_plateforme_mail = $this->getPostInfo()->get('libelle_plateforme_mail');
        $this->getConfigurationSQL()->setConfiguration(
            ConfigurationSQL::LIBELLE_PLATEFORME_MAIL,
            $libelle_plateforme_mail,
            ConfigurationSQL::NULL_ID_E
        );
        $this->setLastMessage('Le libellé de la plateforme mail a été modifié');
        $this->redirect(self::SYSTEM_INDEX_PAGE);
    }
}
