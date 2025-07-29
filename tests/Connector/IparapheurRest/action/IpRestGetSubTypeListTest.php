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
                    '/api/standard/v1/tenant/8a4dba5f-b034-4f92-8625-3aee7be97d46/types/233f806c-9fc3-44db-ab36-739c4909cdc9/subtypes'
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
                'iparapheur_type_id' => '233f806c-9fc3-44db-ab36-739c4909cdc9'
            ]
        );

        $ipRestGetSubTypeList = new IpRestGetSubTypeList($this->getObjectInstancier());
        $ipRestGetSubTypeList->setConnecteurId('iparapheur-rest', $connectorId);

        $result = $ipRestGetSubTypeList->displayAPI();

        static::assertSame([
            '12eff029-ee61-4b9a-816d-1af5cedce3f1' => 'Cachet auto',
            '61c84105-5d7a-4e6e-b673-5bf2fff73563' => 'Cachet manuel',
            'abb28258-6965-4725-8570-ff64a2a02745' => 'Signature',
            '741948ac-855e-4081-9dae-ccc2492a5d54' => 'Visa'
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
            ]
        );

        $ipRestGetSubTypeList = new IpRestGetSubTypeList($this->getObjectInstancier());
        $ipRestGetSubTypeList->setConnecteurId('iparapheur-rest', $connectorId);

        $this->expectException(IpRestException::class);
        $this->expectExceptionMessage("L'entité et le type iparapheur sont obligatoires pour voir la liste des sous-types");

        $ipRestGetSubTypeList->displayAPI();
    }
}
