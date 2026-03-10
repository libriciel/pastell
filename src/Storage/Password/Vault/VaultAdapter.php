<?php

declare(strict_types=1);

namespace Pastell\Storage\Password\Vault;

use Exception;
use GuzzleHttp\Psr7\Uri;
use Pastell\Storage\Password\PasswordStorageInterface;
use Pastell\Storage\Password\Vault\Exceptions\VaultIdNotFoundException;
use Pastell\Storage\Password\Vault\Exceptions\VaultKvEngineNotMountedException;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Vault\AuthenticationStrategies\TokenAuthenticationStrategy;
use Vault\Client;
use Vault\Exceptions\RequestException;
use Vault\Exceptions\RuntimeException;

class VaultAdapter implements PasswordStorageInterface
{
    public const int NOT_FOUND_CODE = 404;
    private Client $vaultClient;
    private string $vaultUnsealKey;
    private string $vaultToken;

    public function __construct(string $vaultUrl, string $vaultUnsealKey, string $vaultToken)
    {
        $httpClient = new Psr18Client();
        $this->vaultClient = new Client(
            new Uri($vaultUrl),
            $httpClient,
            $httpClient,
            $httpClient
        );
        $this->vaultToken = $vaultToken;
        $this->vaultUnsealKey = $vaultUnsealKey;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \JsonException
     */
    private function unseal(): void
    {
        $this->vaultClient->post('v1/sys/unseal', json_encode(['key' => $this->vaultUnsealKey], JSON_THROW_ON_ERROR));
        $this->vaultClient->setAuthenticationStrategy(new TokenAuthenticationStrategy($this->vaultToken))
            ->authenticate();
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws VaultKvEngineNotMountedException
     * @throws \JsonException
     */
    public function write(string $id, string $content): string
    {
        $this->unseal();
        try {
            $response = $this->vaultClient->write('/secret/data/' . $id, ['data' => ['password' => $content]]);
        } catch (RequestException $e) {
            if ($e->getCode() === self::NOT_FOUND_CODE) {
                throw new VaultKvEngineNotMountedException(
                    'Le moteur KV Vault n\'est pas monté sur /secret.'
                );
            }
            throw $e;
        }
        return $response->getData()['created_time'];
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws Exception
     */
    public function read(string $id): string
    {
        $this->unseal();
        try {
            $response = $this->vaultClient->read('/secret/data/' . $id);
            $password = $response->getData()['data']['password'];
        } catch (Exception $e) {
            if ($e->getCode() === self::NOT_FOUND_CODE) {
                throw new VaultIdNotFoundException($e->getMessage());
            }
            throw $e;
        }
        return $password;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \JsonException
     */
    public function delete(string $id): string
    {
        $this->unseal();
        $this->vaultClient->revoke('/secret/metadata/' . $id);
        return 'Delete successful';
    }
}
