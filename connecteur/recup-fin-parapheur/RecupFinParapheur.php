<?php

declare(strict_types=1);

use Libriciel\IparapheurV5\Client\Api\DeskApi;
use Libriciel\IparapheurV5\Client\Api\FolderApi;
use Libriciel\IparapheurV5\Client\Api\TenantApi;
use Libriciel\IparapheurV5\Client\Configuration;
use Libriciel\IparapheurV5\Client\Model\State;
use Pastell\Action\TestConnectionInterface;
use Pastell\Client\IparapheurV5\IparapheurAuthConfig;
use Pastell\Client\IparapheurV5\ApiClientFactory;
use Pastell\Client\IparapheurV5\ZipContent;
use Pastell\Connector\IparapheurRest\IpRestDeskInterface;
use Pastell\Connector\IparapheurRest\IpRestException;
use Pastell\Connector\IparapheurRest\IpRestTenantInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

class RecupFinParapheur extends Connecteur implements
    IpRestTenantInterface,
    IpRestDeskInterface,
    TestConnectionInterface
{
    private const USERNAME = 'username';
    private const PASSWORD = 'password';
    private const URL = 'url';
    private const TENANT_ID = 'tenant_id';
    private const DESK_ID = 'desk_id';
    private const NB_RECUP = 'nb_recup';
    private array $elementIdDictionnary;
    private DonneesFormulaire $connecteurConfig;
    private ClientInterface $client;
    private Configuration $configuration;

    public function __construct(
        private readonly GlaneurDocumentCreator $glaneurDocumentCreator,
        private readonly ApiClientFactory $apiClientFactory,
    ) {
    }

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $this->connecteurConfig = $donneesFormulaire;

        $pastell_dictionnary = $this->connecteurConfig->get('pastell_dictionnary', '');

        $this->elementIdDictionnary = [
            'dossier_id' => 'dossier_id',
            'dossier_name' => 'dossier_name',
            'document_signe' => 'document_signe',
            'annexe' => 'annexe',
            'bordereau' => 'bordereau',
            'premis' => 'premis'
        ];
        foreach (explode("\n", $pastell_dictionnary) as $line) {
            $part = explode(':', $line, 2);
            if (!isset($part[1])) {
                continue;
            }
            if (!isset($this->elementIdDictionnary[trim($part[0])])) {
                continue;
            }
            $this->elementIdDictionnary[trim($part[0])] = trim($part[1]);
        }

        $iparapheurAuthConfig = new IparapheurAuthConfig(
            $donneesFormulaire->get(self::USERNAME) ?: '',
            $donneesFormulaire->get(self::PASSWORD) ?: '',
            $donneesFormulaire->get(self::URL) ?: '',
        );
        [$httpClient, $config] = $this->apiClientFactory->createAuthenticatedClient($iparapheurAuthConfig);
        $this->client = $httpClient;
        $this->configuration = $config;
    }

    public function getTenantList(): array
    {
        $tenants = [];
        $page = 0;

        do {
            $result = (new TenantApi($this->client, $this->configuration))->listTenants($page);

            foreach ($result->getContent() as $tenant) {
                $tenants[$tenant->getId()] = $tenant->getName();
            }

            $pageable = $result->getPageable();
            $currentPage = $pageable ? $pageable->getPageNumber() : $page;
            $totalPages = $result->getTotalPages() ?? 1;

            $page++;
        } while ($currentPage + 1 < $totalPages);

        return $tenants;
    }

    public function testConnexion(): string
    {
        $result = $this->getTenantList();
        if (!$result) {
            return "Connexion réussie, mais aucune entité n'est associée à ce compte";
        }
        return 'Liste des entités parapheurs : ' . implode(', ', $result);
    }

    public function getFinishedFolders(): array
    {
        $folders = [];
        $result = (new FolderApi($this->client, $this->configuration))->listFolders(
            $this->connecteurConfig->get(self::TENANT_ID, ''),
            $this->connecteurConfig->get(self::DESK_ID, ''),
            /** @phpstan-ignore-next-line */
            State::FINISHED,
            null,
            null,
            0,
            (int)$this->connecteurConfig->get(self::NB_RECUP)
        );
        foreach ($result->getContent() as $folder) {
            $folders[$folder->getId()] = $folder->getName();
        }
        return $folders;
    }

    public function removeFolder(string $folder_id): void
    {
        (new FolderApi($this->client, $this->configuration))->deleteFolder(
            $this->connecteurConfig->get(self::TENANT_ID, ''),
            $this->connecteurConfig->get(self::DESK_ID, ''),
            $folder_id
        );
    }


    /**
     * @throws Exception
     */
    public function recupOne(): array
    {
        $finishedFolders = $this->getFinishedFolders();
        $id_d = [];
        foreach ($finishedFolders as $dossierId => $dossierName) {
            $id_d[] = $this->retrieveOneDossier($dossierId);
        }
        return $id_d;
    }

    /**
     * @throws Exception
     */
    private function retrieveOneDossier(string $dossierId): string
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        try {
            $zipData = (new FolderApi($this->client, $this->configuration))->downloadFolderZip(
                $this->connecteurConfig->get(self::TENANT_ID, ''),
                self::DESK_ID,
                $dossierId
            );

            $zipFilePath = $tmp_folder . '/result.zip';
            file_put_contents($zipFilePath, $zipData);

            $zipContent = new ZipContent();
            $zipContentModel = $zipContent->extract($zipFilePath, $tmp_folder);
            $glaneurLocalDocumentInfo = new GlaneurDocumentInfo($this->getConnecteurInfo()['id_e']);
            $glaneurLocalDocumentInfo->nom_flux = $this->connecteurConfig->get('pastell_module_id');
            $glaneurLocalDocumentInfo->metadata = [
                $this->getElementId('dossier_id') => $zipContentModel->id,
                $this->getElementId('dossier_name') => $zipContentModel->name,
            ];
            $glaneurLocalDocumentInfo->element_files_association[$this->getElementId('document_signe')] =
                $zipContentModel->documentPrincipaux;
            $glaneurLocalDocumentInfo->element_files_association[$this->getElementId('annexe')] =
                $zipContentModel->annexe;
            $glaneurLocalDocumentInfo->element_files_association[$this->getElementId('bordereau')] = [
                $zipContentModel->bordereau
            ];
            $glaneurLocalDocumentInfo->element_files_association[$this->getElementId('premis')] = [
                $zipContentModel->premisFile,
            ];

            $glaneurLocalDocumentInfo->force_action_ok = false;
            $glaneurLocalDocumentInfo->action_ok = 'importation';
            $glaneurLocalDocumentInfo->action_ko = 'fatal-error';
            $id_d = $this->glaneurDocumentCreator->create($glaneurLocalDocumentInfo, $tmp_folder);
            $this->removeFolder($dossierId);
            return $id_d;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            throw new \RuntimeException($e->getMessage());
        } finally {
            $tmpFolder->delete($tmp_folder);
        }
    }

    private function getElementId(string $elementId): string
    {
        return $this->elementIdDictionnary[$elementId];
    }

    public function getConnecteurConfig(): DonneesFormulaire
    {
        return $this->connecteurConfig;
    }

    /**
     * @throws IpRestException
     */
    public function getDeskList(): array
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        $desks = [];
        $page = 0;

        do {
            $result = (new DeskApi($this->client, $this->configuration))->listUserDesks($tenantId, $page);

            foreach ($result->getContent() as $desk) {
                $desks[$desk->getId()] = $desk->getName();
            }

            $pageable = $result->getPageable();
            $currentPage = $pageable ? $pageable->getPageNumber() : $page;
            $totalPages = $result->getTotalPages() ?? 1;

            $page++;
        } while ($currentPage + 1 < $totalPages);

        return $desks;
    }
}
