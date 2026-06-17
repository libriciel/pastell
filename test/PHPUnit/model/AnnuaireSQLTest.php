<?php

declare(strict_types=1);

class AnnuaireSQLTest extends PastellTestCase
{
    private function getAnnuaireSQL(): AnnuaireSQL
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        return new AnnuaireSQL($sqlQuery);
    }

    private function getAnnuaireGroupsSQL(): AnnuaireGroupeSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
    }

    public function testGetUtilisateur(): void
    {
        $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $result = $this->getAnnuaireSQL()->getUtilisateur(1);
        static::assertCount(1, $result);
        static::assertEquals('eric@sigmalis.com', $result[0]['email']);
    }

    public function testGetFromEmail(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $result = $this->getAnnuaireSQL()->getFromEmail(1, 'eric@sigmalis.com');
        static::assertEquals($id_a, $result);
    }

    public function testDelete(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $this->getAnnuaireSQL()->delete(1, $id_a);
        static::assertEmpty($this->getAnnuaireSQL()->getInfo($id_a));
    }

    public function testGetListeMail(): void
    {
        $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $this->getAnnuaireSQL()->add(1, 'Toto', 'toto@sigmalis.com');
        $result = $this->getAnnuaireSQL()->getListeMail(1, 'E');
        static::assertEquals('eric@sigmalis.com', $result[0]['email']);
    }

    public function testEdit(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $this->getAnnuaireSQL()->edit($id_a, 'toto', 'toto@sigmalis.com');
        $result = $this->getAnnuaireSQL()->getInfo($id_a);
        static::assertEquals('toto', $result['description']);
    }

    public function testUtilisateurList(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $id_g = $this->getAnnuaireGroupsSQL()->add(1, 'test');
        $this->getAnnuaireGroupsSQL()->addToGroupe($id_g, $id_a);
        $result = $this->getAnnuaireSQL()->getUtilisateurList(1, 0, 1, 'eric', $id_g);
        static::assertEquals('eric@sigmalis.com', $result[0]['email']);
    }

    public function testDeleteGroupeClearsGroupeContact(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $id_g = $this->getAnnuaireGroupsSQL()->add(1, 'test');
        $this->getAnnuaireGroupsSQL()->addToGroupe($id_g, $id_a);
        static::assertTrue((bool) $this->getAnnuaireGroupsSQL()->isInGroupe($id_g, $id_a));
        $this->getAnnuaireGroupsSQL()->delete(1, $id_g);
        static::assertSame(0, $this->getAnnuaireGroupsSQL()->isInGroupe($id_g, $id_a));
    }

    public function testNbUtilisateurList(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $id_g = $this->getAnnuaireGroupsSQL()->add(1, 'test');
        $this->getAnnuaireGroupsSQL()->addToGroupe($id_g, $id_a);
        $result = $this->getAnnuaireSQL()->getNbUtilisateur(1, 'eric', $id_g);
        static::assertEquals(1, $result);
    }
}
