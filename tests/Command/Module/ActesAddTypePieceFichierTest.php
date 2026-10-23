<?php

namespace Pastell\Tests\Command\Module;

use DocumentSQL;
use DonneesFormulaireFactory;
use Pastell\Command\Module\ActesAddTypePieceFichier;
use Pastell\Service\Document\DocumentEntiteService;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ActesAddTypePieceFichierTest extends PastellTestCase
{
    public function testActesAddTypePieceFichier()
    {
        $this->addActesGeneriqueWithoutTypePieceFichier();

        $command = new ActesAddTypePieceFichier(
            $this->getObjectInstancier()->getInstance(DocumentSQL::class),
            $this->getObjectInstancier()->getInstance(DocumentEntiteService::class),
            $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class)
        );
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);

        $commandTester->execute([
            'source' => 'actes-generique'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[OK] Success for 1 and failure for 0 ', $output);
    }

    private function addActesGeneriqueWithoutTypePieceFichier()
    {
        $connecteur_info = $this->createConnector("fakeTdt", "Bouchon tdt");
        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);
        $connecteurDonneesFormulaire->addFileFromCopy(
            'classification_file',
            "classification.xml",
            __DIR__ . "/fixtures/classification.xml"
        );
        $this->associateFluxWithConnector($connecteur_info['id_ce'], "actes-generique", "TdT");

        $document_info = $this->createDocument('actes-generique');
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document_info['id_d']);
        $donneesFormulaire->setTabData([
            'acte_nature' => 3,
            'type_acte' => "99_AI",
            'type_pj' => '["22_AT","41_AT"]'
        ]);
        $donneesFormulaire->addFileFromData('arrete', 'arrete.pdf', "foo");
        $donneesFormulaire->addFileFromData('autre_document_attache', 'annexe1.pdf', "bar", 0);
        $donneesFormulaire->addFileFromData('autre_document_attache', 'annexe2.pdf', "baz", 1);
    }
}
