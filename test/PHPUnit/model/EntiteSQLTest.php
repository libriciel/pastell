<?php

use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Utilisateur\UserCreationService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EntiteSQLTest extends PastellTestCase
{
    /** @var  EntiteSQL */
    private $entiteSQL;

    private RoleUtilisateur $roleUtilisateur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);
        $this->roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
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

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testGetSiblingsWithPermission(): void
    {
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $id_e_2 = $entityCreationService->create('Entité 2', '000000000');
        $id_e_3 = $entityCreationService->create('Entité 3', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_2);

        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('test', 'aa@aa.fr', 'user', 'user');

        $this->roleUtilisateur->addRole($id_u, 'admin', $id_e_2);
        $this->roleUtilisateur->addRole($id_u, 'admin', $id_e_3);

        $siblingsWithPermission = $this->entiteSQL->getSiblingsWithPermission($id_e_3, $id_u);

        static::assertCount(1, $siblingsWithPermission);
        static::assertSame($id_e_3, $siblingsWithPermission[0]['id_e']);
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testGetSiblingsWithPermissionWhenEntityDeactivated(): void
    {
        $entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);
        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $id_e_2 = $entityCreationService->create('Entité 2', '000000000');
        $id_e_3 = $entityCreationService->create('Entité 3', '000000000');
        $id_e_4 = $entityCreationService->create('Entité 4', '000000000');

        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('test', 'aa@aa.fr', 'user', 'user');

        $this->roleUtilisateur->addRole($id_u, 'admin', $id_e_2);
        $this->roleUtilisateur->addRole($id_u, 'admin', $id_e_3);
        $this->roleUtilisateur->addRole($id_u, 'admin', $id_e_4);

        $getSiblingsWithPermission = $this->entiteSQL->getSiblingsWithPermission($id_e_2, $id_u);
        static::assertCount(3, $getSiblingsWithPermission);
        $entiteSQL->setActive($id_e_3, false);
        $getSiblingsWithPermission = $this->entiteSQL->getSiblingsWithPermission($id_e_2, $id_u);
        static::assertCount(2, $getSiblingsWithPermission);
    }
}
