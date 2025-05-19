<?php

use Dkd\PhpCmis\Data\FolderInterface;
use Dkd\PhpCmis\Enum\BindingType;
use Dkd\PhpCmis\Enum\VersioningState;
use Dkd\PhpCmis\OperationContext;
use Dkd\PhpCmis\PropertyIds;
use Dkd\PhpCmis\Session;
use Dkd\PhpCmis\SessionFactory;
use Dkd\PhpCmis\SessionParameter;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;

class DepotCMIS extends DepotConnecteur
{
    public const DEPOT_CMIS_URL = 'depot_cmis_url';
    public const DEPOT_CMIS_LOGIN = 'depot_cmis_login';
    public const DEPOT_CMIS_PASSWORD = 'depot_cmis_password';
    public const DEPOT_CMIS_DIRECTORY = 'depot_cmis_directory';
    public const DEPOT_CMIS_OBJECT_TYPE_ID = 'depot_cmis_object_type_id';
    public const DEPOT_CMIS_PROPERTIES = 'depot_cmis_properties';

    private FolderInterface $folder;
    private Session $session;

    private Client $client;

    public function __construct(
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly string $http_proxy_url = '',
        private readonly string $no_proxy = ''
    ) {
        $this->client = new Client();
    }

    public function listDirectory()
    {
        $result = [];
        foreach ($this->getFolder()->getChildren() as $children) {
            $result[] = $children->getName();
        }

        return $result;
    }

    public function makeDirectory(string $directory_name)
    {
        $properties = [
        PropertyIds::OBJECT_TYPE_ID => 'cmis:folder',
        PropertyIds::NAME => $directory_name,

        ];

        $this->getFolder()->createFolder($properties);

        return $directory_name;
    }

    public function saveDocument(string $directory_name, string $filename, string $filepath)
    {
        $fileContentType = new FileContentType();
        $properties = [
            PropertyIds::OBJECT_TYPE_ID
            => $this->connecteurConfig->get(self::DEPOT_CMIS_OBJECT_TYPE_ID) ?: 'cmis:document',
            PropertyIds::NAME => $filename,
            PropertyIds::CONTENT_STREAM_MIME_TYPE => $fileContentType->getContentType($filepath),
        ];

        $properties += $this->getProperties();

        $versionningState = new VersioningState(VersioningState::MAJOR);

        $folder = $this->getFolder();
        if ($directory_name) {
            $folder = $this->session->getObjectByPath(
                $this->connecteurConfig->get(self::DEPOT_CMIS_DIRECTORY) . "/" . $directory_name
            );
        }

        $document = $folder->createDocument(
            $properties,
            Utils::streamFor(Utils::tryFopen($filepath, 'rb')),
            $versionningState,
            [],
            [],
            [],
            new OperationContext()
        );

        $this->addGedDocumentId($filename, $document->getId());

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
        if (isset($this->folder)) {
            return $this->folder;
        }
        $url = $this->connecteurConfig->get(self::DEPOT_CMIS_URL);

        $options = [
            'auth' => [
                $this->connecteurConfig->get(self::DEPOT_CMIS_LOGIN),
                $this->connecteurConfig->get(self::DEPOT_CMIS_PASSWORD),
            ],
        ];

        $proxyNeeded = new ProxyNeeded($this->http_proxy_url, $this->no_proxy);
        if ($proxyNeeded->isNeeded($url)) {
            $options['proxy'] = $this->http_proxy_url;
        }

        $httpInvoker = new Client($options);

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
        $this->folder = $this->session->getObjectByPath($this->connecteurConfig->get(self::DEPOT_CMIS_DIRECTORY));
        return $this->folder;
    }
}
