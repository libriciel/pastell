<?php

declare(strict_types=1);

class AnnuaireImporterTest extends PastellTestCase
{
    private function getAnnuaireSQL(): AnnuaireSQL
    {
        return new AnnuaireSQL($this->getObjectInstancier()->getInstance(SQLQuery::class));
    }

    private function getAnnuaireGroupsSQL(): AnnuaireGroupeSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
    }

    private function annuaire_import(string $data): int
    {
        $csv = new CSV();
        $annuaireImporter = new AnnuaireImporter($csv, $this->getAnnuaireSQL(), $this->getAnnuaireGroupsSQL());
        $testStreamUrl = org\bovigo\vfs\vfsStream::url(org\bovigo\vfs\vfsStream::setup('test')->getName());
        $fileURL = $testStreamUrl . '/annuaire.csv';
        file_put_contents($fileURL, $data);
        return $annuaireImporter->import(1, $fileURL);
    }

    public function testVide(): void
    {
        static::assertEquals(0, $this->annuaire_import(''));
    }

    public function testOne(): void
    {
        static::assertEquals(1, $this->annuaire_import('eric@sigmalis.com,Eric Pommateau'));
        $mail_list = $this->getAnnuaireSQL()->getUtilisateur(1);
        static::assertEquals('eric@sigmalis.com', $mail_list[0]['email']);
        static::assertEquals('Eric Pommateau', $mail_list[0]['description']);
    }

    public function testTwo(): void
    {
        static::assertEquals(2, $this->annuaire_import("eric@sigmalis.com,Eric Pommateau\ntoto@toto.fr,toto,"));
        $mail_list = $this->getAnnuaireSQL()->getUtilisateur(1);
        static::assertCount(2, $mail_list);
    }

    public function testNotMail(): void
    {
        static::assertEquals(0, $this->annuaire_import('eric_sigmalis.com,Eric Pommateau'));
    }

    public function testDescriptionManquante(): void
    {
        static::assertEquals(0, $this->annuaire_import('eric@sigmalis.com'));
    }

    public function testCorrectionMail(): void
    {
        static::assertEquals(1, $this->annuaire_import('eric@sigmalis.com,Eric Pommateau'));
        static::assertEquals(1, $this->annuaire_import('eric@sigmalis.com,Eric B. Pommateau'));
        $mail_list = $this->getAnnuaireSQL()->getUtilisateur(1);
        static::assertCount(1, $mail_list);
        static::assertEquals('Eric B. Pommateau', $mail_list[0]['description']);
    }

    public function testAddGroupe(): void
    {
        static::assertEquals(1, $this->annuaire_import('eric@sigmalis.com,Eric Pommateau,Mon groupe'));
        static::assertCount(1, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1));
    }

    public function testAdd2Groupe(): void
    {
        static::assertEquals(1, $this->annuaire_import('eric@sigmalis.com,Eric Pommateau,Mon groupe,Elu,'));
        static::assertCount(1, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1));
        static::assertCount(1, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2));
    }

    public function testModifyGroupe(): void
    {
        static::assertEquals(1, $this->annuaire_import('eric@sigmalis.com,Eric Pommateau,Mon groupe,Elu,'));
        static::assertEquals(1, $this->annuaire_import('eric@sigmalis.com,Eric Pommateau,Elu,'));
        static::assertCount(0, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1));
        static::assertCount(1, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2));
    }

    public function testAdd2NonExistentGroupe(): void
    {
        static::assertEquals(1, $this->annuaire_import('eric@sigmalis.com,Eric Pommateau,Nonexistent,'));
        static::assertCount(0, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1));
        static::assertCount(0, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2));
    }
}
