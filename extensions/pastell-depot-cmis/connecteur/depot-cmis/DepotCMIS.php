<?php

//Docker : https://hub.docker.com/r/gui81/alfresco/
//A tester : http://jeci.fr/blog/2017/0922-en-alfresco-docker-cloud-201707.html
//composer update

use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Enum\BindingType;
use Dkd\PhpCmis\Enum\VersioningState;
use Dkd\PhpCmis\OperationContext;
use Dkd\PhpCmis\PropertyIds;
use Dkd\PhpCmis\Session;
use Dkd\PhpCmis\SessionFactory;
use Dkd\PhpCmis\SessionParameter;
use GuzzleHttp\Client;
use GuzzleHttp\Stream\Stream;
use Pastell\Configuration\ElementType;

class DepotCMIS extends DepotConnecteur
{
    public const DEPOT_CMIS_URL = 'depot_cmis_url';
    public const DEPOT_CMIS_LOGIN = 'depot_cmis_login';
    public const DEPOT_CMIS_PASSWORD = 'depot_cmis_password';
    public const DEPOT_CMIS_DIRECTORY = 'depot_cmis_directory';
    public const DEPOT_CMIS_OBJECT_TYPE_ID = 'depot_cmis_object_type_id';
    public const DEPOT_CMIS_PROPERTIES = 'depot_cmis_properties';

    /** @var FolderInterface */
    private $folder;

    /** @var  Session */
    private $session;
    private int $errorReporting;

    private string $http_proxy_url;
    private string $no_proxy;

    private Client $client;

    private DonneesFormulaireFactory $donneesFormulaireFactory;

    public function __construct(
        DonneesFormulaireFactory $donneesFormulaireFactory,
        string $http_proxy_url = '',
        string $no_proxy = ''
    ) {
        $this->donneesFormulaireFactory = $donneesFormulaireFactory;
        $this->http_proxy_url = $http_proxy_url;
        $this->no_proxy = $no_proxy;
        $this->disableDeprecated();
        $this->client = new Client();
    }

    /**
     * The version of Guzzle is not compatible with PHP 8.1
     * @return void
     */
    private function disableDeprecated(): void
    {
        $this->errorReporting = error_reporting(error_reporting() & ~E_DEPRECATED);
    }

    private function restoreErrorReporting(): void
    {
        error_reporting($this->errorReporting);
    }

    public function listDirectory()
    {
        $this->disableDeprecated();
        try {
            $result = [];
            foreach ($this->getFolder()->getChildren() as $children) {
                $result[] = $children->getName();
            }
        } finally {
            $this->restoreErrorReporting();
        }

        return $result;
    }

    public function makeDirectory(string $directory_name)
    {
        $this->disableDeprecated();
        try {
            $properties = [
                PropertyIds::OBJECT_TYPE_ID => 'cmis:folder',
                PropertyIds::NAME => $directory_name,

            ];
            $this->getFolder()->createFolder($properties);
        } finally {
            $this->restoreErrorReporting();
        }

        return $directory_name;
    }

    public function saveDocument(string $directory_name, string $filename, string $filepath)
    {
        $this->disableDeprecated();

        try {
            $fileContentType = new FileContentType();
            $properties = [
                PropertyIds::OBJECT_TYPE_ID
                    => $this->connecteurConfig->get(self::DEPOT_CMIS_OBJECT_TYPE_ID) ?: 'cmis:document',
                PropertyIds::NAME => $filename,
                PropertyIds::CONTENT_STREAM_MIME_TYPE => $fileContentType->getContentType($filepath),
            ];

            $properties  += $this->getProperties();

            $versionningState = new VersioningState(VersioningState::MAJOR);

            $folder = $this->getFolder();
            if ($directory_name) {
                $folder = $this->session->getObjectByPath(
                    $this->connecteurConfig->get(self::DEPOT_CMIS_DIRECTORY) . "/" . $directory_name
                );
            }

            $document = $folder->createDocument(
                $properties,
                Stream::factory(fopen($filepath, 'r')),
                $versionningState,
                [],
                [],
                [],
                new OperationContext()
            );

            $this->addGedDocumentId($filename, $document->getId());
        } finally {
            $this->restoreErrorReporting();
        }
        return $directory_name . "/" . $filename;
    }

    private function getProperties(): array
    {
        if ($this->hasDocDonneesFormulaire()) {
            $docDonneesFormulaire = $this->getDocDonneesFormulaire();
        } else {
            $docDonneesFormulaire = $this->donneesFormulaireFactory->getNonPersistingDonneesFormulaire();
        }

        $properties = [];
        $expressionPerField = $this->getExpressionsPerField(self::DEPOT_CMIS_PROPERTIES);
        foreach ($expressionPerField as $propertie_name => $propertie_info) {
            $propertie_value = $this->getNameFromMetadata($docDonneesFormulaire, $propertie_info['expression']);
            if ($propertie_info['type'] === 'datetime') {
                $propertie_value = new DateTime($propertie_value);
            }
            $properties[$propertie_name] = $propertie_value;
        }
        return $properties;
    }

    private function getExpressionsPerField(string $connecteur_element_id): array
    {
        $expressionPerField = [];
        foreach (explode("\n", $this->connecteurConfig->get($connecteur_element_id)) as $line) {
            preg_match('#"([^"]*)":([^:]*):(.*)#', $line, $matches);
            if (count($matches) < 2) {
                continue;
            }
            $expressionPerField[trim($matches[1])] = [
                'expression' => trim($matches[3]),
                'type' => trim($matches[2])
            ];
        }
        return $expressionPerField;
    }

    private function getNameFromMetadata(DonneesFormulaire $donneesFormulaire, $expression)
    {
        return preg_replace_callback(
            "#%([^%]*)%#",
            function ($matches) use ($donneesFormulaire) {
                $field = $donneesFormulaire->getFormulaire()->getField($matches[1]);
                if ($field && $field->isFile()) {
                    return pathinfo($donneesFormulaire->getFileName($matches[1]), PATHINFO_FILENAME);
                }
                return $donneesFormulaire->get($matches[1]);
            },
            $expression
        );
    }

    private function itemExists(string $item_name)
    {
        return array_reduce(
            $this->listDirectory(),
            function ($carry, $item) use ($item_name) {
                $carry = $carry || basename($item) == $item_name;
                return $carry;
            }
        );
    }

    public function directoryExists(string $directory_name)
    {
        return $this->itemExists($directory_name);
    }

    public function fileExists(string $filename)
    {
        return $this->itemExists($filename);
    }

    private function getFolder()
    {
        if ($this->folder) {
            return $this->folder;
        }
        $url = $this->connecteurConfig->get(self::DEPOT_CMIS_URL);

        $httpInvoker = $this->getClient();

        $httpInvoker->setDefaultOption(
            'auth',
            [
                $this->connecteurConfig->get(self::DEPOT_CMIS_LOGIN),
                $this->connecteurConfig->get(self::DEPOT_CMIS_PASSWORD),
            ]
        );

        $proxyNeeded = new ProxyNeeded($this->http_proxy_url, $this->no_proxy);
        if ($proxyNeeded->isNeeded($url)) {
            $httpInvoker->setDefaultOption('proxy', $this->http_proxy_url);
        }

        $parameters = [
            SessionParameter::BINDING_TYPE => BindingType::BROWSER,
            SessionParameter::BROWSER_URL => $url,
            SessionParameter::BROWSER_SUCCINCT => false,
            SessionParameter::HTTP_INVOKER_OBJECT => $httpInvoker
        ];
        $sessionFactory = new SessionFactory();

        $repositories = $sessionFactory->getRepositories($parameters);
        $parameters[SessionParameter::REPOSITORY_ID] = $repositories[0]->getId();
        $this->session = $sessionFactory->createSession($parameters);
        /** @var FolderInterface $folder */
        $this->folder = $this->session->getObjectByPath($this->connecteurConfig->get(self::DEPOT_CMIS_DIRECTORY));
        return $this->folder;
    }

    /*
     * Only used for testing
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
