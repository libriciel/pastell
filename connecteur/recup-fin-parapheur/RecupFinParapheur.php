<?php

declare(strict_types=1);

use IparapheurV5Client\Api\Desk;
use IparapheurV5Client\Api\Folder;
use IparapheurV5Client\Api\Tenant;
use IparapheurV5Client\Client;
use IparapheurV5Client\Exception\IparapheurV5Exception;
use IparapheurV5Client\Model\ListFoldersQuery;
use IparapheurV5Client\Model\ListTenantsQuery;
use IparapheurV5Client\Model\ListUserDesksQuery;
use IparapheurV5Client\TokenQuery;
use Pastell\Client\IparapheurV5\ClientFactory;
use Pastell\Client\IparapheurV5\ZipContent;
use IparapheurV5Client\Model\State;
use Pastell\Connector\IparapheurRest\IpRestDeskInterface;
use Pastell\Connector\IparapheurRest\IpRestTenantInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class RecupFinParapheur extends Connecteur implements IpRestTenantInterface, IpRestDeskInterface
{
    private const USERNAME = 'username';
    private const PASSWORD = 'password';
    private const URL = 'url';
    private const TENANT_ID = 'tenant_id';
    private const DESK_ID = 'desk_id';
    private const NB_RECUP = 'nb_recup';
    private array $elementIdDictionnary;
    private DonneesFormulaire $connecteurConfig;

    public function __construct(
        private readonly GlaneurDocumentCreator $glaneurDocumentCreator,
        private readonly ClientFactory $clientFactory,
    ) {
    }

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
    }

    /**
     * @throws ExceptionInterface
     * @throws \Http\Client\Exception
     * @throws IparapheurV5Exception
     */
    private function getAuthentificatedClient(): Client
    {
        $tokenQuery = new TokenQuery();
        $tokenQuery->username = $this->connecteurConfig->get(self::USERNAME, '');
        $tokenQuery->password = $this->connecteurConfig->get(self::PASSWORD, '');
        $client = $this->clientFactory->getInstance();
        $client->authenticate($this->connecteurConfig->get(self::URL, ''), $tokenQuery);
        return $client;
    }

    /**
     * @throws ExceptionInterface
     * @throws \Http\Client\Exception
     * @throws IparapheurV5Exception
     */
    public function getTenantList(): array
    {
        $listTenantsQuery = new ListTenantsQuery();
        $listTenantsQuery->page = 0;
        $tenants = [];
        do {
            $result = (new Tenant($this->getAuthentificatedClient()))->listTenants($listTenantsQuery);
            foreach ($result->content as $tenant) {
                $tenants[$tenant->id] = $tenant->name;
            }
            $listTenantsQuery->page++;
        } while ($result->pageable->pageNumber + 1 < $result->totalPages);

        return $tenants;
    }

    /**
     * @throws \Http\Client\Exception
     * @throws ExceptionInterface
     * @throws IparapheurV5Exception
     */
    public function testConnexion(): string
    {
        $result = $this->getTenantList();
        if (!$result) {
            return "Connexion réussie, mais aucune entité n'est associée à ce compte";
        }
        return 'Liste des entités parapheurs : ' . implode(', ', $result);
    }

    /**
     * @throws ExceptionInterface
     * @throws \Http\Client\Exception
     * @throws IparapheurV5Exception
     */
    public function getFinishedFolders(): array
    {
        $result = [];
        $listFolderQuery = new ListFoldersQuery();
        $listFolderQuery->size = (int)$this->connecteurConfig->get(self::NB_RECUP);
        $listFolderQuery->page = 0;
        $pageFolder = (new Folder($this->getAuthentificatedClient()))->listFolders(
            $this->connecteurConfig->get(self::TENANT_ID, ''),
            $this->connecteurConfig->get(self::DESK_ID, ''),
            State::FINISHED,
            $listFolderQuery
        );
        foreach ($pageFolder->content as $folder) {
            $result[$folder->id] = $folder->name;
        }
        return $result;
    }

    /**
     * @throws \Http\Client\Exception
     * @throws ExceptionInterface
     * @throws IparapheurV5Exception
     */
    public function removeFolder(string $folder_id): void
    {
        (new Folder($this->getAuthentificatedClient()))->deleteFolder(
            $this->connecteurConfig->get(self::TENANT_ID, ''),
            $this->connecteurConfig->get(self::DESK_ID, ''),
            $folder_id
        );
    }


    /**
     * @throws ExceptionInterface
     * @throws \Http\Client\Exception
     * @throws IparapheurV5Exception
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
     * @throws \Http\Client\Exception
     * @throws ExceptionInterface
     * @throws IparapheurV5Exception
     * @throws Exception
     */
    private function retrieveOneDossier(string $dossierId): string
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        try {
            $client = $this->getAuthentificatedClient();
            $folder = new Folder($client);
            $response = $folder->downloadFolderZip(
                $this->connecteurConfig->get(self::TENANT_ID, ''),
                self::DESK_ID,
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
     * @throws ExceptionInterface
     * @throws \Http\Client\Exception
     * @throws IparapheurV5Exception
     */
    public function getDeskList(): array
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        $listUserDesksQuery = new ListUserDesksQuery();
        $listUserDesksQuery->page = 0;
        $desks = [];
        do {
            $result = (new Desk($this->getAuthentificatedClient()))
                ->listUserDesks($tenantId, $listUserDesksQuery);
            foreach ($result->content as $desk) {
                $desks[$desk->id] = $desk->name;
            }
            $listUserDesksQuery->page++;
        } while ($result->pageable->pageNumber + 1 < $result->totalPages);

        return $desks;
    }
}
