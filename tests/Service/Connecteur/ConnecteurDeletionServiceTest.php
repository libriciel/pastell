<?php

namespace Pastell\Tests\Service\Connecteur;

use ConnecteurEntiteSQL;
use ConnecteurFrequenceSQL;
use Exception;
use Pastell\Service\Connecteur\ConnecteurDeletionService;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceActionType;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceData;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceService;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceType;
use PastellTestCase;

final class ConnecteurDeletionServiceTest extends PastellTestCase
{
    /**
     * @var ConnecteurDeletionService
     */
    private $connectorDeletionService;
    /**
     * @var ConnecteurEntiteSQL
     */
    private $connectorEntitySql;

    protected function setUp(): void
    {
        $this->connectorDeletionService = $this->getObjectInstancier()->getInstance(ConnecteurDeletionService::class);
        $this->connectorEntitySql = $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class);
        parent::setUp();
    }

    /**
     * @throws Exception
     */
    public function testRemoveOneDisassociatedConnector(): void
    {
        $testConnectors = $this->connectorEntitySql->getAllByConnecteurId('test');
        $this->assertCount(2, $testConnectors);
        $this->connectorDeletionService->deleteConnecteur($testConnectors[0]['id_ce']);

        $testConnectors = $this->connectorEntitySql->getAllByConnecteurId('test');
        $this->assertCount(1, $testConnectors);
    }

    public function testRemoveAssociatedConnector(): void
    {
        $testConnectors = $this->connectorEntitySql->getAllByConnecteurId('test');
        $this->assertCount(2, $testConnectors);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Ce connecteur est utilisé par des flux :  test');
        $this->connectorDeletionService->deleteConnecteur($testConnectors[1]['id_ce']);
    }

    /**
     * @throws Exception
     */
    public function testDeleteConnecteurAlsoDeletesFrequences(): void
    {
        $testConnectors = $this->connectorEntitySql->getAllByConnecteurId('test');
        $id_ce = $testConnectors[0]['id_ce'];

        $connecteurFrequenceSQL = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);
        $id_cf = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceService::class)->save(
            new ConnecteurFrequenceData(
                type_connecteur: ConnecteurFrequenceType::ENTITE->value,
                famille_connecteur: 'test',
                id_connecteur: 'test',
                id_ce: (int)$id_ce,
                action_type: ConnecteurFrequenceActionType::CONNECTEUR->value,
            )
        );

        $this->connectorDeletionService->deleteConnecteur($id_ce);

        $this->assertEmpty($connecteurFrequenceSQL->getInfo($id_cf));
    }

    /**
     * @throws Exception
     */
    public function testDisassociateAndRemoveConnector(): void
    {
        $testConnectors = $this->connectorEntitySql->getAllByConnecteurId('test');
        $this->assertCount(2, $testConnectors);
        $connectorId = $testConnectors[1]['id_ce'];
        $this->connectorDeletionService->disassociate($connectorId);
        $this->connectorDeletionService->deleteConnecteur($connectorId);

        $testConnectors = $this->connectorEntitySql->getAllByConnecteurId('test');
        $this->assertCount(1, $testConnectors);
    }
}
