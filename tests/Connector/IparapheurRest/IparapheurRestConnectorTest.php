<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector\IparapheurRest;

use ActionExecutorFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pastell\Client\IparapheurV5\ApiClientFactory;
use PastellTestCase;
use Psr\Http\Client\ClientInterface;
use UnrecoverableException;

class IparapheurRestConnectorTest extends PastellTestCase
{
    public function getConnectorId(): int
    {
        $clientInterface = $this->getMockBuilder(ClientInterface::class)->getMock();
        $clientInterface->method('sendRequest')
            ->willReturnCallback(function (Request $request): Response {
                return match ($request->getUri()->getPath()) {
                    '/auth/realms/api/protocol/openid-connect/token' => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/fixtures/authenticate_ok.json')
                    ),
                    '/api/standard/v1/tenant' => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/fixtures/list_tenants.json')
                    ),
                    default => throw new UnrecoverableException('Unknown path : ' . $request->getUri()->getPath()),
                };
            });
        $clientFactory = $this->getObjectInstancier()->getInstance(ApiClientFactory::class);
        $clientFactory->setClientInterface($clientInterface);

        $connectorId = $this->createConnector('iparapheur-rest', 'iparapheur REST')['id_ce'];
        $this->configureConnector(
            $connectorId,
            [
                'url' => 'https://url',
                'username' => 'username-iparapheur',
                'password' => 'password-iparapheur',
            ]
        );
        return (int)$connectorId;
    }

    public function testConnexion(): void
    {
        $connectorId = $this->getConnectorId();
        $this->triggerActionOnConnector($connectorId, 'test-connexion');

        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertSame('Liste des entités iparapheur : Pastell, Pastell 2, Pastell 3', $lastMessage);
    }
}
