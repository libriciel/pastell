<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector\RecupActesS2low;

use ActionExecutorFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pastell\Client\S2low\S2lowClientFactory;
use Psr\Http\Client\ClientInterface;

class RecupActesS2lowConnectorTest extends \PastellTestCase
{
    public function getConnectorId(): int
    {
        $clientInterface = $this->createMock(ClientInterface::class);
        $clientInterface->method('sendRequest')
            ->willReturnCallback(function (Request $request): Response {
                return match ($request->getUri()->getPath()) {
                    '/modules/actes/api/list_actes.php' => new Response(
                        200,
                        [],
                        \file_get_contents(__DIR__ . '/fixtures/list_actes.json'),
                    ),
                    '/modules/actes/actes_transac_get_files_list.php' => new Response(
                        200,
                        [],
                        file_get_contents(__DIR__ . '/fixtures/file_list.json')
                    ),
                    '/modules/actes/actes_download_file.php' => new Response(
                        200,
                        [],
                        file_get_contents(__DIR__ . '/fixtures/acte.xml')
                    ),
                    '/modules/actes/actes_transac_get_ARActe.php' => new Response(
                        200,
                        [],
                        file_get_contents(__DIR__ . '/fixtures/aractes.xml')
                    ),
                    default => throw new \RuntimeException('Unknown path : ' . $request->getUri()->getPath()),
                };
            });
        $clientFactory = $this->getObjectInstancier()->getInstance(S2lowClientFactory::class);
        $clientFactory->setClient($clientInterface);

        $connectorId = $this->createConnector('recup-actes-s2low', 'Recup actes s2low')['id_ce'];
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

    public function testFetchActes(): void
    {
        $connectorId = $this->getConnectorId();
        $this->triggerActionOnConnector($connectorId, 'fetch_once');

        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertMatchesRegularExpression('/Création du document lié à la transaction/', $lastMessage);
    }

    public function testFetchActesSkipsDuplicate(): void
    {
        $connectorId = $this->getConnectorId();
        $documentEntite = $this->getObjectInstancier()->getInstance(\DocumentEntite::class);
        $this->triggerActionOnConnector($connectorId, 'fetch_once');
        $this->triggerActionOnConnector($connectorId, 'fetch_once');
        self::assertSame(1, $documentEntite->getNbAll(self::ID_E_COL, 'ls-recup-actes-s2low'));
        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertStringContainsString('déjà importée, ignorée', $lastMessage);
    }

    public function testListActes(): void
    {
        $connectorId = $this->getConnectorId();
        $this->triggerActionOnConnector($connectorId, 'list_actes');

        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertStringContainsString(
            'Transactions entre le 2020-01-01 et le 2024-01-01',
            $lastMessage
        );
        self::assertStringContainsString(
            'Transaction 7 - Acte 20240507_TEST01',
            $lastMessage
        );
    }
}
