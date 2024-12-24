<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Api;

use JsonException;
use Pastell\Client\S2low\Model\ActeListQuery;
use Pastell\Client\S2low\Model\DownloadFileQuery;
use Pastell\Client\S2low\Model\ActeListResponse;
use Pastell\Client\S2low\Model\File;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientException;
use Psr\Http\Client\ClientExceptionInterface;

final class Actes
{
    private const LIST_ACTES_API = '/modules/actes/api/list_actes.php';
    private const ACTES_FILES_LIST_API = '/modules/actes/actes_transac_get_files_list.php';
    private const ACTES_GET_ARACTES = '/modules/actes/actes_transac_get_ARActe.php';
    private const DOWNLOAD_FILE_API = '/modules/actes/actes_download_file.php';
    private const BORDEREAU_API = '/modules/actes/actes_create_pdf.php';
    private const ACTES_SAE_STATUS = '/modules/actes/api/actes_sae_status.php';

    public function __construct(private readonly S2lowClient $client)
    {
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function getActesList(
        ActeListQuery $actesListQuery
    ): ActeListResponse {
        $response = $this->client->get(self::LIST_ACTES_API, $actesListQuery);
        return $this->client->getSerializer()->deserialize($response, ActeListResponse::class, 'json');
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function downloadFile(string $fileId, bool $tampon = true, ?string $date_affichage = null): string
    {
        $downloadFileQuery = new DownloadFileQuery();
        $downloadFileQuery->file = $fileId;
        $downloadFileQuery->tampon = $tampon;
        $downloadFileQuery->date_affichage = $date_affichage;
        return $this->client->get(self::DOWNLOAD_FILE_API, $downloadFileQuery);
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function getBordereau(string $transactionId): string
    {
        return $this->client->get(self::BORDEREAU_API, ['trans_id' => $transactionId]);
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     * @return File[]
     */
    public function getFileList(string $transactionId): array
    {
        $fetched_files = $this->client->get(self::ACTES_FILES_LIST_API, ['transaction' => $transactionId]);
        return $this->client->getSerializer()->deserialize($fetched_files, File::class . '[]', 'json');
    }

    public function getAractes(string $transactionId): string
    {
        return $this->client->get(
            self::ACTES_GET_ARACTES,
            [
                'id' => $transactionId,
                'api' => true,
            ]
        );
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function changeActeStatus(string $transactionId, string $statusId): void
    {
        $body = [
            'transaction_id' => $transactionId,
            'status_id' => $statusId,
        ];
        $this->client->post(self::ACTES_SAE_STATUS, $body);
    }
}
