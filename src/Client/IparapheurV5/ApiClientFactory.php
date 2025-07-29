<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5;

use GuzzleHttp\Psr7\Request;
use Http\Discovery\Psr18Client;
use JsonException;
use OpenAPI\Client\Configuration;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

class ApiClientFactory
{
    private ?ClientInterface $httpClient = null;
    public function setClientInterface(ClientInterface $clientInterface): void
    {
        $this->httpClient = $clientInterface;
    }
    /**
     * @throws JsonException|RuntimeException
     * @throws ClientExceptionInterface
     */
    private function fetchAccessToken(IparapheurAuthConfig $iparapheurAuthConfig, ClientInterface $client): string
    {
        $data = http_build_query([
            'username' => $iparapheurAuthConfig->keycloakUsername,
            'password' => $iparapheurAuthConfig->keycloakPassword,
            'client_id' => $iparapheurAuthConfig->keycloakClientId,
            'grant_type' => 'password',
        ]);

        $request = new Request(
            'POST',
            $iparapheurAuthConfig->keycloakUrl,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $data
        );

        $response = $client->sendRequest($request);
        $body = (string)$response->getBody();

        /** @var array{access_token?: string} $json */
        $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        return $json['access_token'] ?? throw new RuntimeException('Token non présent dans la réponse.');
    }


    /**
     * @return array{0: ClientInterface, 1: Configuration}
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function createAuthenticatedClient(IparapheurAuthConfig $iparapheurAuthConfig): array
    {
        $client = $this->httpClient ?? new Psr18Client();
        $token = $this->fetchAccessToken($iparapheurAuthConfig, $client);
        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken($token)
            ->setHost($iparapheurAuthConfig->iparapheurUrl);
        return [$client, $config];
    }
}
