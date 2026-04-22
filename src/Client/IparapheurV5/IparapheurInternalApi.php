<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5;

use GuzzleHttp\Psr7\Request;
use Libriciel\IparapheurV5\Client\ApiException;
use Libriciel\IparapheurV5\Client\Configuration;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

readonly class IparapheurInternalApi
{
    public function __construct(
        private ClientInterface $client,
        private Configuration $configuration,
    ) {
    }

    /**
     * @throws ApiException
     */
    public function getFolderIdByLegacyId(string $tenantId, string $legacyFolderId): string
    {
        $url = \sprintf(
            '%s/api/internal/tenant/%s/folder/by-legacy-id/%s',
            rtrim($this->configuration->getHost(), '/'),
            rawurlencode($tenantId),
            rawurlencode($legacyFolderId),
        );

        $request = new Request(
            'GET',
            $url,
            ['Authorization' => 'Bearer ' . $this->configuration->getAccessToken()],
        );

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException($e->getMessage(), $request);
        }

        if ($response->getStatusCode() !== 200) {
            throw new ApiException(
                \sprintf('[%d] Error connecting to the API (%s)', $response->getStatusCode(), $response->getBody()),
                $request,
                $response,
            );
        }

        try {
            /** @var array{id?: string} $data */
            $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiException($e->getMessage(), $request, $response, $e);
        }
        return $data['id'] ?? throw new ApiException('Missing id in API response', $request, $response);
    }
}
