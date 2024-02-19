<?php

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use org\bovigo\vfs\vfsStream;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Pack\PackService;
use Pastell\Storage\StorageInterface;
use Pastell\Storage\StorageInterfaceFake;
use Pastell\Tests\SymfonyContainerFactory;
use Pastell\Utilities\Identifier\IdentifierGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Pastell\Service\TypeDossier\TypeDossierImportService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Twig\Environment;

define("FIXTURES_PATH", __DIR__ . "/fixtures/");
define("FIXTURES_TYPE_DOSSIER_PATH", __DIR__ . "/pastell-core/type-dossier/fixtures/");

abstract class PastellTestCase extends TestCase
{
    public const ID_E_COL = 1;
    public const ID_E_SERVICE = 2;
    public const ID_U_ADMIN = 1;

    private ObjectInstancier $objectInstancier;
    private InternalAPI $internalAPI;
    private $emulated_disk;

    public static function getSQLQuery()
    {
        static $sqlQuery;
        if (!$sqlQuery) {
            $sqlQuery = new SQLQuery(BD_DSN_TEST, BD_USER_TEST, BD_PASS_TEST);
        }
        return $sqlQuery;
    }

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->objectInstancier = new ObjectInstancier();
        ObjectInstancierFactory::setObjectInstancier($this->objectInstancier);

        $this->objectInstancier->setInstance('site_base', 'https://localhost:8443');
        $this->objectInstancier->setInstance('websec_base', 'https://localhost:8443');

        $this->objectInstancier->setInstance('daemon_command', "/bin/date");
        $this->objectInstancier->setInstance('pid_file', "/tmp/test");
        $this->objectInstancier->setInstance('log_file', "/tmp/test");
        $this->objectInstancier->setInstance(
            'pes_viewer_url',
            $this->getObjectInstancier()->getInstance('site_base')
        );

        $this->objectInstancier->setInstance('pastell_path', PASTELL_PATH);
        $this->objectInstancier->setInstance('data_dir', PASTELL_PATH . '/data');
        $this->objectInstancier->setInstance(SQLQuery::class, self::getSQLQuery());
        $this->objectInstancier->setInstance('template_path', TEMPLATE_PATH);
        $this->objectInstancier->setInstance('email_template_path', __DIR__ . '/../../templates/email');
        $this->objectInstancier->setInstance('plateforme_mail', 'test@libriciel.net');

        $this->objectInstancier->setInstance(MemoryCache::class, new StaticWrapper());
        $this->objectInstancier->setInstance(RedisWrapper::class, $this->createMock(RedisWrapper::class));
        $this->objectInstancier->setInstance('redis_server', '');
        $this->objectInstancier->setInstance('redis_port', 0);
        $this->objectInstancier->setInstance('disable_journal_horodatage', false);

        $this->objectInstancier->setInstance('upload_chunk_directory', UPLOAD_CHUNK_DIRECTORY);

        $this->objectInstancier->setInstance(LockFactory::class, new LockFactory(new InMemoryStore()));

        $this->getObjectInstancier()->setInstance(
            RoleUtilisateur::class,
            new RoleUtilisateur(
                $this->getSQLQuery(),
                $this->getObjectInstancier()->getInstance(RoleSQL::class),
                new MemoryCacheNone(),
                0
            )
        );

        $this->objectInstancier->setInstance(
            ManifestFactory::class,
            new ManifestFactory(__DIR__ . "/fixtures/", new YMLLoader(new MemoryCacheNone()))
        );

        $this->objectInstancier->setInstance('temp_directory', sys_get_temp_dir());
        $this->objectInstancier->setInstance('disable_job_queue', false);
        $this->objectInstancier->setInstance('cache_ttl_in_seconds', 10);
        $this->objectInstancier->setInstance('rgpd_page_path', RGPD_PAGE_PATH);
        $this->objectInstancier->setInstance(IdentifierGeneratorInterface::class, new PasswordGenerator());
        $this->objectInstancier->setInstance(Logger::class, new  Logger('PHPUNIT'));
        $this->objectInstancier->setInstance('log_level', Logger::DEBUG);
        $testHandler = new TestHandler();
        $this->objectInstancier->setInstance(TestHandler::class, $testHandler);
        $this->getObjectInstancier()->getInstance(Logger::class)->pushHandler($testHandler);
        $this->reinitFileSystem();
        $this->objectInstancier->setInstance('use_external_storage_for_journal_proof', false);
        $this->objectInstancier->setInstance(StorageInterface::class, new StorageInterfaceFake());
        $this->getJournal()->setId(1);

        $this->objectInstancier->setInstance('opensslPath', OPENSSL_PATH);
        $this->objectInstancier->setInstance('journal_max_age_in_months', 2);
        $this->objectInstancier->setInstance('admin_email', ['test@libriciel.net']);
        $this->objectInstancier->setInstance('mailer_dsn', 'null://null');

        $this->objectInstancier->setInstance('pastell_admin_login', 'admin');
        $this->objectInstancier->setInstance('pastell_admin_email', 'test@libriciel.net');
        $this->objectInstancier->setInstance('password_min_entropy', 80);

        $this->objectInstancier->setInstance('unlock_job_error_at_startup', false);

        $symfonyContainer = SymfonyContainerFactory::getSymfonyContainer();
        $environment = $symfonyContainer->get('twig');
        $this->objectInstancier->setInstance(
            Environment::class,
            $environment
        );
        $this->setListPack([
            'pack_chorus_pro' => true,
            'pack_marche' => true,
            'suppl_test' => true,
            'pack_libersign' => true,
            'suppl_recup_parapheur_corbeille_connector' => true,
            'suppl_recup_fin_parapheur' => true,
        ]);
    }

    public function getObjectInstancier(): ObjectInstancier
    {
        return $this->objectInstancier;
    }

    public function reinitFileSystem()
    {
        $structure = [
            'workspace' => [
                'connecteur_1.yml' => '---
iparapheur_type: Actes
iparapheur_retour: Archive',

            ],
            'log' => [],
            'tmp' => [],
            'html_purifier' => [],
            'upload_chunk' => [],
        ];
        vfsStream::setup('test', null, $structure);
        $this->emulated_disk = vfsStream::url('test');
        $this->objectInstancier->setInstance('workspacePath', $this->getEmulatedDisk() . "/workspace/");
        $this->objectInstancier->setInstance('upload_chunk_directory', $this->getEmulatedDisk() . '/upload_chunk');
        $htmlPurifier = new HTMLPurifier();
        $htmlPurifier->config->set('Cache.SerializerPath', $this->emulated_disk . "/html_purifier/");
        $this->objectInstancier->setInstance(HTMLPurifier::class, $htmlPurifier);
    }


    public function getEmulatedDisk(): string
    {
        return $this->emulated_disk;
    }

    /**
     * @throws Exception
     */
    public function reinitDatabase()
    {
        $this->getSQLQuery()->query(file_get_contents(__DIR__ . "/pastell_test.sql"));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reinitDatabase();
        $this->reinitFileSystem();
        $_POST = [];
        $_GET = [];
        ObjectInstancierFactory::setObjectInstancier($this->getObjectInstancier());
    }

    protected function getSiteBase(): string
    {
        return $this->objectInstancier->getInstance('site_base');
    }

    /**
     * @return Journal
     */
    protected function getJournal()
    {
        return $this->objectInstancier->getInstance(Journal::class);
    }

    /**
     * @return ConnecteurFactory
     */
    protected function getConnecteurFactory()
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurFactory::class);
    }

    /**
     * @return DonneesFormulaireFactory
     */
    protected function getDonneesFormulaireFactory()
    {
        return $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);
    }

    /**
     * @param $controllerName
     * @param $id_u
     * @return BaseAPIController
     * @throws NotFoundException
     */
    protected function getAPIController($controllerName, $id_u)
    {
        /** @var  BaseAPIControllerFactory $factory */
        $factory = $this->getObjectInstancier()->getInstance(BaseAPIControllerFactory::class);

        if ($id_u) {
            //FIXME : Faudrait pas que ca arrive...
            /** @var Authentification $authentification */
            $authentification = $this->objectInstancier->getInstance(Authentification::class);
            $authentification->connexion('API', $id_u);
        }
        return $factory->getInstance($controllerName, $id_u);
    }

    protected function getInternalAPI()
    {
        return $this->getInternalAPIAsUser(1);
    }

    protected function getInternalAPIAsUser($userId): InternalAPI
    {
        $this->internalAPI = $this->getObjectInstancier()->getInstance(InternalAPI::class);
        $this->internalAPI->setUtilisateurId($userId);
        return $this->internalAPI;
    }

    protected function getV1($ressource)
    {
        $apiAuthetication = $this->createMock(ApiAuthentication::class);
        $apiAuthetication->method("getUtilisateurId")->willReturn(1);
        $this->getObjectInstancier()->setInstance(ApiAuthentication::class, $apiAuthetication);

        /** @var HttpApi $httpAPI */
        $httpAPI = $this->getObjectInstancier()->getInstance(HttpApi::class);

        $path = parse_url($ressource, PHP_URL_PATH);
        $query = parse_url($ressource, PHP_URL_QUERY);
        parse_str($query ?? "", $data_from_query);

        $httpAPI->setServerArray(['REQUEST_METHOD' => 'get']);
        $data_from_query[HttpApi::PARAM_API_FUNCTION] = $path;
        $httpAPI->setGetArray($data_from_query);
        $httpAPI->setRequestArray($data_from_query);
        $httpAPI->dispatch();
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->getObjectInstancier()->getInstance(Logger::class);
    }

    public function getLogRecords()
    {
        return $this->getObjectInstancier()
            ->getInstance(TestHandler::class)
            ->getRecords();
    }

    public function assertLastLog($expected)
    {
        $logs = $this->getLogRecords();
        $this->assertEquals($expected, $logs[count($logs) - 1]['message']);
    }

    /**
     * Creates and returns a document of the type in parameter
     *
     * @param string $type
     * @param int $entite
     * @return array The document
     */
    protected function createDocument($type, $entite = self::ID_E_COL)
    {
        return $this->getInternalAPI()->post("/Document/$entite", [
            'type' => $type,
        ]);
    }

    /**
     * @param $id_document
     * @param array $data
     * @param int $entite
     * @return mixed
     */
    protected function configureDocument($id_document, array $data, $entite = self::ID_E_COL)
    {
        return $this->getInternalAPI()->patch("/entite/$entite/document/$id_document/content/", $data);
    }

    /**
     * Creates and returns a connector
     *
     * @param string $id_connecteur
     * @param string $libelle
     * @param int $entite
     * @return array The connector
     */
    protected function createConnector($id_connecteur, $libelle, $entite = self::ID_E_COL): array
    {
        return $this->getInternalAPI()->post("/entite/$entite/connecteur/", [
            'id_connecteur' => $id_connecteur,
            'libelle' => $libelle,
        ]);
    }

    /**
     * @param $flux
     * @param $id_connecteur
     * @param int $id_e
     * @return mixed
     */
    protected function createConnecteurForTypeDossier($flux, $id_connecteur, $id_e = self::ID_E_COL)
    {
        $connecteur_info = $this->createConnector($id_connecteur, "Connecteur $id_connecteur", $id_e);
        $id_ce = $connecteur_info['id_ce'];
        $type = $this->getObjectInstancier()
            ->getInstance(ConnecteurDefinitionFiles::class)
            ->getInfo($id_connecteur)['type'];
        $this->associateFluxWithConnector($id_ce, $flux, $type);
        return $id_ce;
    }

    /**
     * @param string $filepath
     * @return int
     * @throws TypeDossierException
     */
    protected function copyTypeDossierTest($filepath = FIXTURES_TYPE_DOSSIER_PATH . "cas-nominal.json"): int
    {
        $typeDossierImportService = $this->getObjectInstancier()->getInstance(TypeDossierImportService::class);
        return $typeDossierImportService->importFromFilePath($filepath)['id_t'];
    }

    /**
     * Configures the content of a connector
     *
     * @param int $id_ce The id of the connector
     * @param array $data
     * @param int $entite
     * @return mixed
     */
    protected function configureConnector($id_ce, array $data, $entite = self::ID_E_COL)
    {
        return $this->getInternalAPI()->patch("/entite/$entite/connecteur/$id_ce/content/", $data);
    }

    /**
     * Associates a flux with a connector
     *
     * @param int $id_ce The id of the connector
     * @param string $flux
     * @param string $type
     * @param int $entite
     * @param int $num_same_type
     * @return mixed
     */
    protected function associateFluxWithConnector($id_ce, $flux, $type, $entite = self::ID_E_COL, $num_same_type = 0)
    {
        return $this->getInternalAPI()->post("/entite/$entite/flux/$flux/connecteur/$id_ce", [
            'type' => $type,
            'num_same_type' => $num_same_type,
        ]);
    }

    public function associateGlobalConnector(int $id_ce): void
    {
        $connecteurAssociationService = $this->getObjectInstancier()->getInstance(
            ConnecteurAssociationService::class
        );

        $connecteurAssociationService->addConnecteurAssociation(0, $id_ce);
    }

    /**
     * Triggers an action on a document and returns the success or not of this action
     *
     * @param string $id_d The id of the document
     * @param string $action
     * @param int $entite
     * @param int $user
     * @return bool
     */
    protected function triggerActionOnDocument($id_d, $action, $entite = self::ID_E_COL, $user = self::ID_U_ADMIN)
    {
        return $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->executeOnDocument(
            $entite,
            $user,
            $id_d,
            $action
        );
    }

    /**
     * Triggers an action on a connector and returns the success or not of this action
     *
     * @param int $id_ce The id of the connector
     * @param string $action
     * @param int $user
     * @return bool
     */
    protected function triggerActionOnConnector($id_ce, $action, $user = self::ID_U_ADMIN)
    {
        return $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->executeOnConnecteur(
            $id_ce,
            $user,
            $action
        );
    }

    /**
     * Asserts that this is the last message received
     *
     * @param string $last_message
     */
    protected function assertLastMessage($last_message)
    {
        $this->assertEquals(
            $last_message,
            $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage()
        );
    }

    protected function assertLastDocumentAction($expected_action, $id_d, $id_e = self::ID_E_COL)
    {
        $this->assertEquals(
            $expected_action,
            $this->getObjectInstancier()->getInstance(DocumentActionEntite::class)->getLastAction($id_e, $id_d)
        );
    }

    protected function assertActionPossible(
        array $expected_action_possible,
        string $id_d,
        int $id_e = self::ID_E_COL,
        int $id_u = 0
    ): void {
        $actionPossible = $this->getObjectInstancier()->getInstance(ActionPossible::class);
        $this->assertSame(
            $expected_action_possible,
            $actionPossible->getActionPossible($id_e, $id_u, $id_d)
        );
    }

    /**
     * @param array $list_pack
     */
    public function setListPack(array $list_pack = []): void
    {
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->flushAll();
        $packService = $this->getObjectInstancier()->getInstance(PackService::class);
        $packService->setListPack($list_pack);
    }
}
