<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupActesS2low;

use DonneesFormulaire;
use Pastell\Client\S2low\Model\ActeListQuery;
use Pastell\Client\S2low\Model\ActeListResponse;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientAuth;
use Pastell\Client\S2low\S2lowClientException;
use Pastell\Client\S2low\S2lowClientFactory;
use Psr\Http\Client\ClientExceptionInterface;

class RecupActesS2lowConnector extends \Connecteur
{
    private const FLUX = 'draft-ls-recup-actes-s2low';
    private const STATUS_ACK = 4;
    private S2lowClient $client;
    private string $startDate;
    private string $endDate;
    private int $transactionStatus;
    private int $numberOfDocumentsPerJob;
    private int $maxNumberOfDocumentsInEntity;

    public function __construct(
        private readonly S2lowClientFactory $s2lowClientFactory,
        private readonly \DocumentEntite $documentEntite,
    ) {
    }

    /**
     * @throws \DateInvalidOperationException
     * @throws \DateMalformedStringException
     * @throws \UnrecoverableException
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $url = $donneesFormulaire->get('url');
        $this->transactionStatus = (int)$donneesFormulaire->get('transaction_status') ?: self::STATUS_ACK;
        $this->startDate = $donneesFormulaire->get('start_date');
        $this->endDate = $donneesFormulaire->get('end_date');

        $dateSixtyDaysAgo = new \DateTime();
        $dateSixtyDaysAgo->sub(new \DateInterval('P62D'));
        $connectorDate = new \DateTime($donneesFormulaire->get('end_date'));

        if ($dateSixtyDaysAgo > $connectorDate) {
            $this->endDate = $connectorDate->format('Y-m-d');
        } else {
            $this->endDate = $dateSixtyDaysAgo->format('Y-m-d');
        }

        $this->numberOfDocumentsPerJob = (int)$donneesFormulaire->get('nb_recup') ?: 10;
        $this->maxNumberOfDocumentsInEntity = (int)$donneesFormulaire->get('nb_documents') ?: 100;

        $auth = new S2lowClientAuth();
        $auth->username = $donneesFormulaire->get('username') ?: '';
        $auth->password = $donneesFormulaire->get('password') ?: '';
        $auth->user_certificat_password = $donneesFormulaire->get('certificate_password');
        $auth->user_key_pem = $donneesFormulaire->getFilePath('certificate_key');
        $auth->user_certificat_pem = $donneesFormulaire->getFilePath('certificate_pem');
        $this->client = $this->s2lowClientFactory->getClient($url, $auth);
    }

    public function getNumberOfDocumentsToCreate(int $entityId): int
    {
        $maxCreatableDocuments = max(
            $this->maxNumberOfDocumentsInEntity - $this->documentEntite->getNbAll(
                $entityId,
                self::FLUX
            ),
            0
        );

        return min(
            $maxCreatableDocuments,
            $this->numberOfDocumentsPerJob
        );
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function getEndDate(): string
    {
        return $this->endDate;
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function testAuth(): string
    {
        return $this->client->connexion()->testConnexion();
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function listActes(int $numberOfTransactions, int $offset = 0): ActeListResponse
    {
        $query = new ActeListQuery();
        $query->limit = $numberOfTransactions;
        $query->minDate = $this->startDate;
        $query->maxDate = $this->endDate;
        $query->offset = $offset;
        $query->statusId = $this->transactionStatus;

        return $this->client->actes()->getActesList($query);
    }
}
