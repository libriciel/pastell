<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Utilisateur\UserCreationService;

class UtilisateurRoleSQLTest extends PastellTestCase
{
    private UtilisateurRoleSQL $sql;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sql = new UtilisateurRoleSQL($this->getSQLQuery());
    }

    public function testGetRole()
    {
        static::assertSame('admin', $this->sql->getRole(1)[0]['role']);
    }

    public function testInsertDeleteRole()
    {
        $this->sql->deleteAllRoles(2);
        static::assertSame([], $this->sql->getAllDroitEntite(2, 1));

        $this->sql->insertRole(2, 'admin', 1);
        static::assertNotEmpty($this->sql->getAllDroitEntite(2, 1));
        static::assertNotEmpty($this->sql->getAllDroit(2));
    }

    public function testHasRole()
    {
        $this->sql->deleteAllRoles(2);
        static::assertSame(0, $this->sql->hasRole(2, 'admin', 1));

        $this->sql->insertRole(2, 'admin', 1);
        static::assertSame(1, $this->sql->hasRole(2, 'admin', 1));
    }

    public function testGetChildrenWithPermission()
    {
        $entity = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $id_e_2 = $entity->create('Entité 2', '000000000');
        $id_e_3 = $entity->create('Entité 3', '000000000', EntiteSQL::TYPE_COLLECTIVITE, $id_e_2);

        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('test', 'aa@aa.fr', 'user', 'user');

        $this->sql->insertRole($id_u, 'admin', $id_e_2);
        $this->sql->insertRole($id_u, 'admin', $id_e_3);

        $children = $this->sql->getChildrenWithAnyDroit($id_e_2, $id_u, DroitService::AUCUN_DROIT);

        static::assertCount(1, $children);
        static::assertSame($id_e_3, $children[0]['id_e']);
    }
}
