<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Entite;

use EntiteSQL;
use Pastell\Service\Entite\EntityHierarchyService;
use PastellTestCase;

final class EntityHierarchyServiceTest extends PastellTestCase
{
    private function getEntityHierarchyService(): EntityHierarchyService
    {
        return $this->getObjectInstancier()->getInstance(EntityHierarchyService::class);
    }

    private function getEntiteSQL(): EntiteSQL
    {
        return $this->getObjectInstancier()->getInstance(EntiteSQL::class);
    }

    public function testGetAncestorIds(): void
    {
        self::assertEquals([0, 1], $this->getEntityHierarchyService()->getAncestorIds(2));
        self::assertEquals([0], $this->getEntityHierarchyService()->getAncestorIds(1));
    }

    public function testGetAllChildren(): void
    {
        $children = $this->getEntityHierarchyService()->getAllChildren(EntiteSQL::ID_E_ENTITE_RACINE);
        self::assertSame(['Bourg-en-Bresse', 'CCAS'], array_column($children, 'denomination'));
    }

    public function testGetAncestorDenominations(): void
    {
        self::assertSame(
            [EntiteSQL::ENTITE_RACINE_DENOMINATION, 'Bourg-en-Bresse', 'CCAS'],
            $this->getEntityHierarchyService()->getAncestorDenominations(2)
        );
        self::assertSame(
            [EntiteSQL::ENTITE_RACINE_DENOMINATION],
            $this->getEntityHierarchyService()->getAncestorDenominations(EntiteSQL::ID_E_ENTITE_RACINE)
        );
    }

    public function testGetNavigationAncestors(): void
    {
        $ancestors = $this->getEntityHierarchyService()->getNavigationAncestors(2, [0]);
        self::assertSame(['Bourg-en-Bresse'], array_column($ancestors, 'denomination'));
    }

    public function testGetNavigationAncestorsUnauthorized(): void
    {
        self::assertSame([], $this->getEntityHierarchyService()->getNavigationAncestors(2, [2]));
    }

    public function testGetNavigationChildren(): void
    {
        self::assertSame(
            'Bourg-en-Bresse',
            $this->getEntityHierarchyService()->getNavigationChildren(0, [])[0]['denomination']
        );
    }

    public function testGetNavigationChildrenAuthorized(): void
    {
        self::assertSame(
            'Bourg-en-Bresse',
            $this->getEntityHierarchyService()->getNavigationChildren(0, [1])[0]['denomination']
        );
    }

    public function testGetNavigationChildrenUnauthorized(): void
    {
        self::assertSame(
            'CCAS',
            $this->getEntityHierarchyService()->getNavigationChildren(0, [2])[0]['denomination']
        );
    }

    public function testUpdateAncestors(): void
    {
        $id_e = $this->getEntiteSQL()->create('Child', '', EntiteSQL::TYPE_COLLECTIVITE, 2);
        $this->getEntityHierarchyService()->updateAncestors($id_e, 2);

        self::assertSame(
            ['Bourg-en-Bresse', 'CCAS', 'Child'],
            array_column($this->getEntiteSQL()->getAncetre($id_e), 'denomination')
        );
    }

    public function testUpdateAllAncestors(): void
    {
        $id_e = $this->getEntiteSQL()->create('Child', '', EntiteSQL::TYPE_COLLECTIVITE, 2);
        $this->getEntityHierarchyService()->updateAllAncestors();

        self::assertSame(
            ['Bourg-en-Bresse', 'CCAS', 'Child'],
            array_column($this->getEntiteSQL()->getAncetre($id_e), 'denomination')
        );
        self::assertEquals([0, 1], $this->getEntityHierarchyService()->getAncestorIds(2));
    }
}
