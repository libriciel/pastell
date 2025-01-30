<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest;

use DonneesFormulaire;
use Fichier;
use FileToSign;
use SignatureConnecteur;
use Http\Client\Exception;
use IparapheurV5Client\Api\Tenant;
use IparapheurV5Client\Client;
use IparapheurV5Client\Exception\IparapheurV5Exception;
use IparapheurV5Client\TokenQuery;
use Pastell\Client\IparapheurV5\ClientFactory;
use stdClass;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class IparapheurRestConnector extends SignatureConnecteur
{
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
        $url = $donneesFormulaire->get('url');
        $auth = new TokenQuery();
        $auth->username = $donneesFormulaire->get('username') ?: '';
        $auth->password = $donneesFormulaire->get('password') ?: '';
        $this->client = $this->clientFactory->getInstance();
        $this->client->authenticate($url, $auth);
    }

    public function getTenantList(): array
    {
        $result = [];
        $pageTenant = (new Tenant($this->client))->listTenants();
        foreach ($pageTenant->content as $tenant) {
            $result[$tenant->id] = $tenant->name;
        }
        return $result;
    }

    public function testConnexion(): string
    {
        $result = $this->getTenantList();
        if (!$result) {
            return "Connexion réussie, mais aucune entité n'est associée à ce compte";
        }
        return 'Liste des entités iparapheur : ' . implode(', ', $result);
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
