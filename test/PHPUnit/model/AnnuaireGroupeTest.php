<?php

/** @deprecated Since 4.1.20, Unused, Use AnnuaireGroupeSQL instead */
class AnnuaireGroupeTest extends PastellTestCase
{
    private function getAnnuaireSQL(): AnnuaireSQL
    {
        return new AnnuaireSQL($this->getObjectInstancier()->getInstance(SQLQuery::class));
    }

    private function getAnnuaireGroupe(): AnnuaireGroupe
    {
        return new AnnuaireGroupe($this->getObjectInstancier()->getInstance(SQLQuery::class), 1);
    }

    public function testAdd()
    {
        $id_g = $this->getAnnuaireGroupe()->add("test");
        $this->assertNotEmpty($id_g);
        $info = $this->getAnnuaireGroupe()->getInfo($id_g);
        $this->assertEquals("test", $info['nom']);
    }

    public function testAddIsIdempotent()
    {
        $id_g_1 = $this->getAnnuaireGroupe()->add("test");
        $id_g_2 = $this->getAnnuaireGroupe()->add("test");
        $this->assertEquals($id_g_1, $id_g_2);
    }

    public function testAddToGroupe()
    {
        $id_a = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $id_g = $this->getAnnuaireGroupe()->add("test");
        $this->getAnnuaireGroupe()->addToGroupe($id_g, $id_a);
        $this->assertTrue((bool) $this->getAnnuaireGroupe()->isInGroupe($id_g, $id_a));
        $this->assertEquals(1, $this->getAnnuaireGroupe()->getNbUtilisateur($id_g));
    }

    public function testDelete()
    {
        $id_a = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $id_g = $this->getAnnuaireGroupe()->add("test");
        $this->getAnnuaireGroupe()->addToGroupe($id_g, $id_a);
        $this->getAnnuaireGroupe()->delete([$id_g]);
        $this->assertEmpty($this->getAnnuaireGroupe()->getInfo($id_g));
        $this->assertSame(0, $this->getAnnuaireGroupe()->isInGroupe($id_g, $id_a));
    }

    public function testDeleteFromGroupe()
    {
        $id_a = $this->getAnnuaireSQL()->add(1, "Eric Pommateau", "eric@sigmalis.com");
        $id_g = $this->getAnnuaireGroupe()->add("test");
        $this->getAnnuaireGroupe()->addToGroupe($id_g, $id_a);
        $this->getAnnuaireGroupe()->deleteFromGroupe($id_g, [$id_a]);
        $this->assertSame(0, $this->getAnnuaireGroupe()->isInGroupe($id_g, $id_a));
    }
}
