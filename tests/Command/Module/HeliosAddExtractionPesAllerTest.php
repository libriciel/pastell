<?php

namespace Pastell\Tests\Command\Module;

use ActionExecutorFactory;
use DocumentSQL;
use DonneesFormulaireFactory;
use Pastell\Command\Module\HeliosAddExtractionPesAller;
use Pastell\Service\Document\DocumentEntiteService;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class HeliosAddExtractionPesAllerTest extends PastellTestCase
{
    public function testHeliosAddExtractionPes()
    {
        $id_d = $this->createDocument('helios-automatique')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->addFileFromCopy(
            'fichier_pes',
            'fichier.xml',
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml"
        );
        $donneesFormulaire->setData('etat_ack', 2);
        $this->assertFalse($this->getDonneesFormulaireFactory()->get($id_d)->get('id_coll'));

        $command = new HeliosAddExtractionPesAller(
            $this->getObjectInstancier()->getInstance(DocumentSQL::class),
            $this->getObjectInstancier()->getInstance(DocumentEntiteService::class),
            $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class),
            $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)
        );
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['source' => 'helios-automatique']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[OK] Success for 1 and failure for 0 ', $output);

        $this->assertEquals([
            'objet' => 'HELIOS_SIMU_ALR2_1496987735_826268894.xml',
            'id_coll' => '12345678912345',
            'dte_str' => '2017-06-09',
            'cod_bud' => '12',
            'exercice' => '2009',
            'id_bordereau' => '1234567',
            'id_pj' => '',
            'id_pce' => '832',
            'id_nature' => '6553',
            'id_fonction' => '113',
            'fichier_pes' => [0 => 'fichier.xml'],
            'etat_ack' => '2'
        ], $this->getDonneesFormulaireFactory()->get($id_d)->getRawData());
    }
}
