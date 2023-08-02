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
    public const NOT_FOUND_CODE = 404;
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

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function unseal(): void
    {
        $this->vaultClient->post('v1/sys/unseal', json_encode(['key' => $this->vaultUnsealKey]));
        $this->vaultClient->setAuthenticationStrategy(new TokenAuthenticationStrategy($this->vaultToken))
            ->authenticate();
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function write(string $id, string $content): string
    {
        $this->unseal();
        $response = $this->vaultClient->write('/secret/data/' . $id, ['data' => ['password' => $content]]);
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
     */
    public function delete(string $id): string
    {
        $this->unseal();
        $this->vaultClient->revoke('/secret/metadata/' . $id);
        return 'Delete successful';
    }
}
