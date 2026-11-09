<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Entite;

use FluxEntiteSQL;
use Pastell\Service\Entite\EntityUtilitiesService;
use PastellTestCase;

class EntityUtilitiesServiceTest extends PastellTestCase
{
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
