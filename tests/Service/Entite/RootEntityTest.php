<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Entite;

use Pastell\Service\Entite\RootEntity;
use PHPUnit\Framework\TestCase;

final class RootEntityTest extends TestCase
{
    public function testAddDenomination(): void
    {
        $entity_list = RootEntity::addDenomination([
            ['id_e' => RootEntity::ID_E, 'denomination' => null],
            ['id_e' => 1, 'denomination' => 'Bourg-en-Bresse'],
            ['libelle' => 'without id_e'],
            false,
        ]);

        self::assertSame(
            [
                ['id_e' => RootEntity::ID_E, 'denomination' => RootEntity::DENOMINATION],
                ['id_e' => 1, 'denomination' => 'Bourg-en-Bresse'],
                ['libelle' => 'without id_e'],
                false,
            ],
            $entity_list
        );
    }
}
