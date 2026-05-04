<?php

class AnnuaireSQLTest extends PastellTestCase
{
    /**
     *
     * @return AnnuaireSQL
     */
    private function getAnnuaireSQL()
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        return new AnnuaireSQL($sqlQuery);
    }

    private function getAnnuaireGroupsSQL()
    {
        return new AnnuaireGroupe($this->getObjectInstancier()->getInstance(SQLQuery::class), 1);
    }

    public function testGetUtilisateur()
    {
        $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $result = $this->getAnnuaireSQL()->getUtilisateur(1);
        $this->assertCount(1, $result);
        $this->assertEquals("eric@sigmalis.com", $result[0]['email']);
    }

    public function testGetFromEmail()
    {
        $id_a = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $result = $this->getAnnuaireSQL()->getFromEmail(1, "eric@sigmalis.com");
        $this->assertEquals($id_a, $result);
    }

    public function testUpdate()
    {
        $id_a_1 = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $id_a_2 = $this->getAnnuaireSQL()->add(1, "epommate", "eric@sigmalis.com");
        $this->assertEquals($id_a_1, $id_a_2);
        $result = $this->getAnnuaireSQL()->getInfo($id_a_2);
        $this->assertEquals("epommate", $result["description"]);
    }

    public function testDelete()
    {
        $id_a = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $this->getAnnuaireSQL()->delete(1, $id_a);
        $this->assertEmpty($this->getAnnuaireSQL()->getInfo($id_a));
    }

    public function testGetListeMail()
    {
        $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $this->getAnnuaireSQL()->add(1, "Toto", "toto@sigmalis.com");
        $result = $this->getAnnuaireSQL()->getListeMail(1, "E");
        $this->assertEquals("eric@sigmalis.com", $result[0]['email']);
    }

    public function testEdit()
    {
        $id_a = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $this->getAnnuaireSQL()->edit($id_a, "toto", "toto@sigmalis.com");
        $result = $this->getAnnuaireSQL()->getInfo($id_a);
        $this->assertEquals("toto", $result["description"]);
    }

    public function testUtilisateurList()
    {
        $id_a = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $id_g = $this->getAnnuaireGroupsSQL()->add("test");
        $this->getAnnuaireGroupsSQL()->addToGroupe($id_g, $id_a);
        $result = $this->getAnnuaireSQL()->getUtilisateurList(1, 0, 1, "eric", $id_g);
        $this->assertEquals("eric@sigmalis.com", $result[0]['email']);
    }

    public function testDeleteGroupeClearsGroupeContact(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $id_g = $this->getAnnuaireGroupsSQL()->add('test');
        $this->getAnnuaireGroupsSQL()->addToGroupe($id_g, $id_a);
        $this->assertTrue((bool) $this->getAnnuaireGroupsSQL()->isInGroupe($id_g, $id_a));
        $this->getAnnuaireGroupsSQL()->delete([$id_g]);
        $this->assertSame(0, $this->getAnnuaireGroupsSQL()->isInGroupe($id_g, $id_a));
    }

    public function testNbUtilisateurList()
    {
        $id_a = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $id_g = $this->getAnnuaireGroupsSQL()->add("test");
        $this->getAnnuaireGroupsSQL()->addToGroupe($id_g, $id_a);
        $result = $this->getAnnuaireSQL()->getNbUtilisateur(1, "eric", $id_g);
        $this->assertEquals(1, $result);
    }
}
