<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupPesS2low;

use DonneesFormulaire;
use Pastell\Client\S2low\Model\PesAllerListQuery;
use Pastell\Client\S2low\Model\PesAllerListResponse;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientAuth;
use Pastell\Client\S2low\S2lowClientException;
use Pastell\Client\S2low\S2lowClientFactory;
use Psr\Http\Client\ClientExceptionInterface;

class RecupPesS2lowConnector extends \Connecteur
{
    private const STATUS_ACK = 4;
    private S2lowClient $client;
    private string $startDate;
    private string $endDate;
    private int $transactionStatus;

    public function __construct(
        private readonly S2lowClientFactory $s2lowClientFactory,
    ) {
    }

    /**
     * @throws \UnrecoverableException
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $url = $donneesFormulaire->get('url');
        $this->transactionStatus = (int)$donneesFormulaire->get('transaction_status') ?: self::STATUS_ACK;
        $this->startDate = $donneesFormulaire->get('start_date');
        $this->endDate = $donneesFormulaire->get('end_date');

        $auth = new S2lowClientAuth();
        $auth->username = $donneesFormulaire->get('user_login') ?: '';
        $auth->password = $donneesFormulaire->get('user_password') ?: '';
        $auth->user_certificat_password = $donneesFormulaire->get('certificate_password');
        $auth->user_key_pem = $donneesFormulaire->getFilePath('certificate_key');
        $auth->user_certificat_pem = $donneesFormulaire->getFilePath('certificate_pem');
        $this->client = $this->s2lowClientFactory->getClient($url, $auth);
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
     */
    public function listPes(int $numberOfTransactions, int $offset = 0): PesAllerListResponse
    {
        $query = new PesAllerListQuery();
        $query->limit = $numberOfTransactions;
        $query->minDate = $this->startDate;
        $query->maxDate = $this->endDate;
        $query->offset = $offset;
        $query->statusId = $this->transactionStatus;

        return $this->client->pes()->getPesAllerList($query);
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function changeStatus(string $transactionId, string $statusId): void
    {
        $this->client->pes()->changePesStatus($transactionId, $statusId);
    }
}
