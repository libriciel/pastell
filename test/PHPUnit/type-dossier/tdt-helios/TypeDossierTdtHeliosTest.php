<?php

class TypeDossierTdtHeliosTest extends PastellTestCase
{
    public const TDT_HELIOS_ONLY = 'tdt-helios-only';

    /** @var TypeDossierLoader */
    private $typeDossierLoader;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->typeDossierLoader = $this->getObjectInstancier()->getInstance(TypeDossierLoader::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->typeDossierLoader->unload();
    }

    /**
     * @throws Exception
     */
    public function testEtapeTdtHelios()
    {
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::TDT_HELIOS_ONLY);

        $info_connecteur = $this->createConnector("fakeTdt", "Bouchon Tdt");
        $this->associateFluxWithConnector($info_connecteur['id_ce'], self::TDT_HELIOS_ONLY, "TdT");

        $info = $this->createDocument(self::TDT_HELIOS_ONLY);
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($info['id_d']);
        $donneesFormulaire->addFileFromCopy(
            'pes_aller',
            'fichier.xml',
            __DIR__ . "/../../module/helios-generique/fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml"
        );

        $this->assertTrue(
            $this->triggerActionOnDocument($info['id_d'], "fichier-pes-change")
        );

        $this->assertEquals([
            'objet' => 'HELIOS_SIMU_ALR2_1496987735_826268894.xml',
            'pes_aller' =>
                [
                    0 => 'fichier.xml',
                ],
            'id_coll' => '12345678912345',
            'dte_str' => '2017-06-09',
            'cod_bud' => '12',
            'exercice' => '2009',
            'id_bordereau' => '1234567',
            'montant_bordereau_ht' => '75724.75',
            'id_pj' => '',
            'id_pce' => '832',
            'id_nature' => '6553',
            'id_fonction' => '113',
            'pes_etat_ack' => '0',
            'pes_information_pes_aller' => '1',
            'envoi_tdt_helios' => 'checked',
        ], $this->getDonneesFormulaireFactory()->get($info['id_d'])->getRawData());

        $this->assertTrue(
            $this->triggerActionOnDocument($info['id_d'], "orientation")
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $this->assertLastDocumentAction('pre-send-tdt', $info['id_d']);

        $this->assertTrue(
            $this->triggerActionOnDocument($info['id_d'], "send-tdt")
        );
        $this->assertLastMessage("Le document a été envoyé au TdT");

        $this->assertTrue(
            $this->triggerActionOnDocument($info['id_d'], "verif-tdt")
        );
        $this->assertLastMessage("Une réponse est disponible pour ce fichier PES");

        $this->assertTrue(
            $this->triggerActionOnDocument($info['id_d'], "orientation")
        );
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $this->assertLastDocumentAction('termine', $info['id_d']);
    }
}
