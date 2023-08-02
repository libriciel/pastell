<?php

declare(strict_types=1);

namespace Pastell\Storage;

use Exception;
use GuzzleHttp\Psr7\Uri;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Vault\AuthenticationStrategies\TokenAuthenticationStrategy;
use Vault\Client;
use Vault\Exceptions\RuntimeException;

class VaultAdapter implements StorageInterface
{
    private const RESPONSE_OK = 'ok';
    public const VAULT_ERREUR = 'Erreur Vault : ';
    public const NOT_FOUND_CODE = '404';
    private Client $vaultClient;
    private string $vaultUnsealKey;
    private string $vaultToken;

    public function __construct(string $vaultUrl, string $vaultUnsealKey, string $vaultToken)
    {
        $this->vaultClient = new Client(
            new Uri($vaultUrl),
            new Psr18Client(),
            new RequestFactory(),
            new StreamFactory()
        );
        $this->vaultToken = $vaultToken;
        $this->vaultUnsealKey = $vaultUnsealKey;
    }

    private function isUnsealed(): string
    {
        $response = self::RESPONSE_OK;
        try {
            $this->vaultClient->post('v1/sys/unseal', json_encode(['key' => $this->vaultUnsealKey]));
            $this->vaultClient->setAuthenticationStrategy(new TokenAuthenticationStrategy($this->vaultToken))
                ->authenticate();
        } catch (ClientExceptionInterface | InvalidArgumentException | RuntimeException | Exception $exception) {
            $response = self::VAULT_ERREUR . $exception->getMessage();
        }
        return $response;
    }

    public function write(string $id, string $content): string
    {
        $response = $this->isUnsealed();
        if ($response !== self::RESPONSE_OK) {
            return $response;
        }
        try {
            $response = $this->vaultClient->write('/secret/data/' . $id, ['data' => ['password' => $content]]);
            $response = $response->getData()['created_time'];
        } catch (ClientExceptionInterface $e) {
            $response = self::VAULT_ERREUR . $e->getCode() . ' : ' . $e->getMessage();
        }
        return $response;
    }

    public function read(string $id): string
    {
        $response = $this->isUnsealed();
        if ($response !== self::RESPONSE_OK) {
            return $response;
        }
        try {
            $response = $this->vaultClient->read('/secret/data/' . $id);
            $response = $response->getData()['data']['password'];
        } catch (ClientExceptionInterface $e) {
            $response = self::VAULT_ERREUR . $e->getCode() . ' : ' . $e->getMessage();
        }
        return $response;
    }

    public function delete(string $id): string
    {
        $response = $this->isUnsealed();
        if ($response !== self::RESPONSE_OK) {
            return $response;
        }
        try {
            $this->vaultClient->revoke('/secret/metadata/' . $id);
            $response = 'Delete successful';
        } catch (ClientExceptionInterface $e) {
            $response = self::VAULT_ERREUR . $e->getCode() . ' : ' . $e->getMessage();
        }
        return $response;
    }
}
