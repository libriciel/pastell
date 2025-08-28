<?php

declare(strict_types=1);

use Libriciel\IparapheurV5\Client\Api\AdminTrashBinApi;
use Libriciel\IparapheurV5\Client\Api\TenantApi;
use Pastell\Action\TestConnectionInterface;
use Pastell\Client\IparapheurV5\IparapheurAuthConfig;
use Pastell\Client\IparapheurV5\ApiClientFactory;
use Pastell\Client\IparapheurV5\ZipContent;
use Libriciel\IparapheurV5\Client\Configuration;
use Pastell\Connector\IparapheurRest\IpRestTenantInterface;
use Psr\Http\Client\ClientInterface;

class RecupParapheurCorbeille extends Connecteur implements IpRestTenantInterface, TestConnectionInterface
{
    private const USERNAME = 'username';
    private const PASSWORD = 'password';
    private const URL = 'url';
    private const NB_RECUP = 'nb_recup';
    private const TENANT_ID = 'tenant_id';
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
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
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
            if (! isset($part[1])) {
                continue;
            }
            if (! isset($this->elementIdDictionnary[trim($part[0])])) {
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
        if (! $result) {
            return "La connexion est ok, mais il n'existe aucune entité associée à ce compte";
        }
        return 'Liste des entités parapheurs : ' . implode(', ', $result);
    }

    public function listDossier(): array
    {
        $result = (new AdminTrashBinApi($this->client, $this->configuration))->listTrashBinFolders(
            $this->connecteurConfig->get(self::TENANT_ID, ''),
            0,
            (int)$this->connecteurConfig->get(self::NB_RECUP)
        );
        $folders = [];
        foreach ($result->getContent() as $folder) {
            $folders[$folder->getId()] = $folder->getName();
        }
        return [
            'number' => $result->getTotalElements(),
            'first' => $folders,
        ];
    }

    /**
     * @throws UnrecoverableException
     */
    public function recupOne(): array
    {
        $listDossier = $this->listDossier();
        $id_d = [];
        foreach ($listDossier['first'] as $dossierId => $dossierName) {
            $id_d[] = $this->retrieveOneDossier($dossierId);
        }
        return $id_d;
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    private function retrieveOneDossier(string $folderId): string
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID, '');
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        try {
            $adminTrashBinApi = new AdminTrashBinApi($this->client, $this->configuration);
            $zipData = $adminTrashBinApi->downloadTrashBinFolderZip(
                $tenantId,
                $folderId
            );
            $zipFilePath = $tmp_folder . '/result.zip';
            file_put_contents($zipFilePath, $zipData);

            $zipContent = new ZipContent();
            $zipContentModel = $zipContent->extract($zipFilePath, $tmp_folder);
            $glaneurLocalDocumentInfo = new GlaneurDocumentInfo($this->getConnecteurInfo()['id_e']);
            $glaneurLocalDocumentInfo->nom_flux = $this->connecteurConfig->get('pastell_module_id', '');
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
            $glaneurLocalDocumentInfo->element_files_association[$this->getElementId('premis')]  = [
                $zipContentModel->premisFile,
            ];

            $glaneurLocalDocumentInfo->force_action_ok = false;
            $glaneurLocalDocumentInfo->action_ok = 'importation';
            $glaneurLocalDocumentInfo->action_ko = 'fatal-error';
            $id_d = $this->glaneurDocumentCreator->create($glaneurLocalDocumentInfo, $tmp_folder);
        } finally {
            $tmpFolder->delete($tmp_folder);
        }

        $adminTrashBinApi->deleteTrashBinFolder(
            $tenantId,
            $folderId
        );

        return $id_d;
    }

    private function getElementId(string $elementId): string
    {
        return $this->elementIdDictionnary[$elementId];
    }
}
