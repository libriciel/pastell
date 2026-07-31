<?php

declare(strict_types=1);

namespace Pastell\Tests\Step\UpdateStatusPesS2low\Action;

use NotFoundException;
use Pastell\Client\S2low\S2lowClientFactory;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use PastellTestCase;
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use RoleSQL;
use RuntimeException;

final class ChangeStatusActionTest extends PastellTestCase
{
    private const CONNECTOR_RECUP_PES_S2LOW = 'recup-pes-s2low';
    private const FLUX_RECUP_PES_S2LOW = 'ls-recup-pes-s2low';
    private const CHANGE_STATUS_ACTION = 'change-status-transaction_1';
    private const CHANGE_STATUS_ERROR = 'change-status-error_1';


    private function getConnector(): void
    {
        $clientInterface = $this->createMock(ClientInterface::class);
        $clientInterface->method('sendRequest')
            ->willReturnCallback(function (Request $request): Response {
                return match ($request->getUri()->getPath()) {
                    '/modules/helios/helios_transac_change_status_sae.php' => new Response(
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

        $connectorId = $this->createConnector(self::CONNECTOR_RECUP_PES_S2LOW, 'Recup pes s2low')['id_ce'];
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
        $this->associateFluxWithConnector($connectorId, self::FLUX_RECUP_PES_S2LOW, self::CONNECTOR_RECUP_PES_S2LOW);
    }

    /**
     * @throws NotFoundException
     */
    public function testChangeStatusError(): void
    {
        $this->getConnector();

        /** @var RoleSQL $roleSQL */
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSQL->addDroit('admin', DroitService::getDroitFor(self::FLUX_RECUP_PES_S2LOW, DroitType::LECTURE));
        $roleSQL->addDroit('admin', DroitService::getDroitFor(self::FLUX_RECUP_PES_S2LOW, DroitType::EDITION));

        $documentId = $this->createDocument(self::FLUX_RECUP_PES_S2LOW)['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($documentId);
        $donneesFormulaire->setTabData([
            'transaction_id' => '2007',
            'update_status_pes_s2low_status_1' => '19',
        ]);
        $this->assertLastMessage("Création du document");

        $this->triggerActionOnDocument($documentId, self::CHANGE_STATUS_ACTION);
        $this->assertLastDocumentAction(self::CHANGE_STATUS_ERROR, $documentId);
        $this->assertLastMessage(
            '{"status":"error","error-message":"Impossible de changer le status de la transaction"}'
        );
    }
}
