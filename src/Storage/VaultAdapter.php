<?php

declare(strict_types=1);

namespace Pastell\Storage;

use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Vault\AuthenticationStrategies\TokenAuthenticationStrategy;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\Uri;
use Vault\Client;
use Vault\Exceptions\AuthenticationException;
use Vault\Exceptions\RuntimeException;

class VaultAdapter implements StorageInterface
{
    private Client $vaultClient;

    /**
     * @throws ClientExceptionInterface
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function __construct(string $vaultUrl, string $token)
    {
        $this->vaultClient = new Client(
            new Uri($vaultUrl),
            new \AlexTartan\GuzzlePsr18Adapter\Client(),
            new RequestFactory(),
            new StreamFactory()
        );
        $authenticated = $this->vaultClient
            ->setAuthenticationStrategy(new TokenAuthenticationStrategy($token))
            ->authenticate();
        if (!$authenticated) {
            throw new AuthenticationException('La connexion à Vault a échoué');
        }
    }

    public function write(string $id, string $content): string
    {
        try {
            $response = $this->vaultClient->write('/secret/data/' . $id, ['data' => ['password' => $content]]);
            $response = $response->getData()['created_time'];
        } catch (ClientExceptionInterface $e) {
            $response = $e->getCode() . ' : ' . $e->getMessage();
        }
        return $response;
    }

    public function read(string $id): string
    {
        try {
            $response = $this->vaultClient->read('/secret/data/' . $id);
            $response = $response->getData()['data']['password'];
        } catch (ClientExceptionInterface $e) {
            $response = $e->getCode() . ' : ' . $e->getMessage();
        }
        return $response;
    }

    public function delete(string $id): string
    {
        try {
            $this->vaultClient->revoke('/secret/metadata/' . $id);
            $response = 'Delete successful';
        } catch (ClientExceptionInterface $e) {
            $response = $e->getCode() . ' : ' . $e->getMessage();
        }
        return $response;
    }
}
