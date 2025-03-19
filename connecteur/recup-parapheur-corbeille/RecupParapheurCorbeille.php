<?php

declare(strict_types=1);

use IparapheurV5Client\Api\AdminTrashBin;
use IparapheurV5Client\Api\Tenant;
use IparapheurV5Client\Client;
use IparapheurV5Client\Model\ListTenantsQuery;
use IparapheurV5Client\Model\ListTrashBinFoldersQuery;
use IparapheurV5Client\TokenQuery;
use Pastell\Client\IparapheurV5\ClientFactory;
use Pastell\Client\IparapheurV5\ZipContent;
use Pastell\Connector\IparapheurRest\IpRestTenantInterface;

class RecupParapheurCorbeille extends Connecteur implements IpRestTenantInterface
{
    private const USERNAME = 'username';
    private const PASSWORD = 'password';
    private const URL = 'url';
    private const NB_RECUP = 'nb_recup';
    private const TENANT_ID = 'tenant_id';
    private array $elementIdDictionnary;
    private DonneesFormulaire $connecteurConfig;

    public function __construct(
        private readonly GlaneurDocumentCreator $glaneurDocumentCreator,
        private readonly ClientFactory $clientFactory,
    ) {
    }

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
            $part = explode(":", $line, 2);
            if (! isset($part[1])) {
                continue;
            }
            if (! isset($this->elementIdDictionnary[trim($part[0])])) {
                continue;
            }
            $this->elementIdDictionnary[trim($part[0])] = trim($part[1]);
        }
    }

    private function getAuthenticatedClient(): Client
    {
        $tokenQuery = new TokenQuery();
        $tokenQuery->username = $this->connecteurConfig->get(self::USERNAME, '');
        $tokenQuery->password = $this->connecteurConfig->get(self::PASSWORD, '');
        $client = $this->clientFactory->getInstance();
        $client->authenticate($this->connecteurConfig->get(self::URL, ''), $tokenQuery);

        return $client;
    }

    public function getTenantList(): array
    {
        $listTenantsQuery = new ListTenantsQuery();
        $listTenantsQuery->page = 0;
        $tenants = [];
        do {
            $result = (new Tenant($this->getAuthenticatedClient()))->listTenants($listTenantsQuery);
            foreach ($result->content as $tenant) {
                $tenants[$tenant->id] = $tenant->name;
            }
            $listTenantsQuery->page++;
        } while ($result->pageable->pageNumber + 1 < $result->totalPages);

        return $tenants;
    }

    public function testConnexion(): string
    {
        $result = $this->getTenantList();
        if (! $result) {
            return "La connexion est ok, mais il n'existe aucune entité associée à ce compte";
        }
        return 'Liste des entités parapheurs : ' . implode(", ", $result);
    }

    public function listDossier(): array
    {
        $adminTrashBin = new AdminTrashBin($this->getAuthenticatedClient());

        $listTrashBinFolderQuery = new ListTrashBinFoldersQuery();
        $listTrashBinFolderQuery->size = (int)$this->connecteurConfig->get(self::NB_RECUP);
        $listTrashBinFolderQuery->page = 0;
        $pageFolderRepresentation =  $adminTrashBin->listTrashBinFolders(
            $this->connecteurConfig->get(self::TENANT_ID, ''),
            $listTrashBinFolderQuery
        );
        $result = [];
        foreach ($pageFolderRepresentation->content as $folder) {
            $result[$folder->id] = $folder->name;
        }
        return [
            'number' => $pageFolderRepresentation->totalElements,
            'first' => $result,
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
    private function retrieveOneDossier(string $dossierId): string
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        try {
            $client = $this->getAuthenticatedClient();
            $adminTrashBin = new AdminTrashBin($client);
            $response = $adminTrashBin->downloadTrashBinFolderZip(
                $this->connecteurConfig->get(self::TENANT_ID, ''),
                $dossierId
            );
            $body = $response->getBody();
            $zipFilePath = $tmp_folder . '/response.zip';
            $file = fopen($zipFilePath, 'wb');
            while (!$body->eof()) {
                fwrite($file, $body->read(1024));
            }
            fclose($file);
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

        $adminTrashBin->deleteTrashBinFolder(
            $this->connecteurConfig->get(self::TENANT_ID, ''),
            $dossierId
        );

        return $id_d;
    }

    private function getElementId(string $elementId): string
    {
        return $this->elementIdDictionnary[$elementId];
    }
}
