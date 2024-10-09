<?php

declare(strict_types=1);

namespace Pastell\Client\S2low;

use GuzzleHttp\Psr7\MultipartStream;
use Http\Discovery\Psr17FactoryDiscovery;
use IparapheurV5Client\UrlEncoder;
use Pastell\Client\S2low\Api\Actes;
use Pastell\Client\S2low\Api\Connexion;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class S2lowClient
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;

    public function __construct(
        ClientInterface $clientInterface,
        RequestFactoryInterface $requestFactory = null,
    ) {
        $this->httpClient = $clientInterface;
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function get(string $endpoint, object $queryObject = null): string
    {
        if ($queryObject) {
            $encoders = [new UrlEncoder()];
            $normalizers = [new ObjectNormalizer(nameConverter: new CamelCaseToSnakeCaseNameConverter())];
            $serializer = (new Serializer($normalizers, $encoders));
            $queryArray = get_object_vars($queryObject);
            $filteredQueryArray = array_filter($queryArray, static function ($value) {
                return $value !== null;
            });
            $filteredQueryObject = (object) $filteredQueryArray;
            $queryPart = $serializer->serialize($filteredQueryObject, 'url');
            $endpoint .= "?$queryPart";
        }
        $request = $this->requestFactory
            ->createRequest('GET', $endpoint);
        $response = $this->httpClient->sendRequest($request);

        $body = (string)$response->getBody();
        $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');

        if ($response->getStatusCode() !== 200 || str_starts_with($body, 'KO')) {
            throw new S2lowClientException($body, $response->getStatusCode());
        }
        return $body;
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function post(string $endpoint, array $data): string
    {
        $multipart = [];
        foreach ($data as $name => $value) {
            $multipart[] = [
                'name'     => $name,
                'contents' => $value
            ];
        }

        $stream = new MultipartStream($multipart);
        $contentType = sprintf('%s; boundary="%s"', 'multipart/form-data', $stream->getBoundary());

        $request = $this->requestFactory
            ->createRequest('POST', $endpoint)
            ->withAddedHeader('Content-Type', $contentType)
            ->withBody($stream);
        $response = $this->httpClient->sendRequest($request);

        $body = (string)$response->getBody();
        if ($response->getStatusCode() !== 200) {
            throw new S2lowClientException($body, $response->getStatusCode());
        }
        return $body;
    }

    public function actes(): Actes
    {
        return new Actes($this);
    }

    public function connexion(): Connexion
    {
        return new Connexion($this);
    }
}
