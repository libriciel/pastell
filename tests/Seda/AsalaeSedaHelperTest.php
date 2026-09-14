<?php

declare(strict_types=1);

namespace Pastell\Tests\Seda;

use Pastell\Helpers\SedaHelper;
use Pastell\Seda\AsalaeSedaHelper;
use Pastell\Tests\Helpers\SedaHelperTestCase;

final class AsalaeSedaHelperTest extends SedaHelperTestCase
{
    public function sedaHelper(): SedaHelper
    {
        return new AsalaeSedaHelper();
    }

    public static function atrProviderOk(): iterable
    {
        yield 'asalae OK' => [
            __DIR__ . '/../Helpers/fixtures/atr_asalae_ok.xml',
            [
                'MessageIdentifier' => '15ef78ef665a8777c33d1125783707f8dfb190f82869dc9248e46c5ed396d70b_1542893421',
                'MessageRequestIdentifier' => 'FRLSADC_ATR_27',
                'ArchiveUnitIdentifier' => 'AE_2022_15',
                'Comment' => 'Versement flux test pastell dev',
            ],
        ];
    }

    public static function atrProviderKo(): iterable
    {
        yield 'asalae KO' => [
            __DIR__ . '/../Helpers/fixtures/atr_asalae_ko.xml',
            [
                'MessageIdentifier' => '1bc19af9-2212-4e5a-956a-36591c4ac27a',
                'MessageRequestIdentifier' => 'FRADCH_ATR_17388',
                'ArchiveUnitIdentifier' => '',
                'Comment' => "Transfert de l'acte administratif TRDYTVB",
            ],
        ];
    }
}
