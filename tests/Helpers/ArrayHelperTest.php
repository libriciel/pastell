<?php

declare(strict_types=1);

namespace Pastell\Tests\Helpers;

use Generator;
use Pastell\Helpers\ArrayHelper;
use PastellTestCase;

final class ArrayHelperTest extends PastellTestCase
{
    public static function getExpectedArrayByDepthProvider(): Generator
    {
        yield 'depth 0' => [
            0,
            [
                0 => 0,
                1 => 1,
                2 => 2,
            ],
        ];
        yield 'depth 1' => [
            1,
            [
                0 => 'Retour GED',
                1 => 'Mail sécurisé',
                2 => 'Accusé de notification',
            ],
        ];
        yield 'depth 2' => [
            2,
            [
                0 => 'has_ged_document_id',
                1 => 'ged_document_id_file',
                2 => 'to',
                3 => 'cc',
                4 => 'bcc',
                5 => 'password',
                6 => 'password2',
                7 => 'key',
                8 => 'sent_mail_number',
                9 => 'generated_receipt',
                10 => 'accuse_notification',
            ],
        ];
        yield 'depth 3' => [
            3,
            [
                0 => 'name',
                1 => 'type',
                2 => 'name',
                3 => 'type',
                4 => 'default',
            ],
        ];
        yield 'depth 4' => [
            4,
            [],
        ];
    }
    public static function buildNestedTreeProvider(): Generator
    {
        yield 'empty list' => [
            [],
            [],
        ];
        yield 'single root node' => [
            [
                ['id_e' => 1, 'denomination' => 'Racine', 'profondeur' => 0],
            ],
            [
                ['id_e' => 1, 'denomination' => 'Racine', 'profondeur' => 0],
            ],
        ];
        yield 'two root nodes' => [
            [
                ['id_e' => 1, 'denomination' => 'A', 'profondeur' => 0],
                ['id_e' => 2, 'denomination' => 'B', 'profondeur' => 0],
            ],
            [
                ['id_e' => 1, 'denomination' => 'A', 'profondeur' => 0],
                ['id_e' => 2, 'denomination' => 'B', 'profondeur' => 0],
            ],
        ];
        yield 'one child under root' => [
            [
                ['id_e' => 1, 'denomination' => 'Parent', 'profondeur' => 0],
                ['id_e' => 2, 'denomination' => 'Enfant', 'profondeur' => 1],
            ],
            [
                [
                    'id_e' => 1,
                    'denomination' => 'Parent',
                    'profondeur' => 0,
                    'children' => [
                        ['id_e' => 2, 'denomination' => 'Enfant', 'profondeur' => 1],
                    ],
                ],
            ],
        ];
        yield 'two children under same root' => [
            [
                ['id_e' => 1, 'denomination' => 'Parent', 'profondeur' => 0],
                ['id_e' => 2, 'denomination' => 'Enfant 1', 'profondeur' => 1],
                ['id_e' => 3, 'denomination' => 'Enfant 2', 'profondeur' => 1],
            ],
            [
                [
                    'id_e' => 1,
                    'denomination' => 'Parent',
                    'profondeur' => 0,
                    'children' => [
                        ['id_e' => 2, 'denomination' => 'Enfant 1', 'profondeur' => 1],
                        ['id_e' => 3, 'denomination' => 'Enfant 2', 'profondeur' => 1],
                    ],
                ],
            ],
        ];
        yield 'two levels of nesting' => [
            [
                ['id_e' => 1, 'denomination' => 'Racine', 'profondeur' => 0],
                ['id_e' => 2, 'denomination' => 'Niveau 1', 'profondeur' => 1],
                ['id_e' => 3, 'denomination' => 'Niveau 2', 'profondeur' => 2],
            ],
            [
                [
                    'id_e' => 1,
                    'denomination' => 'Racine',
                    'profondeur' => 0,
                    'children' => [
                        [
                            'id_e' => 2,
                            'denomination' => 'Niveau 1',
                            'profondeur' => 1,
                            'children' => [
                                ['id_e' => 3, 'denomination' => 'Niveau 2', 'profondeur' => 2],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'two roots with children' => [
            [
                ['id_e' => 1, 'denomination' => 'A', 'profondeur' => 0],
                ['id_e' => 2, 'denomination' => 'A1', 'profondeur' => 1],
                ['id_e' => 3, 'denomination' => 'B', 'profondeur' => 0],
                ['id_e' => 4, 'denomination' => 'B1', 'profondeur' => 1],
            ],
            [
                [
                    'id_e' => 1,
                    'denomination' => 'A',
                    'profondeur' => 0,
                    'children' => [
                        ['id_e' => 2, 'denomination' => 'A1', 'profondeur' => 1],
                    ],
                ],
                [
                    'id_e' => 3,
                    'denomination' => 'B',
                    'profondeur' => 0,
                    'children' => [
                        ['id_e' => 4, 'denomination' => 'B1', 'profondeur' => 1],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider buildNestedTreeProvider
     */
    public function testBuildNestedTree(array $flatList, array $expected): void
    {
        self::assertSame($expected, ArrayHelper::buildNestedTree($flatList));
    }

    public static function buildTreeselectOptionsProvider(): Generator
    {
        yield 'empty list' => [
            [],
            [],
        ];
        yield 'single root node' => [
            [
                ['id_e' => 1, 'denomination' => 'Racine', 'profondeur' => 0],
            ],
            [
                ['value' => 1, 'name' => 'Racine', 'profondeur' => 0],
            ],
        ];
        yield 'root with one child' => [
            [
                ['id_e' => 1, 'denomination' => 'Parent', 'profondeur' => 0],
                ['id_e' => 2, 'denomination' => 'Enfant', 'profondeur' => 1],
            ],
            [
                [
                    'value' => 1,
                    'name' => 'Parent',
                    'profondeur' => 0,
                    'children' => [
                        ['value' => 2, 'name' => 'Enfant', 'profondeur' => 1],
                    ],
                ],
            ],
        ];
        yield 'two levels of nesting with key renaming' => [
            [
                ['id_e' => 10, 'denomination' => 'Racine', 'profondeur' => 0],
                ['id_e' => 20, 'denomination' => 'Niveau 1', 'profondeur' => 1],
                ['id_e' => 30, 'denomination' => 'Niveau 2', 'profondeur' => 2],
            ],
            [
                [
                    'value' => 10,
                    'name' => 'Racine',
                    'profondeur' => 0,
                    'children' => [
                        [
                            'value' => 20,
                            'name' => 'Niveau 1',
                            'profondeur' => 1,
                            'children' => [
                                ['value' => 30, 'name' => 'Niveau 2', 'profondeur' => 2],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider buildTreeselectOptionsProvider
     */
    public function testBuildTreeselectOptions(array $flatList, array $expected): void
    {
        self::assertSame($expected, ArrayHelper::buildTreeselectOptions($flatList));
    }

    /**
     * @dataProvider getExpectedArrayByDepthProvider
     */
    public function testGetArrayKeysByDepth(int $depth, array $expectedArray): void
    {
        $browseArray = [
            0 => [
                'Retour GED' => [
                    'has_ged_document_id' => [],
                    'ged_document_id_file' => [
                        'name' => ['Identifiants des documents sur la GED'],
                        'type' => ['file'],
                        ],
                    ]
                ],
            1 => [
                'Mail sécurisé' => [
                    'to' => [
                        'name' => ['Destinataire(s)'],
                        'type' => ['mail-list'],
                        ],
                    'cc' => [
                        'commentaire' => 'Plusieurs emails possibles séparés par une virgule'
                        ],
                    'bcc'  => [],
                    'password'  => [],
                    'password2'  => [],
                    'key'  => [],
                    'sent_mail_number' => [
                        'default' => [0]
                    ],
                    'sent_mail_read',
                    'sent_mail_answered',
                    ]
                ],
                [
                'Accusé de notification' => [
                    'generated_receipt'  => [],
                    'accuse_notification'  => [],
                    ]
                ],
            ];

        self::assertSame(
            $expectedArray,
            ArrayHelper::getArrayKeysByDepth($browseArray, $depth)
        );
    }
}
