<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector\IparapheurRest\action;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pastell\Client\IparapheurV5\ApiClientFactory;
use Pastell\Connector\IparapheurRest\Action\IpRestGetSubTypeList;
use Pastell\Connector\IparapheurRest\IpRestException;
use PastellTestCase;
use Psr\Http\Client\ClientInterface;
use UnrecoverableException;

class IpRestGetSubTypeListTest extends PastellTestCase
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
                    '/api/standard/v1/tenant/8a4dba5f-b034-4f92-8625-3aee7be97d46/desk/429db3e9-c419-4e6a-87d9-1348c63cf2b7/types/233f806c-9fc3-44db-ab36-739c4909cdc9/subtypes/creation-allowed'
                    => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/../fixtures/list_subtypes.json')
                    ),
                    default => throw new UnrecoverableException('Unknown path : ' . $request->getUri()->getPath()),
                };
            });

        /** @var ApiClientFactory $clientFactory */
        $clientFactory = $this->getObjectInstancier()->getInstance(ApiClientFactory::class);
        $clientFactory->setClientInterface($clientInterface);
    }

    /**
     * @throws Exception
     */
    public function testIpRestGetSubTypeList(): void
    {
        $this->setClient();

        $connectorId = $this->createConnector('iparapheur-rest', 'iparapheur REST')['id_ce'];
        $this->configureConnector(
            $connectorId,
            [
                'url' => 'https://url',
                'username' => 'username-iparapheur',
                'password' => 'password-iparapheur',
                'tenant_id' => '8a4dba5f-b034-4f92-8625-3aee7be97d46',
                'desk_id' => '429db3e9-c419-4e6a-87d9-1348c63cf2b7',
                'iparapheur_type_id' => '233f806c-9fc3-44db-ab36-739c4909cdc9'
            ]
        );

        $ipRestGetSubTypeList = new IpRestGetSubTypeList($this->getObjectInstancier());
        $ipRestGetSubTypeList->setConnecteurId('iparapheur-rest', $connectorId);

        $result = $ipRestGetSubTypeList->displayAPI();

        static::assertSame([
            0 => 'Cachet auto',
            1 => 'Cachet manuel',
            2 => 'Signature',
            3 => 'Visa'
        ], $result);
    }

    /**
     * @throws Exception
     */
    public function testCheckAddIParapeurTypeId(): void
    {
        $this->setClient();

        $connectorId = $this->createConnector('iparapheur-rest', 'iparapheur REST')['id_ce'];
        $this->configureConnector(
            $connectorId,
            [
                'url' => 'https://url',
                'username' => 'username-iparapheur',
                'password' => 'password-iparapheur',
                'tenant_id' => '8a4dba5f-b034-4f92-8625-3aee7be97d46',
                'desk_id' => '429db3e9-c419-4e6a-87d9-1348c63cf2b7',
            ]
        );

        $ipRestGetSubTypeList = new IpRestGetSubTypeList($this->getObjectInstancier());
        $ipRestGetSubTypeList->setConnecteurId('iparapheur-rest', $connectorId);

        $this->expectException(IpRestException::class);
        $this->expectExceptionMessage(
            "L'entité, le bureau et le type iparapheur sont obligatoires pour voir la liste des sous-types"
        );

        $ipRestGetSubTypeList->displayAPI();
    }
}
