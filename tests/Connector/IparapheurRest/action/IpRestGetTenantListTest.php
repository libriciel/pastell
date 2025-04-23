<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector\IparapheurRest\action;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pastell\Client\IparapheurV5\ClientFactory;
use Pastell\Connector\IparapheurRest\Action\IpRestGetTenantList;
use PastellTestCase;
use Psr\Http\Client\ClientInterface;
use UnrecoverableException;

class IpRestGetTenantListTest extends PastellTestCase
{
    /**
     * @throws Exception
     */
    public function testIpRestGetTenantList(): void
    {
        $clientInterface = $this->getMockBuilder(ClientInterface::class)->getMock();
        $clientInterface->method('sendRequest')
            ->willReturnCallback(function (Request $request): Response {
                return match ($request->getUri()->getPath()) {
                    '/auth/realms/api/protocol/openid-connect/token' => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/../fixtures/authenticate_ok.json')
                    ),
                    '/api/standard/v1/tenant' => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/../fixtures/list_tenants.json')
                    ),
                    default => throw new UnrecoverableException('Unknown path : ' . $request->getUri()->getPath()),
                };
            });

        /** @var ClientFactory $clientFactory */
        $clientFactory = $this->getObjectInstancier()->getInstance(ClientFactory::class);
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

        $ipRestGetTenantList = new IpRestGetTenantList($this->getObjectInstancier());
        $ipRestGetTenantList->setConnecteurId('iparapheur-rest', $connectorId);

        $result = $ipRestGetTenantList->displayAPI();

        static::assertSame([
            '8a4dba5f-b034-4f92-8625-3aee7be97d46' => 'Pastell',
            'bc75c516-7fa6-4edd-8a3e-9318d3263996' => 'Pastell 2',
            'a19c2cc6-6923-45cb-9a26-771ea14cd995' => 'Pastell 3',
        ], $result);
    }
}
