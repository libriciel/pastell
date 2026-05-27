<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Entite;

use EntiteSQL;
use FluxEntiteSQL;
use Pastell\Service\Entite\EntityUtilitiesService;
use PastellTestCase;

class EntityUtilitiesServiceTest extends PastellTestCase
{
    private EntityUtilitiesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getObjectInstancier()->getInstance(EntityUtilitiesService::class);
    }

    public function testBuildEntityTreeEmptyList(): void
    {
        static::assertSame([], $this->service->buildEntityTree([]));
    }

    public function testBuildEntityTreeSingleEntity(): void
    {
        $flatList = [
            ['id_e' => 1, 'denomination' => 'Entité A', 'profondeur' => 0],
        ];

        static::assertSame(
            [['id_e' => 1, 'denomination' => 'Entité A', 'profondeur' => 0]],
            $this->service->buildEntityTree($flatList)
        );
    }

    public function testBuildEntityTreeWithChildren(): void
    {
        $flatList = [
            ['id_e' => 1, 'denomination' => 'Parent', 'profondeur' => 0],
            ['id_e' => 2, 'denomination' => 'Enfant', 'profondeur' => 1],
            ['id_e' => 3, 'denomination' => 'Petit-enfant', 'profondeur' => 2],
        ];

        $expected = [
            [
                'id_e' => 1,
                'denomination' => 'Parent',
                'profondeur' => 0,
                'children' => [
                    [
                        'id_e' => 2,
                        'denomination' => 'Enfant',
                        'profondeur' => 1,
                        'children' => [
                            ['id_e' => 3, 'denomination' => 'Petit-enfant', 'profondeur' => 2],
                        ],
                    ],
                ],
            ],
        ];

        static::assertSame($expected, $this->service->buildEntityTree($flatList));
    }

    public function testBuildEntityTreeWithSiblingEntities(): void
    {
        $flatList = [
            ['id_e' => 1, 'denomination' => 'Entité 1', 'profondeur' => 0],
            ['id_e' => 2, 'denomination' => 'Enfant de Entité 1', 'profondeur' => 1],
            ['id_e' => 3, 'denomination' => 'Entité 2', 'profondeur' => 0],
        ];

        $expected = [
            [
                'id_e' => 1,
                'denomination' => 'Entité 1',
                'profondeur' => 0,
                'children' => [
                    ['id_e' => 2, 'denomination' => 'Enfant de Entité 1', 'profondeur' => 1],
                ],
            ],
            ['id_e' => 3, 'denomination' => 'Entité 2', 'profondeur' => 0],
        ];

        static::assertSame($expected, $this->service->buildEntityTree($flatList));
    }

    public function testBuildEntityTreeWithRoot(): void
    {
        $flatList = [
            ['id_e' => 1, 'denomination' => 'Entité A', 'profondeur' => 0],
        ];

        $result = $this->service->buildEntityTreeWithRoot($flatList);

        static::assertCount(1, $result);
        static::assertSame(EntiteSQL::ID_E_ENTITE_RACINE, $result[0]['id_e']);
        static::assertSame(EntiteSQL::ENTITE_RACINE_DENOMINATION, $result[0]['denomination']);
        static::assertSame(
            $this->service->buildEntityTree($flatList),
            $result[0]['children']
        );
    }

    public function testBuildEntityTreeWithRootEmptyList(): void
    {
        $result = $this->service->buildEntityTreeWithRoot([]);

        static::assertSame(
            [
                [
                    'id_e' => EntiteSQL::ID_E_ENTITE_RACINE,
                    'denomination' => EntiteSQL::ENTITE_RACINE_DENOMINATION,
                    'children' => [],
                ],
            ],
            $result
        );
    }

    public function testAddDenominationForEntiteRacine(): void
    {
        $connecteurInfo = $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class)
            ->getConnecteur(0, 'global', 'horodateur');
        static::assertNull(
            $connecteurInfo['denomination']
        );

        $connecteurInfo = $this->getObjectInstancier()->getInstance(EntityUtilitiesService::class)
            ->addDenominationForEntiteRacine([$connecteurInfo])[0];
        static::assertSame(
            'Entité racine',
            $connecteurInfo['denomination']
        );
    }
}
