<?php

namespace Pastell\Client\S2low\Api;

use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientException;
use Psr\Http\Client\ClientExceptionInterface;

class Connexion
{
    /**
     * @var S2lowClient
     */
    private S2lowClient $client;

    private const TEST_CONNEXION_API = '/api/test-connexion.php';

    public function __construct(S2lowClient $client)
    {
        $this->client = $client;
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function testConnexion(): string
    {
        return $this->client->get(self::TEST_CONNEXION_API);
    }
}
