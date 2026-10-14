<?php

/**
 * @deprecated Since 4.1.24, Use EntiteSQLTest instead
 */
class EntiteListeTest extends PastellTestCase
{
    /** @var  EntiteListe */
    private $entiteListe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entiteListe = new EntiteListe($this->getSQLQuery());
    }

    public function testCountCollectivite()
    {
        $this->assertEquals(2, $this->entiteListe->countCollectivite());
    }

    public function testGetAllCollectivite()
    {
        $this->assertEquals(
            "Bourg-en-Bresse",
            $this->entiteListe->getAllCollectivite(0, "")[0]['denomination']
        );
    }

    public function testGetAll()
    {
        $this->entiteListe->setFiltre("Bourg-en-Bresse");
        $this->assertEquals(
            "Bourg-en-Bresse",
            $this->entiteListe->getAll("collectivite")[0]['denomination']
        );
    }

    public function testWithoutRecherche()
    {
        $this->entiteListe->setFiltre(false);
        $this->assertEquals(
            "Bourg-en-Bresse",
            $this->entiteListe->getAll("collectivite")[0]['denomination']
        );
    }


    public function testGetNbCollectivite()
    {
        $this->assertEquals(1, $this->entiteListe->getNbCollectivite("Bourg-en-Bresse"));
    }

    public function testGetInfoFromArray()
    {
        $this->assertEquals(
            "Bourg-en-Bresse",
            $this->entiteListe->getInfoFromArray(["1"])[0]['denomination']
        );
    }

    public function testGetAllFille()
    {
        $this->assertEquals(
            "CCAS",
            $this->entiteListe->getAllFille(1)[0]['denomination']
        );
    }

    public function testgetAllDescendant()
    {
        $this->assertEquals(
            "CCAS",
            $this->entiteListe->getAllDescendant(1)[0]['denomination']
        );
    }

    public function testGetDenomination()
    {
        $this->assertEquals(
            "Bourg-en-Bresse",
            $this->entiteListe->getDenomination("Bourg")[0]['denomination']
        );
    }

    public function testGetBySiren(): void
    {
        static::assertSame(
            'Bourg-en-Bresse',
            $this->entiteListe->getBySiren('000000000')[0]['denomination']
        );
    }
}
