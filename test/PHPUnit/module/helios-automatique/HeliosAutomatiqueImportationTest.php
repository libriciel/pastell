<?php

final class HeliosAutomatiqueImportationTest extends PastellTestCase
{
    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     */
    public function testPesInfoIsExtractedOnImportationAction(): void
    {
        $document = $this->createDocument('helios-automatique');
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $donneesFormulaire->addFileFromCopy(
            'fichier_pes',
            'pes.xml',
            __DIR__ . '/../helios-generique/fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml'
        );

        $this->getObjectInstancier()->getInstance(ActionChange::class)
            ->addAction($document['id_d'], self::ID_E_COL, 0, 'importation', 'message');

        $this->triggerActionOnDocument($document['id_d'], 'orientation');
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);

        $this->assertSame('2009', $donneesFormulaire->get('exercice'));
    }
}
