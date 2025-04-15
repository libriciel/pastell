<?php

declare(strict_types=1);

namespace Pastell\Tests\Step\UpdateStatusActesS2low\Action;

use NotFoundException;
use Pastell\Client\S2low\S2lowClientFactory;
use PastellTestCase;
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use RoleSQL;
use RuntimeException;

final class ChangeStatusActionTest extends PastellTestCase
{
    private const CONNECTOR_RECUP_ACTES_S2LOW = 'recup-actes-s2low';
    private const FLUX_RECUP_ACTES_S2LOW = 'ls-recup-actes-s2low';
    private const CHANGE_STATUS_ACTION = 'change-status-transaction_1';
    private const CHANGE_STATUS_ERROR = 'change-status-error_1';


    private function getConnector(): void
    {
        $clientInterface = $this->createMock(ClientInterface::class);
        $clientInterface->method('sendRequest')
            ->willReturnCallback(function (Request $request): Response {
                return match ($request->getUri()->getPath()) {
                    '/modules/actes/api/actes_sae_status.php' => new Response(
                        200,
                        [],
                        json_encode([
                            'status' => 'error',
                            'error-message' => 'Impossible de changer le status de la transaction',
                        ], JSON_THROW_ON_ERROR)
                    ),
                    default => throw new RuntimeException('Unknown path : ' . $request->getUri()->getPath()),
                };
            });

        $clientFactory = $this->getObjectInstancier()->getInstance(S2lowClientFactory::class);
        $clientFactory->setClient($clientInterface);

        $connectorId = $this->createConnector(self::CONNECTOR_RECUP_ACTES_S2LOW, 'Recup actes s2low')['id_ce'];
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
        $this->associateFluxWithConnector($connectorId, self::FLUX_RECUP_ACTES_S2LOW, self::CONNECTOR_RECUP_ACTES_S2LOW);
    }

    /**
     * @throws NotFoundException
     */
    public function testChangeStatusError(): void
    {
        $this->getConnector();

        /** @var RoleSQL $roleSQL */
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSQL->addDroit('admin', self::FLUX_RECUP_ACTES_S2LOW . ":lecture");
        $roleSQL->addDroit('admin', self::FLUX_RECUP_ACTES_S2LOW . ":edition");

        $documentId = $this->createDocument(self::FLUX_RECUP_ACTES_S2LOW)['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($documentId);
        $donneesFormulaire->setTabData([
            'transaction_id' => '56',
            'update_status_actes_s2low_status_1' => '12',
        ]);
        $this->assertLastMessage("Création du document");

        $this->triggerActionOnDocument($documentId, self::CHANGE_STATUS_ACTION);
        $this->assertLastDocumentAction(self::CHANGE_STATUS_ERROR, $documentId);
        $this->assertLastMessage(
            '{"status":"error","error-message":"Impossible de changer le status de la transaction"}'
        );
    }
}
