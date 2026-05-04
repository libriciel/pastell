<?php

namespace Pastell\Tests\Service\Connecteur;

use ConnecteurEntiteSQL;
use ConnecteurFrequence;
use ConnecteurFrequenceSQL;
use Exception;
use Pastell\Service\Connecteur\ConnecteurDeletionService;
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
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = 'test';
        $connecteurFrequence->id_connecteur = 'test';
        $connecteurFrequence->id_ce = $id_ce;
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_CONNECTEUR;
        $id_cf = $connecteurFrequenceSQL->edit($connecteurFrequence);

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
