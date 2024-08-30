<?php

use PHPUnit\Framework\TestCase;

class PESV2XMLTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testWithSpecialChar()
    {
        $pesAllerFile = new PESAllerFile();
        $info = $pesAllerFile->getAllInfo(
            __DIR__ . "/../fixtures/HELIOS_SIMU_ALR2_LibelleColBud_avec_accent.xml"
        );

        $this->assertEquals([
            'IdColl' => '12345678912345',
            'DteStr' => '2017-06-09',
            'CodBud' => '12',
            'LibelleColBud' => 'Test é(-è_çà)=',
            'Exercice' => '2009',
            'IdBord' => '1234567',
            'IdPJ' => '',
            'IdPce' => '832',
            'NomFic' => 'HELIOS_SIMU_ALR2_1496987735_826268894.xml',
            'IdNature' => '6553',
            'IdFonction' => '113',
            'MtBordHt' => '75724.75',
        ], $info);
    }
}
