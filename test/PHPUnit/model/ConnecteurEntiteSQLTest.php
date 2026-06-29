<?php

class ConnecteurEntiteSQLTest extends PastellTestCase
{
    /**
     *
     * @return ConnecteurEntiteSQL
     */
    private function getConnecteurEntiteSQL()
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        return new ConnecteurEntiteSQL($sqlQuery);
    }

    public function testGetAll()
    {
        $result = $this->getConnecteurEntiteSQL()->getAll(1);
        $this->assertEquals("Fake GED", $result[2]['libelle']);
    }

    public function testGetAllLocalByIde()
    {
        $result = $this->getConnecteurEntiteSQL()->getAllLocalByIde(1);
        $this->assertEquals("Fake GED", $result[2]['libelle']);
    }

    public function testAddConnecteur()
    {
        $id_ce = $this->getConnecteurEntiteSQL()->addConnecteur(
            1,
            'mailsec',
            'mailsec',
            'Mail sécurisé',
            0
        );
        $this->assertEquals(14, $id_ce);
    }

    public function testGetInfo()
    {
        $result = $this->getConnecteurEntiteSQL()->getInfo(1);
        $this->assertEquals('Fake iParapheur', $result['libelle']);
    }

    public function testDelete()
    {
        $this->getConnecteurEntiteSQL()->delete(1);
        $this->assertCount(11, $this->getConnecteurEntiteSQL()->getAll(1));
    }

    public function testEdit()
    {
        $new_libelle = "***test***";
        $this->getConnecteurEntiteSQL()->edit(1, $new_libelle);
        $result = $this->getConnecteurEntiteSQL()->getInfo(1);
        $this->assertEquals($new_libelle, $result['libelle']);
    }

    public function testGetDisponible()
    {
        $result = $this->getConnecteurEntiteSQL()->getDisponible(1, 'signature');
        $this->assertEquals('Fake iParapheur', $result[0]['libelle']);
    }

    public function testGetGlobal()
    {
        $result = $this->getConnecteurEntiteSQL()->getGlobal('horodateur-interne');
        $this->assertEquals(10, $result);
    }

    public function testGetDisponibleUsed()
    {
        $info = $this->getConnecteurEntiteSQL()->getDisponibleUsed(1, 'mailsec');
        $this->assertEquals("Mail securise", $info[0]['libelle']);
    }

    public function testGetDisponibleLocalUsed()
    {
        $info = $this->getConnecteurEntiteSQL()->getDisponibleUsedLocal("mailsec");
        $this->assertEquals("Mail securise", $info[0]['libelle']);
    }

    public function testGetOne()
    {
        $result = $this->getConnecteurEntiteSQL()->getOne('fakeIparapheur');
        $this->assertEquals(1, $result);
    }

    public function testGetAllById()
    {
        $result = $this->getConnecteurEntiteSQL()->getAllById('fakeIparapheur');
        $this->assertEquals('Fake iParapheur', $result[0]['libelle']);
    }

    public function testGetByType()
    {
        $result = $this->getConnecteurEntiteSQL()->getByType(1, 'signature');
        $this->assertEquals('Fake iParapheur', $result[0]['libelle']);
    }

    public function testGetAllId()
    {
        $result = $this->getConnecteurEntiteSQL()->getAllId();
        $this->assertEquals('fakeIparapheur', $result[0]['id_connecteur']);
    }

    public function testListNotUsed()
    {
        $result = $this->getConnecteurEntiteSQL()->listNotUsed(1);
        $this->assertEquals('SEDA CG86', $result[0]['libelle']);
    }

    public function testListNotUsedGlobal()
    {
        $result = $this->getConnecteurEntiteSQL()->listNotUsed(0);
        $this->assertEquals('SEDA CG86', $result[0]['libelle']);
    }

    public function testGetAllUsed()
    {
        $this->assertCount(
            12,
            array_merge(
                $this->getConnecteurEntiteSQL()->getAllUsedByScope(false),
                $this->getConnecteurEntiteSQL()->getAllUsedByScope(true)
            )
        );
    }

    public function testIsConnectorInEntityScope(): void
    {
        $connecteurEntiteSQL = $this->getConnecteurEntiteSQL();

        // A connector owned by the entity itself (id_ce 1 belongs to entity 1)
        static::assertTrue($connecteurEntiteSQL->isConnectorInEntityScope(1, 1));

        // A connector owned by an ancestor: entity 2 is a child of entity 1
        static::assertTrue($connecteurEntiteSQL->isConnectorInEntityScope(1, 2));

        // A global/root connector (id_ce 10 belongs to entity 0) is in scope for any entity
        static::assertTrue($connecteurEntiteSQL->isConnectorInEntityScope(10, 1));
        static::assertTrue($connecteurEntiteSQL->isConnectorInEntityScope(10, 2));
        // ... including the root entity 0 itself (global connector association)
        static::assertTrue($connecteurEntiteSQL->isConnectorInEntityScope(10, 0));

        // A connector owned by a descendant must NOT be in the ancestor's scope (IDOR property)
        $childConnectorId = $this->createConnector('fakeIparapheur', 'Connecteur entité 2', 2)['id_ce'];
        static::assertFalse($connecteurEntiteSQL->isConnectorInEntityScope($childConnectorId, 1));
        // ... but it is in scope for its own entity
        static::assertTrue($connecteurEntiteSQL->isConnectorInEntityScope($childConnectorId, 2));

        // A non-existing connector is never in scope
        static::assertFalse($connecteurEntiteSQL->isConnectorInEntityScope(99999, 1));

        // Reproduce a real install: the root entity 0 has no entite_ancetre row
        // (it is never seeded automatically). A global/root connector must still be
        // in scope for entity 0, while a non-root connector must not.
        $this->getObjectInstancier()
            ->getInstance(SQLQuery::class)
            ->query('DELETE FROM entite_ancetre WHERE id_e = 0');
        static::assertTrue($connecteurEntiteSQL->isConnectorInEntityScope(10, 0));
        static::assertFalse($connecteurEntiteSQL->isConnectorInEntityScope(1, 0));
    }
}
