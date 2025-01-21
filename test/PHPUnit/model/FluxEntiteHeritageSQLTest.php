<?php

use Pastell\Service\Connecteur\ConnecteurAssociationService;

class FluxEntiteHeritageSQLTest extends PastellTestCase
{
    public function getFluxEntiteHeritageSQL()
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        return new FluxEntiteHeritageSQL($sqlQuery, new FluxEntiteSQL($sqlQuery), new EntiteSQL($sqlQuery));
    }

    public function testGetAll()
    {
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAll(1);
        $this->assertEquals("Fake iParapheur", $all_flux['actes-generique']['signature']['libelle']);
    }

    public function testGetAllWithSameType()
    {
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAllWithSameType(1);
        $this->assertEquals("Fake iParapheur", $all_flux['actes-generique']['signature'][0]['libelle']);
    }

    public function testGetAllNoFlux()
    {
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAll(2);
        $this->assertEmpty($all_flux);
    }

    public function testGetAllNoFluxWithSameType()
    {
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAllWithSameType(2);
        $this->assertEmpty($all_flux);
    }

    public function testSetInherit()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritance(2, "actes-generique");
        $result = $this->getFluxEntiteHeritageSQL()->getInheritance(2);
        $this->assertContains("actes-generique", $result);
    }

    public function testSetInheritNotWithEntiteRacine()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritance(1, "actes-generique");
        $result = $this->getFluxEntiteHeritageSQL()->getInheritance(1);
        $this->assertEmpty($result);
    }

    public function testInherit()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritance(2, "actes-generique");
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAll(2);
        $this->assertEquals("Fake iParapheur", $all_flux['actes-generique']['signature']['libelle']);
    }

    public function testInheritWithSameType()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritance(2, "actes-generique");
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAllWithSameType(2);
        $this->assertEquals("Fake iParapheur", $all_flux['actes-generique']['signature'][0]['libelle']);
    }

    public function testInheritAll()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritanceAllFlux(2);
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAll(2);
        $this->assertEquals("Fake iParapheur", $all_flux['actes-generique']['signature']['libelle']);
    }

    public function testInheritAllWithSameType()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritanceAllFlux(2);
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAllWithSameType(2);
        $this->assertEquals("Fake iParapheur", $all_flux['actes-generique']['signature'][0]['libelle']);
    }

    public function testDeleteInheritAll()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritanceAllFlux(2);
        $this->getFluxEntiteHeritageSQL()->deleteInheritanceAllFlux(2);
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAll(2);
        $this->assertEmpty($all_flux);
    }

    public function testDeleteInheritAllSameType()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritanceAllFlux(2);
        $this->getFluxEntiteHeritageSQL()->deleteInheritanceAllFlux(2);
        $all_flux = $this->getFluxEntiteHeritageSQL()->getAllWithSameType(2);
        $this->assertEmpty($all_flux);
    }

    public function testGetConnecteurId()
    {
        $id_ce = $this->getFluxEntiteHeritageSQL()->getConnecteurId(1, 'actes-generique', 'signature');
        $this->assertEquals(1, $id_ce);
    }

    public function testGetConnecteurIdInherit()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritance(2, "actes-generique");
        $id_ce = $this->getFluxEntiteHeritageSQL()->getConnecteurId(2, 'actes-generique', 'signature');
        $this->assertEquals(1, $id_ce);
    }

    public function testGetConnecteurIdInheritAll()
    {
        $this->getFluxEntiteHeritageSQL()->setInheritanceAllFlux(2);
        $id_ce = $this->getFluxEntiteHeritageSQL()->getConnecteurId(2, 'actes-generique', 'signature');
        $this->assertEquals(1, $id_ce);
    }

    public function testGetConnecteurIdNotInherit()
    {
        $id_ce = $this->getFluxEntiteHeritageSQL()->getConnecteurId(2, 'actes-generique', 'signature');
        $this->assertFalse($id_ce);
    }

    public function testInheritNoFlux()
    {
        $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class)->deleteConnecteur(1, 'mailsec', 'mailsec');
        $this->getFluxEntiteHeritageSQL()->setInheritance(2, "mailsec");
        $result = $this->getFluxEntiteHeritageSQL()->getAllWithSameType(2);
        $this->assertEquals(1, $result['mailsec']['inherited_flux']);
    }

    /*
     * Quand il y a un connecteur et qu'on fait hériter et s'il n'y a pas de connecteur plus haut, alors il n'y a pas de connecteur au final...
     */
    public function testInheritNoFluxBehindConnecteur()
    {
        $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class)->deleteConnecteur(1, 'mailsec', 'mailsec');
        $id_ce = $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class)->addConnecteur(
            2,
            'mailsec',
            'mailsec',
            'connecteur mailsec de test',
            0
        );
        $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class)->addConnecteur(2, 'mailsec', 'mailsec', $id_ce);
        $this->assertEquals($id_ce, $this->getFluxEntiteHeritageSQL()->getConnecteurId(2, 'mailsec', 'mailsec'));
        $this->getFluxEntiteHeritageSQL()->setInheritance(2, "mailsec");
        $this->assertFalse($this->getFluxEntiteHeritageSQL()->getConnecteurId(2, 'mailsec', 'mailsec'));
    }

    public function testToogle()
    {
        $id_ce = $this->getFluxEntiteHeritageSQL()->getConnecteurId(2, 'actes-generique', 'signature');
        $this->assertFalse($id_ce);
        $this->getFluxEntiteHeritageSQL()->toogleInheritance(2, 'actes-generique');
        $id_ce = $this->getFluxEntiteHeritageSQL()->getConnecteurId(2, 'actes-generique', 'signature');
        $this->assertEquals(1, $id_ce);
        $this->getFluxEntiteHeritageSQL()->toogleInheritance(2, 'actes-generique');
        $id_ce = $this->getFluxEntiteHeritageSQL()->getConnecteurId(2, 'actes-generique', 'signature');
        $this->assertFalse($id_ce);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testWithTwoSameConnecteurType()
    {
        $info = $this->createConnector('test', 'test 2', 1);
        $connecteurAssociationService = $this->getObjectInstancier()->getInstance(ConnecteurAssociationService::class);
        $connecteurAssociationService->addConnecteurAssociation(1, $info['id_ce'], 'test', 0, 'test', 1);
        $this->assertCount(2, $this->getFluxEntiteHeritageSQL()->getAllWithSameType(1)['test']['test']);
    }
}
