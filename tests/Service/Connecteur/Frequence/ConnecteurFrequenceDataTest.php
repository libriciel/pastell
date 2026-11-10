<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Connecteur\Frequence;

use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceActionType;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceData;
use Pastell\Service\Connecteur\Frequence\ConnecteurFrequenceType;
use PHPUnit\Framework\TestCase;

final class ConnecteurFrequenceDataTest extends TestCase
{
    public function testFromArray(): void
    {
        $connecteurFrequenceData = ConnecteurFrequenceData::fromArray([
            'id_cf' => '12',
            'type_connecteur' => 'entite',
            'id_ce' => '0',
            'action_type' => 'document',
            'expression' => '15',
            'libelle' => null,
        ]);

        self::assertSame(12, $connecteurFrequenceData->id_cf);
        self::assertSame(ConnecteurFrequenceType::ENTITE->value, $connecteurFrequenceData->type_connecteur);
        self::assertNull($connecteurFrequenceData->id_ce);
        self::assertSame(ConnecteurFrequenceActionType::DOCUMENT->value, $connecteurFrequenceData->action_type);
        self::assertSame('15', $connecteurFrequenceData->expression);
        self::assertSame('', $connecteurFrequenceData->libelle);
    }

    public function testFromArrayEmpty(): void
    {
        self::assertEquals(new ConnecteurFrequenceData(), ConnecteurFrequenceData::fromArray([]));
    }

    public function testFromArrayUnknownValues(): void
    {
        $connecteurFrequenceData = ConnecteurFrequenceData::fromArray([
            'type_connecteur' => 'Global',
            'action_type' => 'toto',
        ]);

        self::assertSame('Global', $connecteurFrequenceData->type_connecteur);
        self::assertSame('toto', $connecteurFrequenceData->action_type);
    }

    public function testWithFrequency(): void
    {
        $connecteurFrequenceData = (new ConnecteurFrequenceData(famille_connecteur: 'signature'))
            ->withFrequency('60', 'VERROU');

        self::assertSame('signature', $connecteurFrequenceData->famille_connecteur);
        self::assertSame('60', $connecteurFrequenceData->expression);
        self::assertSame('VERROU', $connecteurFrequenceData->id_verrou);
    }
}
