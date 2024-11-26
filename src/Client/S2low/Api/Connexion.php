<?php

namespace Pastell\Client\S2low\Api;

use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientException;
use Psr\Http\Client\ClientExceptionInterface;

final class Connexion
{
    private const TEST_CONNEXION_API = '/api/test-connexion.php';

    public function __construct(private readonly S2lowClient $client)
    {
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
