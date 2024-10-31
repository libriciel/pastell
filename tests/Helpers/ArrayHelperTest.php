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
