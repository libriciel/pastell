<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Api;

use Pastell\Client\S2low\Model\PesAllerListQuery;
use Pastell\Client\S2low\Model\PesAllerListResponse;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientException;
use Psr\Http\Client\ClientExceptionInterface;

final class Pes
{
    private const LIST_PES_API = '/modules/helios/api/list_pes_aller.php';
    private const CHANGE_STATUS_API = '/modules/helios/helios_transac_change_status_sae.php';

    public function __construct(private readonly S2lowClient $client)
    {
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function getPesAllerList(
        PesAllerListQuery $actesListQuery
    ): PesAllerListResponse {
        $response = $this->client->get(self::LIST_PES_API, $actesListQuery);
        return $this->client->getSerializer()->deserialize($response, PesAllerListResponse::class, 'json');
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function changePesStatus(string $transactionId, string $statusId): void
    {
        $body = [
            'api' => '1',
            'transaction_id' => $transactionId,
            'status_id' => $statusId,
        ];
        $this->client->post(self::CHANGE_STATUS_API, $body);
    }
}
