<?php

use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Entite\EntityType;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EntiteSQLTest extends PastellTestCase
{
    /** @var  EntiteSQL */
    private $entiteSQL;

    private UtilisateurRoleService $utilisateurRoleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);
        $this->utilisateurRoleService = $this->getObjectInstancier()->getInstance(UtilisateurRoleService::class);
    }

    public function testGetEntiteMere()
    {
        $id_e = $this->entiteSQL->getEntiteMere(2);
        $this->assertEquals(1, $id_e);
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

    public function testGetAllByType(): void
    {
        static::assertSame(
            'Bourg-en-Bresse',
            $this->entiteSQL->getAllByType(EntityType::COLLECTIVITE->value)[0]['denomination']
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

    public function testGetActiveChildren(): void
    {
        $children = $this->entiteSQL->getActiveChildrenByType(1, [EntityType::COLLECTIVITE->value]);
        self::assertSame(['CCAS'], array_column($children, 'denomination'));
        self::assertSame([], $this->entiteSQL->getActiveChildrenByType(1, [EntityType::CENTRE_DE_GESTION->value]));
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
        $id_e_3 = $entityCreationService->create('Entité 3', '000000000', EntityType::COLLECTIVITE->value, $id_e_2);

        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $id_u = $userCreationService->create('test', 'aa@aa.fr', 'user', 'user');

        $this->utilisateurRoleService->addRole($id_u, 'admin', $id_e_2);
        $this->utilisateurRoleService->addRole($id_u, 'admin', $id_e_3);

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

        $this->utilisateurRoleService->addRole($id_u, 'admin', $id_e_2);
        $this->utilisateurRoleService->addRole($id_u, 'admin', $id_e_3);
        $this->utilisateurRoleService->addRole($id_u, 'admin', $id_e_4);

        $getSiblingsWithPermission = $this->entiteSQL->getSiblingsWithPermission($id_e_2, $id_u);
        static::assertCount(3, $getSiblingsWithPermission);
        $entiteSQL->setActive($id_e_3, false);
        $getSiblingsWithPermission = $this->entiteSQL->getSiblingsWithPermission($id_e_2, $id_u);
        static::assertCount(2, $getSiblingsWithPermission);
    }
}
