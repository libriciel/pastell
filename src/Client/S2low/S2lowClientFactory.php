<?php

declare(strict_types=1);

namespace Pastell\Client\S2low;

use DonneesFormulaire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use UnrecoverableException;

final class S2lowClientFactory
{
    public function getClient(string $url, S2lowClientAuth $s2lowClientAuth): S2lowClient
    {
        $client = new Psr18Client(
            HttpClient::createForBaseUri($url, [
                'auth_basic' => [
                    $s2lowClientAuth->username,
                    $s2lowClientAuth->password
                ],
                'local_cert' => $s2lowClientAuth->user_certificat_pem,
                'local_pk' => $s2lowClientAuth->user_key_pem,
                'passphrase' => $s2lowClientAuth->user_certificat_password
            ])
        );
        return new S2lowClient($client);
    }
}
