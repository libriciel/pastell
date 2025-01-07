<?php

declare(strict_types=1);

namespace Pastell\Client\S2low;

use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

final class S2lowClientFactory
{
    private ClientInterface $httpClient;

    public function setClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }
    public function getClient(string $url, S2lowClientAuth $s2lowClientAuth): S2lowClient
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new Psr18Client(
                HttpClient::createForBaseUri($url, [
                    'auth_basic' => [
                        $s2lowClientAuth->username,
                        $s2lowClientAuth->password
                    ],
                    'local_cert' => $s2lowClientAuth->user_certificat_pem,
                    'local_pk' => $s2lowClientAuth->user_key_pem,
                    'passphrase' => $s2lowClientAuth->user_certificat_password,
                    'verify_peer' => false,
                    'verify_host' => false,
                ])
            );
        }

        return new S2lowClient($this->httpClient);
    }
}
