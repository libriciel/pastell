<?php

declare(strict_types=1);

namespace Pastell\Client\S2low;

use DonneesFormulaire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use UnrecoverableException;

class S2lowClientFactory
{
    /**
     * @throws UnrecoverableException|UnrecoverableException
     */
    public function getClient(DonneesFormulaire $donneesFormulaire): S2lowClient
    {
        $client = new Psr18Client(HttpClient::createForBaseUri($donneesFormulaire->get('url'), [
            'auth_basic' => [
                $donneesFormulaire->get('username'),
                $donneesFormulaire->get('password')
            ],
            'local_cert' => $donneesFormulaire->getFilePath('user_certificat_pem'),
            'local_pk' => $donneesFormulaire->getFilePath('user_key_pem'),
            'passphrase' => $donneesFormulaire->get('user_certificat_password')
        ]));
        return new S2lowClient($client);
    }
}
