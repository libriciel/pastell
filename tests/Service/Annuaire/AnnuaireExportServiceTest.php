<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;
use Pastell\Service\Annuaire\AnnuaireExportService;
use PastellTestCase;
use SQLQuery;

class AnnuaireExportServiceTest extends PastellTestCase
{
    private function getService(): AnnuaireExportService
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireExportService::class);
    }

    private function getAnnuaireSQL(): AnnuaireSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireSQL::class);
    }

    private function getAnnuaireGroupsSQL(): AnnuaireGroupeSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
    }

    public function testVide(): void
    {
        static::assertEmpty($this->getService()->export(1));
    }

    public function testOne(): void
    {
        $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        static::assertSame("eric@sigmalis.com,\"Eric Pommateau\"\n", $this->getService()->export(1));
    }

    public function testTwo(): void
    {
        $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $this->getAnnuaireSQL()->add(1, 'Toto', 'toto@sigmalis.com');
        static::assertSame(
            "eric@sigmalis.com,\"Eric Pommateau\"\ntoto@sigmalis.com,Toto\n",
            $this->getService()->export(1)
        );
    }

    public function testGroupe(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $this->getAnnuaireGroupsSQL()->addToGroupe(2, $id_a);
        static::assertSame("eric@sigmalis.com,\"Eric Pommateau\",Elu\n", $this->getService()->export(1));
    }

    public function test2Groupe(): void
    {
        $id_a = $this->getAnnuaireSQL()->add(1, 'Eric Pommateau', 'eric@sigmalis.com');
        $this->getAnnuaireGroupsSQL()->addToGroupe(1, $id_a);
        $this->getAnnuaireGroupsSQL()->addToGroupe(2, $id_a);
        static::assertSame(
            "eric@sigmalis.com,\"Eric Pommateau\",Elu,\"Mon groupe\"\n",
            $this->getService()->export(1)
        );
    }
}
