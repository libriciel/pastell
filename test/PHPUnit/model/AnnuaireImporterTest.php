<?php

class AnnuaireImporterTest extends PastellTestCase
{
    private function getAnnuaireSQL()
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        return new AnnuaireSQL($sqlQuery);
    }

    private function getAnnuaireGroupsSQL()
    {
        return new AnnuaireGroupe($this->getObjectInstancier()->getInstance(SQLQuery::class), 1);
    }

    private function annuaire_import($data)
    {
        $csv = new CSV();
        $annuaireImporter = new AnnuaireImporter($csv, $this->getAnnuaireSQL(), $this->getAnnuaireGroupsSQL());
        $testStream = org\bovigo\vfs\vfsStream::setup('test');
        $testStreamUrl = org\bovigo\vfs\vfsStream::url('test');
        $fileURL = $testStreamUrl . "/annuaire.csv";
        file_put_contents($fileURL, $data);
        return $annuaireImporter->import(1, $fileURL);
    }

    public function testVide()
    {
        $this->assertEquals(0, $this->annuaire_import(""));
    }

    public function testOne()
    {
        $this->assertEquals(1, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau"));
        $annuaire = new AnnuaireSQL($this->getObjectInstancier()->getInstance(SQLQuery::class));
        $mail_list  = $this->getAnnuaireSQL()->getUtilisateur(1);
        $this->assertEquals("eric@sigmalis.com", $mail_list[0]['email']);
        $this->assertEquals("Eric Pommateau", $mail_list[0]['description']);
    }

    public function testTwo()
    {
        $this->assertEquals(2, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau\ntoto@toto.fr,toto,"));
        $annuaire = new AnnuaireSQL($this->getObjectInstancier()->getInstance(SQLQuery::class));
        $mail_list  = $this->getAnnuaireSQL()->getUtilisateur(1);
    }

    public function testNotMail()
    {
        $this->assertEquals(0, $this->annuaire_import("eric_sigmalis.com,Eric Pommateau"));
    }

    public function testDescriptionManquante()
    {
        $this->assertEquals(0, $this->annuaire_import("eric@sigmalis.com"));
    }

    public function testCorrectionMail()
    {
        $this->assertEquals(1, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau"));
        $this->assertEquals(1, $this->annuaire_import("eric@sigmalis.com,Eric B. Pommateau"));
        $mail_list  = $this->getAnnuaireSQL()->getUtilisateur(1);
        $this->assertCount(1, $mail_list);
        $this->assertEquals("Eric B. Pommateau", $mail_list[0]['description']);
    }

    public function testAddGroupe()
    {
        $this->assertEquals(1, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau,Mon groupe"));
        $utilisateur = $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1);
        $this->assertCount(1, $utilisateur);
    }

    public function testAdd2Groupe()
    {
        $this->assertEquals(1, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau,Mon groupe,Elu,"));
        $utilisateur = $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1);
        $this->assertCount(1, $utilisateur);
        $utilisateur = $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2);
        $this->assertCount(1, $utilisateur);
    }

    public function testModifyGroupe()
    {
        $this->assertEquals(1, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau,Mon groupe,Elu,"));
        $this->assertEquals(1, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau,Elu,"));
        $utilisateur = $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1);
        $this->assertCount(0, $utilisateur);
        $utilisateur = $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2);
        $this->assertCount(1, $utilisateur);
    }

    public function add2NonExistentGroupe()
    {
        $this->assertEquals(1, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau,Nonexistent,"));
        $utilisateur = $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1);
        $this->assertCount(0, $utilisateur);
        $utilisateur = $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2);
        $this->assertCount(0, $utilisateur);
    }
}
