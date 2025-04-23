<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector\IparapheurRest\action;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pastell\Client\IparapheurV5\ClientFactory;
use Pastell\Connector\IparapheurRest\Action\IpRestGetDeskList;
use Pastell\Connector\IparapheurRest\IpRestException;
use PastellTestCase;
use Psr\Http\Client\ClientInterface;
use UnrecoverableException;

class IpRestGetDeskListTest extends PastellTestCase
{
    private function setClient(): void
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
                    '/api/standard/v1/tenant/8a4dba5f-b034-4f92-8625-3aee7be97d46/desk' => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/../fixtures/list_user_desks.json')
                    ),
                    default => throw new UnrecoverableException('Unknown path : ' . $request->getUri()->getPath()),
                };
            });

        /** @var ClientFactory $clientFactory */
        $clientFactory = $this->getObjectInstancier()->getInstance(ClientFactory::class);
        $clientFactory->setClientInterface($clientInterface);
    }

    /**
     * @throws Exception
     */
    public function testIpRestGetDeskList(): void
    {
        $this->setClient();

        $connectorId = $this->createConnector('iparapheur-rest', 'iparapheur REST')['id_ce'];
        $this->configureConnector(
            $connectorId,
            [
                'url' => 'https://url',
                'username' => 'username-iparapheur',
                'password' => 'password-iparapheur',
                'tenant_id' => '8a4dba5f-b034-4f92-8625-3aee7be97d46'
            ]
        );

        $ipRestGetDeskList = new IpRestGetDeskList($this->getObjectInstancier());
        $ipRestGetDeskList->setConnecteurId('iparapheur-rest', $connectorId);

        $result = $ipRestGetDeskList->displayAPI();

        static::assertSame([
            '429db3e9-c419-4e6a-87d9-1348c63cf2b7' => 'bureau1',
            '71903116-a21a-4304-949a-9e63ec1c7935' => 'bureau2',
            '812a615a-05b1-48d9-8e68-71d96087ed0e' => 'bureau3',
        ], $result);
    }

    /**
     * @throws Exception
     */
    public function testCheckAddTenantId(): void
    {
        $this->setClient();

        $connectorId = $this->createConnector('iparapheur-rest', 'iparapheur REST')['id_ce'];
        $this->configureConnector(
            $connectorId,
            [
                'url' => 'https://url',
                'username' => 'username-iparapheur',
                'password' => 'password-iparapheur',
            ]
        );

        $ipRestGetDeskList = new IpRestGetDeskList($this->getObjectInstancier());
        $ipRestGetDeskList->setConnecteurId('iparapheur-rest', $connectorId);

        $this->expectException(IpRestException::class);
        $this->expectExceptionMessage("L'entité iparapheur est obligatoire pour voir la liste des bureaux");

        $ipRestGetDeskList->displayAPI();
    }
}
