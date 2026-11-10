<?php

namespace Pastell\Client\Crypto;

use GuzzleHttp\Psr7\MultipartStream;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

use function file_get_contents;
use function json_encode;
use function sprintf;

class CryptoClient
{
    /** @var string */
    public const CONTENT_TYPE = 'multipart/form-data';

    /**
     * @var ClientInterface
     */
    private $httpClient;
    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    public function __construct(
        ClientInterface $clientInterface,
        ?RequestFactoryInterface $requestFactory = null
    ) {
        $this->httpClient = $clientInterface;
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
    }

    public function getMultipartStream(string $filepath, array $model): MultipartStream
    {
        return new MultipartStream(
            [
                [
                    'name' => 'file',
                    'contents' => file_get_contents($filepath),
                    'filename' => 'filename',
                ],
                [
                    'name' => 'model',
                    'contents' => json_encode($model),
                ],
            ]
        );
    }

    private function getContentTypeWithBoundary(MultipartStream $stream): string
    {
        return sprintf('%s; boundary="%s"', self::CONTENT_TYPE, $stream->getBoundary());
    }

    /**
     * @throws CryptoClientException
     * @throws ClientExceptionInterface
     */
    public function get(string $endpoint): string
    {
        $request = $this->requestFactory
            ->createRequest('GET', $endpoint);
        $response = $this->httpClient->sendRequest($request);

        $body = (string)$response->getBody();
        if ($response->getStatusCode() !== 200) {
            throw new CryptoClientException($body, $response->getStatusCode());
        }
        return $body;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws CryptoClientException
     */
    public function post(string $endpoint, MultipartStream $stream): string
    {
        $request = $this->requestFactory
            ->createRequest('POST', $endpoint)
            ->withAddedHeader('Content-Type', $this->getContentTypeWithBoundary($stream))
            ->withBody($stream);
        $response = $this->httpClient->sendRequest($request);

        $body = (string)$response->getBody();
        if ($response->getStatusCode() !== 200) {
            throw new CryptoClientException($body, $response->getStatusCode());
        }
        return $body;
    }

    public function cades(): Api\Cades
    {
        return new Api\Cades($this);
    }

    public function xades(): Api\Xades
    {
        return new Api\Xades($this);
    }

    public function pades(): Api\Pades
    {
        return new Api\Pades($this);
    }

    public function version(): Api\Version
    {
        return new Api\Version($this);
    }
}
