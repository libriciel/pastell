<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupActesS2low;

use DonneesFormulaire;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientAuth;
use Pastell\Client\S2low\S2lowClientException;
use Pastell\Client\S2low\S2lowClientFactory;
use Psr\Http\Client\ClientExceptionInterface;

class RecupActesS2lowConnector extends \Connecteur
{
    private S2lowClient $client;

    public function __construct(
        private readonly S2lowClientFactory $s2lowClientFactory,
    ) {
    }

    /**
     * @throws \DateInvalidOperationException
     * @throws \DateMalformedStringException
     * @throws \UnrecoverableException
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $url = $donneesFormulaire->get('url');

        $auth = new S2lowClientAuth();
        $auth->username = $donneesFormulaire->get('username') ?: '';
        $auth->password = $donneesFormulaire->get('password') ?: '';
        $auth->user_certificat_password = $donneesFormulaire->get('certificate_password');
        $auth->user_key_pem = $donneesFormulaire->getFilePath('certificate_key');
        $auth->user_certificat_pem = $donneesFormulaire->getFilePath('certificate_pem');
        $this->client = $this->s2lowClientFactory->getClient($url, $auth);
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function testAuth(): string
    {
        return $this->client->connexion()->testConnexion();
    }
}
