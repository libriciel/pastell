<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Api;

use JsonException;
use Pastell\Client\S2low\Model\ActeListQuery;
use Pastell\Client\S2low\Model\BordereauQuery;
use Pastell\Client\S2low\Model\DownloadFileQuery;
use Pastell\Client\S2low\Model\FileListQuery;
use Pastell\Client\S2low\Responses\ActeListResponse;
use Pastell\Client\S2low\Responses\File;
use Pastell\Client\S2low\Responses\Transaction;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientException;
use Psr\Http\Client\ClientExceptionInterface;
use stdClass;

final class Actes
{
    private const LIST_ACTES_API = '/modules/actes/api/list_actes.php';
    private const ACTES_FILES_LIST_API = '/modules/actes/actes_transac_get_files_list.php';
    private const DOWNLOAD_FILE_API = '/modules/actes/actes_download_file.php';
    private const BORDEREAU_API = '/modules/actes/actes_create_pdf.php';
    private const ACTES_SAE_STATUS = '/modules/actes/api/actes_sae_status.php';
    public const EN_ATTENTE_TRANSMISSION_SAE = '19';

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
        $response = json_decode(
            $this->client->get(self::LIST_ACTES_API, $actesListQuery),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $transactions = array_map(static function ($transaction) {
            return new Transaction(
                $transaction->id,
                $transaction->subject,
                $transaction->number,
                $transaction->date,
                $transaction->nature_descr,
                $transaction->classification,
                $transaction->type
            );
        }, $response['transactions']);

        return new ActeListResponse($response['status_id'], $transactions);
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
        $transactionQuery = new BordereauQuery();
        $transactionQuery->trans_id = $transactionId;
        return $this->client->get(self::BORDEREAU_API, $transactionQuery);
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function getFileList(string $transactionId): array
    {
        $transactionQuery = new FileListQuery();
        $transactionQuery->transaction = $transactionId;
        $fetched_files = $this->client->get(self::ACTES_FILES_LIST_API, $transactionQuery);
        $files = json_decode($fetched_files, true, 512, JSON_THROW_ON_ERROR);

        return array_map(static function ($file) {
            return new File(
                $file['id'],
                $file['name'],
                $file['posted_filename'],
                $file['mimetype'],
                $file['size'],
                $file['signature'] ?? null
            );
        }, $files);
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
