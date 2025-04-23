<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector\IparapheurRest\action;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pastell\Client\IparapheurV5\ClientFactory;
use Pastell\Connector\IparapheurRest\Action\IpRestGetTypeList;
use Pastell\Connector\IparapheurRest\IpRestException;
use PastellTestCase;
use Psr\Http\Client\ClientInterface;
use UnrecoverableException;

class IpRestGetTypeListTest extends PastellTestCase
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
                    '/api/standard/v1/tenant/8a4dba5f-b034-4f92-8625-3aee7be97d46/types' => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/../fixtures/list_types.json')
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
    public function testIpRestGetTypeList(): void
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

        $ipRestGetTypeList = new IpRestGetTypeList($this->getObjectInstancier());
        $ipRestGetTypeList->setConnecteurId('iparapheur-rest', $connectorId);

        $result = $ipRestGetTypeList->displayAPI();

        static::assertSame([
            '83cf8658-8bef-4e22-84b2-db119d04811d' => 'Cades',
            '233f806c-9fc3-44db-ab36-739c4909cdc9' => 'Pades',
            '80dd7f2e-58c4-4e99-818c-040642f23326' => 'Visa',
            '5d2854db-fae0-4ce9-81df-5e209b7a657c' => 'Xades'
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

        $ipRestGetTypeList = new IpRestGetTypeList($this->getObjectInstancier());
        $ipRestGetTypeList->setConnecteurId('iparapheur-rest', $connectorId);

        $this->expectException(IpRestException::class);
        $this->expectExceptionMessage("L'entité iparapheur est obligatoire pour voir la liste des types");

        $ipRestGetTypeList->displayAPI();
    }
}
