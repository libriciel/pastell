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

    public function testBuildEntityTreeselectOptionsEmptyList(): void
    {
        static::assertSame([], $this->service->buildEntityTreeselectOptions([]));
    }

    public function testBuildEntityTreeselectOptionsSingleEntity(): void
    {
        $flatList = [
            ['id_e' => 1, 'denomination' => 'Entité A', 'profondeur' => 0],
        ];

        static::assertSame(
            [['name' => 'Entité A', 'value' => '1']],
            $this->service->buildEntityTreeselectOptions($flatList)
        );
    }

    public function testBuildEntityTreeselectOptionsWithChildren(): void
    {
        $flatList = [
            ['id_e' => 1, 'denomination' => 'Parent', 'profondeur' => 0],
            ['id_e' => 2, 'denomination' => 'Enfant', 'profondeur' => 1],
            ['id_e' => 3, 'denomination' => 'Petit-enfant', 'profondeur' => 2],
        ];

        $expected = [
            [
                'name' => 'Parent',
                'value' => '1',
                'children' => [
                    [
                        'name' => 'Enfant',
                        'value' => '2',
                        'children' => [
                            ['name' => 'Petit-enfant', 'value' => '3'],
                        ],
                    ],
                ],
            ],
        ];

        static::assertSame($expected, $this->service->buildEntityTreeselectOptions($flatList));
    }

    public function testBuildEntityTreeselectOptionsWithSiblingEntities(): void
    {
        $flatList = [
            ['id_e' => 1, 'denomination' => 'Entité 1', 'profondeur' => 0],
            ['id_e' => 2, 'denomination' => 'Enfant de Entité 1', 'profondeur' => 1],
            ['id_e' => 3, 'denomination' => 'Entité 2', 'profondeur' => 0],
        ];

        $expected = [
            [
                'name' => 'Entité 1',
                'value' => '1',
                'children' => [
                    ['name' => 'Enfant de Entité 1', 'value' => '2'],
                ],
            ],
            ['name' => 'Entité 2', 'value' => '3'],
        ];

        static::assertSame($expected, $this->service->buildEntityTreeselectOptions($flatList));
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
