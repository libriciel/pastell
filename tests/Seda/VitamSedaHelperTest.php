<?php

declare(strict_types=1);

namespace Pastell\Tests\Seda;

use Pastell\Helpers\SedaHelper;
use Pastell\Seda\VitamSedaHelper;
use Pastell\Tests\Helpers\SedaHelperTestCase;

final class VitamSedaHelperTest extends SedaHelperTestCase
{
    public function sedaHelper(): SedaHelper
    {
        return new VitamSedaHelper();
    }

    public static function atrProviderOk(): iterable
    {
        yield 'vitam OK' => [
            __DIR__ . '/../Helpers/fixtures/atr_vitam_ok.xml',
            [
                'MessageIdentifier' => 'aeeaaaaaacfdatmhaahbiamc2a76zpyaaaaq',
                'MessageRequestIdentifier' => 'ef311f05-2a6f-40fe-b015-00b8ebeafcbd',
                'ArchiveUnitIdentifier' => 'aeaqaaaaaafdat3yadroqamc2a77c6yaaaba',
                'Comment' => '',
            ],
        ];
    }

    public static function atrProviderKo(): iterable
    {
        yield 'vitam KO' => [
            __DIR__ . '/../Helpers/fixtures/atr_vitam_ko.xml',
            [
                'MessageIdentifier' => 'aeeaaaaaacfdatmhabqumamcgwajmciaaaaq',
                'MessageRequestIdentifier' => '',
                'ArchiveUnitIdentifier' => 'AE_2022_15',
                'Comment' => "Échec du contrôle de cohérence entre le profil d'archivage déclaré dans le bordereau
                de transfert et celui déclaré dans le contrat d'entrée Detail= KO:1",
            ],
        ];
    }
}
