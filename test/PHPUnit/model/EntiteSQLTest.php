<?php

class EntiteSQLTest extends PastellTestCase
{
    /** @var  EntiteSQL */
    private $entiteSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);
    }

    public function testGetDemominationEntiteRacine()
    {
        $this->assertEquals(
            EntiteSQL::ENTITE_RACINE_DENOMINATION,
            $this->entiteSQL->getDenomination(0)
        );
    }

    public function testGetDenomination()
    {
        $this->assertEquals("Bourg-en-Bresse", $this->entiteSQL->getDenomination(1));
    }

    public function testGetDenominationEmpty()
    {
        $this->assertEquals("", $this->entiteSQL->getDenomination(42));
    }

    public function testGetEntiteMere()
    {
        $id_e = $this->entiteSQL->getEntiteMere(2);
        $this->assertEquals(1, $id_e);
    }

    public function testGetEntiteFromData()
    {
        $info = $this->entiteSQL->getEntiteFromData(['id_e' => 1]);
        $this->assertEquals("Bourg-en-Bresse", $info['denomination']);
    }

    public function testGetEntiteFromDataFromDenomination()
    {
        $info = $this->entiteSQL->getEntiteFromData(['denomination' => "Bourg-en-Bresse"]);
        $this->assertEquals(1, $info['id_e']);
    }

    public function testGetEntiteFromDataIdNotExisting()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("L'identifiant de l'entite n'existe pas : {id_e=42}");
        $this->entiteSQL->getEntiteFromData(['id_e' => 42]);
    }

    public function testGetEntiteFromDataDenominationNotExisting()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("La dénomination de l'entité n'existe pas : {denomination=FizzBuzz}");
        $this->entiteSQL->getEntiteFromData(['denomination' => "FizzBuzz"]);
    }

    public function testGetEntiteFromDataFailed()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("Aucun paramètre permettant la recherche de l'entité n'a été renseigné");
        $this->entiteSQL->getEntiteFromData([]);
    }

    public function testGetEntiteFromDataSameDenomination()
    {
        $sql = "INSERT INTO entite(denomination,siren) VALUES ('Bourg-en-Bresse','123456789')";
        $this->getSQLQuery()->query($sql);
        $this->expectException("Exception");
        $this->expectExceptionMessage("Plusieurs entités portent le même nom, préférez utiliser son identifiant");
        $this->entiteSQL->getEntiteFromData(['denomination' => "Bourg-en-Bresse"]);
    }

    public function testGetEntiteFromDataIsActive(): void
    {
        $data['is_active'] = 1;
        $this->assertCount(2, $this->entiteSQL->getEntiteFromData($data));
        $data['is_active'] = 0;
        $this->assertCount(0, $this->entiteSQL->getEntiteFromData($data));

        $this->entiteSQL->setActive(self::ID_E_COL, 0);
        $data['is_active'] = 1;
        $this->assertCount(1, $this->entiteSQL->getEntiteFromData($data));
        $data['is_active'] = 0;
        $this->assertCount(1, $this->entiteSQL->getEntiteFromData($data));
    }

    public function testExists()
    {
        $this->assertTrue($this->entiteSQL->exists(1));
    }

    public function testGetBySiren(): void
    {
        $id_e = $this->entiteSQL->getBySiren('000000000');
        $this->assertEquals(1, $id_e);
    }

    public function testGetIdByDenomination()
    {
        $id_e = $this->entiteSQL->getIdByDenomination('Bourg-en-Bresse');
        $this->assertEquals(1, $id_e);
    }

    public function testGetFilleInfoNavigation()
    {
        $this->assertEquals(
            'Bourg-en-Bresse',
            $this->entiteSQL->getFilleInfoNavigation(0, [])[0]['denomination']
        );
    }

    public function testGetFilleInfoNavigationAuthorized()
    {
        $this->assertEquals(
            'Bourg-en-Bresse',
            $this->entiteSQL->getFilleInfoNavigation(0, [1])[0]['denomination']
        );
    }

    public function testGetFilleInfoNavigationUnauthorized()
    {
        $this->assertEquals(
            'CCAS',
            $this->entiteSQL->getFilleInfoNavigation(0, [2])[0]['denomination']
        );
    }

    public function testGetAllChildren(): void
    {
        $this->assertCount(2, $this->entiteSQL->getAllChildren(EntiteSQL::ID_E_ENTITE_RACINE));
    }

    public function testGetAllByType(): void
    {
        static::assertSame(
            'Bourg-en-Bresse',
            $this->entiteSQL->getAllByType(EntiteSQL::TYPE_COLLECTIVITE)[0]['denomination']
        );
    }

    public function testGetAllCollectivite(): void
    {
        static::assertSame('Bourg-en-Bresse', $this->entiteSQL->getAllCollectivite(0, '')[0]['denomination']);
    }

    public function testGetNbCollectivite(): void
    {
        static::assertSame(1, $this->entiteSQL->getNbCollectivite('Bourg-en-Bresse'));
    }
}
