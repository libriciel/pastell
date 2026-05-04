<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector\RecupPesS2low;

use ActionExecutorFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pastell\Client\S2low\S2lowClientFactory;
use Psr\Http\Client\ClientInterface;

class RecupPesS2lowConnectorTest extends \PastellTestCase
{
    public function getConnectorId(): int
    {
        $clientInterface = $this->createMock(ClientInterface::class);
        $clientInterface->method('sendRequest')
            ->willReturnCallback(function (Request $request): Response {
                return match ($request->getUri()->getPath()) {
                    '/modules/helios/api/list_pes_aller.php' => new Response(
                        200,
                        [],
                        \file_get_contents(__DIR__ . '/fixtures/list_pes.json'),
                    ),
                    '/modules/helios/helios_download_file.php' => new Response(
                        200,
                        [],
                        file_get_contents(__DIR__ . '/fixtures/pes_aller.xml')
                    ),
                    '/modules/helios/helios_download_acquit.php' => new Response(
                        200,
                        [],
                        file_get_contents(__DIR__ . '/fixtures/pes_acquit.xml')
                    ),
                    default => throw new \RuntimeException('Unknown path : ' . $request->getUri()->getPath()),
                };
            });
        $clientFactory = $this->getObjectInstancier()->getInstance(S2lowClientFactory::class);
        $clientFactory->setClient($clientInterface);

        $connectorId = $this->createConnector('recup-pes-s2low', 'Recup pes s2low')['id_ce'];
        $this->configureConnector(
            $connectorId,
            [
                'url' => 'https://url',
                'start_date' => '2020-01-01',
                'end_date' => '2024-01-01',
                'certificate_password' => '',
                'nb_recup' => '1',
            ]
        );
        return (int)$connectorId;
    }

    public function testFetchPes(): void
    {
        $connectorId = $this->getConnectorId();
        $this->triggerActionOnConnector($connectorId, 'fetch_once');

        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertMatchesRegularExpression('/Création du document lié à la transaction/', $lastMessage);
    }

    public function testFetchPesSkipsDuplicate(): void
    {
        $connectorId = $this->getConnectorId();
        $documentEntite = $this->getObjectInstancier()->getInstance(\DocumentEntite::class);
        $this->triggerActionOnConnector($connectorId, 'fetch_once');
        $this->triggerActionOnConnector($connectorId, 'fetch_once');
        self::assertSame(1, $documentEntite->getNbAll(self::ID_E_COL, 'ls-recup-pes-s2low'));
        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertStringContainsString('déjà importée, ignorée', $lastMessage);
    }

    public function testListPes(): void
    {
        $connectorId = $this->getConnectorId();
        $this->triggerActionOnConnector($connectorId, 'list_pes');

        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertStringContainsString(
            'Transactions entre le 2020-01-01 et le 2024-01-01',
            $lastMessage
        );
        self::assertStringContainsString(
            'Transaction 5',
            $lastMessage
        );
    }
}
