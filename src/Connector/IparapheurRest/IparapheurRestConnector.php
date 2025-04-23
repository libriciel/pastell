<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest;

use DonneesFormulaire;
use Fichier;
use FileToSign;
use IparapheurV5Client\Api\Desk;
use IparapheurV5Client\Api\Typology;
use IparapheurV5Client\Model\ListSubtypesQuery;
use IparapheurV5Client\Model\ListTenantsQuery;
use IparapheurV5Client\Model\ListTypesQuery;
use IparapheurV5Client\Model\ListUserDesksQuery;
use Pastell\Action\TestConnectionInterface;
use SignatureConnecteur;
use Http\Client\Exception;
use IparapheurV5Client\Api\Tenant;
use IparapheurV5Client\Client;
use IparapheurV5Client\Exception\IparapheurV5Exception;
use IparapheurV5Client\TokenQuery;
use Pastell\Client\IparapheurV5\ClientFactory;
use stdClass;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class IparapheurRestConnector extends SignatureConnecteur implements
    IpRestTenantInterface,
    IpRestDeskInterface,
    TestConnectionInterface
{
    private const URL = 'url';
    private const USERNAME = 'username';
    private const PASSWORD = 'password';
    private const TENANT_ID = 'tenant_id';
    private const TYPE_ID = 'iparapheur_type_id';
    private DonneesFormulaire $connecteurConfig;
    private Client $client;

    public function __construct(
        private readonly ClientFactory $clientFactory,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws Exception
     * @throws IparapheurV5Exception
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $this->connecteurConfig = $donneesFormulaire;
        $url = $donneesFormulaire->get(self::URL);
        $auth = new TokenQuery();
        $auth->username = $donneesFormulaire->get(self::USERNAME) ?: '';
        $auth->password = $donneesFormulaire->get(self::PASSWORD) ?: '';
        $this->client = $this->clientFactory->getInstance();
        $this->client->authenticate($url, $auth);
    }

    public function testConnexion(): string
    {
        $result = $this->getTenantList();
        if (!$result) {
            return "Connexion réussie, mais aucune entité n'est associée à ce compte";
        }
        return 'Liste des entités iparapheur : ' . implode(', ', $result);
    }

    public function getTenantList(): array
    {
        $listTenantsQuery = new ListTenantsQuery();
        $listTenantsQuery->page = 0;
        $tenants = [];
        do {
            $result = (new Tenant($this->client))->listTenants($listTenantsQuery);
            foreach ($result->content as $tenant) {
                $tenants[$tenant->id] = $tenant->name;
            }
            $listTenantsQuery->page++;
        } while ($result->pageable->pageNumber + 1 < $result->totalPages);

        return $tenants;
    }

    /**
     * @throws IpRestException
     */
    public function getDeskList(): array
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        if (! $tenantId) {
            throw new IpRestException("L'entité iparapheur est obligatoire pour voir la liste des bureaux");
        }
        $listUserDesksQuery = new ListUserDesksQuery();
        $listUserDesksQuery->page = 0;
        $desks = [];
        do {
            $result = (new Desk($this->client))->listUserDesks($tenantId, $listUserDesksQuery);
            foreach ($result->content as $desk) {
                $desks[$desk->id] = $desk->name;
            }
            $listUserDesksQuery->page++;
        } while ($result->pageable->pageNumber + 1 < $result->totalPages);

        return $desks;
    }

    /**
     * @throws IpRestException
     */
    public function getTypeList(): array
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        if (! $tenantId) {
            throw new IpRestException("L'entité iparapheur est obligatoire pour voir la liste des types");
        }
        $listTypesQuery = new ListTypesQuery();
        $listTypesQuery->page = 0;
        $types = [];
        do {
            $result = (new Typology($this->client))->listTypes($tenantId, $listTypesQuery);
            foreach ($result->content as $type) {
                $types[$type->id] = $type->name;
            }
            $listTypesQuery->page++;
        } while ($result->pageable->pageNumber + 1 < $result->totalPages);

        return $types;
    }

    /**
     * @throws IpRestException
     */
    public function getSubTypeList(): array
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        $typeId = $this->connecteurConfig->get(self::TYPE_ID);
        if ((! $tenantId) || (! $typeId)) {
            throw new IpRestException(
                "L'entité et le type iparapheur sont obligatoires pour voir la liste des sous-types"
            );
        }
        $listSubtypesQuery = new ListSubtypesQuery();
        $listSubtypesQuery->page = 0;
        $subTypes = [];
        do {
            $result = (new Typology($this->client))->listSubtypes($tenantId, $typeId, $listSubtypesQuery);
            foreach ($result->content as $subType) {
                $subTypes[$subType->id] = $subType->name;
            }
            $listSubtypesQuery->page++;
        } while ($result->pageable->pageNumber + 1 < $result->totalPages);

        return $subTypes;
    }

    public function getNbJourMaxInConnecteur()
    {
        // TODO: Implement getNbJourMaxInConnecteur() method.
    }

    public function getSousType()
    {
        // TODO: Implement getSousType() method.
    }

    public function getDossierID($id, $name)
    {
        // TODO: Implement getDossierID() method.
    }

    public function sendDossier(FileToSign $dossier)
    {
        // TODO: Implement sendDossier() method.
    }

    public function getSignature($dossierID, $archive = true)
    {
        // TODO: Implement getSignature() method.
    }

    public function getAllHistoriqueInfo($dossierID)
    {
        // TODO: Implement getAllHistoriqueInfo() method.
    }

    public function getLastHistorique($history): string
    {
        // TODO: Implement getLastHistorique() method.
        return '';
    }

    public function getRefusalMessage($dossierID)
    {
        // TODO: Implement getRefusalMessage() method.
    }

    public function getDateSignature(array|stdClass $history): string
    {
        // TODO: Implement getDateSignature() method.
        return '';
    }

    public function effacerDossierRejete($dossierID)
    {
        // TODO: Implement effacerDossierRejete() method.
    }

    public function exercerDroitRemordDossier($dossierID)
    {
        // TODO: Implement exercerDroitRemordDossier() method.
    }

    public function isFinalState(string $lastState): bool
    {
        // TODO: Implement isFinalState() method.
        return false;
    }

    public function isRejected(string $lastState): bool
    {
        // TODO: Implement isRejected() method.
        return false;
    }

    public function isDetached($signature): bool
    {
        // TODO: Implement isDetached() method.
        return false;
    }

    public function getDetachedSignature($file)
    {
        // TODO: Implement getDetachedSignature() method.
    }

    public function getSignedFile($file)
    {
        // TODO: Implement getSignedFile() method.
    }

    public function getBordereauFromSignature($signature, string $documentId = ''): ?Fichier
    {
        // TODO: Implement getBordereauFromSignature() method.
        return new Fichier();
    }

    public function getMetadataSortie($signature): ?Fichier
    {
        // TODO: Implement getMetadataSortie() method.
        return new Fichier();
    }
}
