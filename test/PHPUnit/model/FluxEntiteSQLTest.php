<?php

class FluxEntiteSQLTest extends PastellTestCase
{
    private function getFluxEntiteSQL(): FluxEntiteSQL
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        return new FluxEntiteSQL($sqlQuery);
    }

    public function testGetConnecteur()
    {
        $connecteur = $this->getFluxEntiteSQL()->getConnecteur(1, 'actes-generique', 'signature');
        $this->assertEquals("Fake iParapheur", $connecteur['libelle']);
    }

    public function testGetConnecteurGlobal()
    {
        $connecteur = $this->getFluxEntiteSQL()->getConnecteur(0, 'global', 'horodateur');
        $this->assertEquals("Horodateur interne par défaut", $connecteur['libelle']);
    }

    public function testgetConnecteurId()
    {
        $id_ce = $this->getFluxEntiteSQL()->getConnecteurId(1, 'actes-generique', 'signature');
        $this->assertEquals(1, $id_ce);
    }

    public function testGetConnecteurById()
    {
        $connecteur = $this->getFluxEntiteSQL()->getConnecteurById(1);
        $this->assertEquals(1, $connecteur['id_ce']);
    }

    public function testGetAllWithSameType()
    {
        $result = $this->getFluxEntiteSQL()->getAllWithSameType(1);
        $this->assertEquals("Fake iParapheur", $result['actes-generique']['signature'][0]['libelle']);
    }

    public function testGetAllFluxEntite()
    {
        $result = $this->getFluxEntiteSQL()->getAllFluxEntite(1);
        $this->assertEquals(1, $result[0]['id_ce']);
    }

    public function testGetAllFluxEntiteWithFlux()
    {
        $result = $this->getFluxEntiteSQL()->getAllFluxEntite(1, 'actes-generique');
        $this->assertEquals(1, $result[0]['id_ce']);
    }

    public function testGetAllFluxEntiteWithType()
    {
        $result = $this->getFluxEntiteSQL()->getAllFluxEntite(1, false, 'signature');
        $this->assertEquals(1, $result[0]['id_ce']);
    }

    public function testGetAllFluxEntiteWithTypeAndFlux()
    {
        $result = $this->getFluxEntiteSQL()->getAllFluxEntite(1, 'actes-generique', 'signature');
        $this->assertEquals(1, $result[0]['id_ce']);
    }

    public function testAddConnecteur()
    {
        $id_fe = $this->getFluxEntiteSQL()->addConnecteur(1, 'mailsec', 'mailsec', 12);
        $this->assertEquals(10, $id_fe);
    }

    public function testDeleteConnecteur()
    {
        $this->getFluxEntiteSQL()->deleteConnecteur(1, 'actes-generique', 'signature');
        $connecteur = $this->getFluxEntiteSQL()->getConnecteur(1, 'actes-generique', 'signature');
        $this->assertEmpty($connecteur);
    }

    public function testRemoveConnecteur()
    {
        $this->getFluxEntiteSQL()->removeConnecteur(1);
        $connecteur = $this->getFluxEntiteSQL()->getConnecteur(1, 'actes-generique', 'signature');
        $this->assertEmpty($connecteur);
    }

    public function testGetFluxByConnecteur()
    {
        $result = $this->getFluxEntiteSQL()->getFluxByConnecteur(1);
        $this->assertEquals(['actes-generique'], $result);
    }

    public function testGetUsedByConnecteur()
    {
        $result = $this->getFluxEntiteSQL()->getUsedByConnecteur(1, 'actes-generique', 1);
        $this->assertEquals(1, $result[0]['id_fe']);
    }

    public function testGetAssociations(): void
    {
        $associations = $this->getFluxEntiteSQL()->getAssociations('actes-generique');
        $this->assertCount(5, $associations);
    }

    public function testGetAssociatedConnectorsById(): void
    {
        $associatedConnectors = $this->getFluxEntiteSQL()->getAssociatedConnectorsById('fakeTdt');
        $this->assertCount(1, $associatedConnectors);
        $this->assertSame(2, $associatedConnectors[0]['id_ce']);
    }
}
