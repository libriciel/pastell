<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\Entite\EntityType;
use Pastell\Service\Entite\RootEntity;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use Pastell\Service\Utilisateur\UtilisateurEntiteService;
use PastellTestCase;

class UtilisateurEntiteServiceTest extends PastellTestCase
{
    private UtilisateurEntiteService $service;
    private UtilisateurRoleService $roleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getObjectInstancier()->getInstance(UtilisateurEntiteService::class);
        $this->roleService = $this->getObjectInstancier()->getInstance(UtilisateurRoleService::class);
    }

    public function testGetArbreFille(): void
    {
        $entity = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $parent = $entity->create('Parent', '000000000');
        $child = $entity->create('Child', '000000000', EntityType::COLLECTIVITE->value, $parent);

        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('login', 'email@example.org', 'firstname', 'lastname');
        $this->roleService->addRole($id_u, 'admin', $parent);

        $arbre = $this->service->getArbreFille(
            $id_u,
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE)
        );

        static::assertSame(
            [
                ['id_e' => $parent, 'denomination' => 'Parent', 'profondeur' => 0],
                ['id_e' => $child, 'denomination' => 'Child', 'profondeur' => 1],
            ],
            $arbre
        );
    }

    public function testArbreFilleWithRacine(): void
    {
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('login', 'email@example.org', 'firstname', 'lastname');
        $this->roleService->addRole($id_u, 'admin', RootEntity::ID_E);

        $arbre = $this->service->getArbreFilleWithRacine(
            $id_u,
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE)
        );

        static::assertSame(RootEntity::ID_E, $arbre[0]['id_e']);
        static::assertSame(RootEntity::DENOMINATION, $arbre[0]['denomination']);
    }

    public function testGetChildrenWithPermission(): void
    {
        $entity = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $parent = $entity->create('Parent', '000000000');
        $child = $entity->create('Child', '000000000', EntityType::COLLECTIVITE->value, $parent);

        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('login', 'email@example.org', 'firstname', 'lastname');
        $this->roleService->addRole($id_u, 'admin', $parent);
        $this->roleService->addRole($id_u, 'admin', $child);

        $children = $this->service->getChildrenWithAnyDroit($parent, $id_u);

        static::assertCount(1, $children);
        static::assertSame($child, $children[0]['id_e']);
    }
}
