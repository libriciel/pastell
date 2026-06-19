<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;
use org\bovigo\vfs\vfsStream;
use Pastell\Service\Annuaire\AnnuaireImportService;
use PastellTestCase;
use SQLQuery;

class AnnuaireImportServiceTest extends PastellTestCase
{
    private function getService(): AnnuaireImportService
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireImportService::class);
    }

    private function getAnnuaireSQL(): AnnuaireSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireSQL::class);
    }

    private function getAnnuaireGroupsSQL(): AnnuaireGroupeSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
    }

    private function doImport(string $data): int
    {
        $fileURL = vfsStream::url('test') . '/annuaire.csv';
        file_put_contents($fileURL, $data);
        return $this->getService()->import(1, $fileURL);
    }

    public function testVide(): void
    {
        static::assertEquals(0, $this->doImport(''));
    }

    public function testOne(): void
    {
        static::assertEquals(1, $this->doImport('eric@sigmalis.com,Eric Pommateau'));
        $mail_list = $this->getAnnuaireSQL()->getUtilisateur(1);
        static::assertEquals('eric@sigmalis.com', $mail_list[0]['email']);
        static::assertEquals('Eric Pommateau', $mail_list[0]['description']);
    }

    public function testTwo(): void
    {
        static::assertEquals(2, $this->doImport("eric@sigmalis.com,Eric Pommateau\ntoto@toto.fr,toto,"));
        $mail_list = $this->getAnnuaireSQL()->getUtilisateur(1);
        static::assertCount(2, $mail_list);
    }

    public function testNotMail(): void
    {
        static::assertEquals(0, $this->doImport('eric_sigmalis.com,Eric Pommateau'));
    }

    public function testDescriptionManquante(): void
    {
        static::assertEquals(0, $this->doImport('eric@sigmalis.com'));
    }

    public function testCorrectionMail(): void
    {
        static::assertEquals(1, $this->doImport('eric@sigmalis.com,Eric Pommateau'));
        static::assertEquals(1, $this->doImport('eric@sigmalis.com,Eric B. Pommateau'));
        $mail_list = $this->getAnnuaireSQL()->getUtilisateur(1);
        static::assertCount(1, $mail_list);
        static::assertEquals('Eric B. Pommateau', $mail_list[0]['description']);
    }

    public function testAddGroupe(): void
    {
        static::assertEquals(1, $this->doImport('eric@sigmalis.com,Eric Pommateau,Mon groupe'));
        $utilisateur = $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1);
        static::assertCount(1, $utilisateur);
    }

    public function testAdd2Groupe(): void
    {
        static::assertEquals(1, $this->doImport('eric@sigmalis.com,Eric Pommateau,Mon groupe,Elu,'));
        static::assertCount(1, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1));
        static::assertCount(1, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2));
    }

    public function testModifyGroupe(): void
    {
        static::assertEquals(1, $this->doImport('eric@sigmalis.com,Eric Pommateau,Mon groupe,Elu,'));
        static::assertEquals(1, $this->doImport('eric@sigmalis.com,Eric Pommateau,Elu,'));
        static::assertCount(0, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1));
        static::assertCount(1, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2));
    }

    public function testNonExistentGroupe(): void
    {
        static::assertEquals(1, $this->doImport('eric@sigmalis.com,Eric Pommateau,Nonexistent,'));
        static::assertCount(0, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(1));
        static::assertCount(0, $this->getAnnuaireGroupsSQL()->getAllUtilisateur(2));
    }
}
