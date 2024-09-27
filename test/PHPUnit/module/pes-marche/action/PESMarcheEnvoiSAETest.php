<?php

class PESMarcheEnvoiSAETest extends PastellMarcheTestCase
{
    private const PES_MARCHE = 'pes-marche';

    private $id_d;
    private $id_ce;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnecteurForTypeDossier(self::PES_MARCHE, FakeSAE::CONNECTEUR_ID);

        $this->id_ce = $this->createConnecteurForTypeDossier(self::PES_MARCHE, SedaNG::CONNECTEUR_ID);

        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($this->id_ce);
        $connecteurDonneesFormulaire->addFileFromCopy('schema_rng', "profil.rng", __DIR__ . "/../profil/Profil_PES_Marche_LS_V1.rng");
        $connecteurDonneesFormulaire->addFileFromCopy('profil_agape', 'profil.xml', __DIR__ . "/../profil/Profil_PES_Marche_LS_V1.xml");
        $connecteurDonneesFormulaire->addFileFromCopy('connecteur_info_content', 'connecteur_info_content.json', __DIR__ . "/../profil/Profil_PES_Marche_LS_V1.json");
        $connecteurDonneesFormulaire->addFileFromCopy('flux_info_content', 'flux_info_content.json', __DIR__ . "/../profil/Profil_PES_Marche_LS_V1.json");


        $this->id_d = $this->createDocument(self::PES_MARCHE)['id_d'];
    }

    public function testValidateBordereauTest()
    {
        $connecteurFactory = $this->getObjectInstancier()->getInstance(ConnecteurFactory::class);

        /** @var SedaNG $sedaNG */
        $sedaNG = $connecteurFactory->getConnecteurById($this->id_ce);
        $bordereau =  $sedaNG->getBordereauTest();

        try {
            $this->assertTrue(
                $sedaNG->validateBordereau($bordereau)
            );
        } catch (Exception $e) {
            echo $bordereau;
            print_r($sedaNG->getLastValidationError());
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function testOK()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($this->id_d);
        $donneesFormulaire->setTabData([
            'objet' => 'test'
        ]);

        $donneesFormulaire->addFileFromCopy('fichier_pes', 'PESALR2_XYZ.xml', __DIR__ . "/../fixtures/exemple_marche_contrat_initial_nov2017.xml");
        $donneesFormulaire->addFileFromCopy('fichier_reponse', 'PESALR2_XYZ.xml', __DIR__ . "/../fixtures/exemple_marche_contrat_initial_nov2017.xml");

        $result = $this->triggerActionOnDocument($this->id_d, 'send-archive');

        if (! $result) {
            $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($this->id_d);
            echo $donneesFormulaire->getFileContent('sae_bordereau');
        }
        $this->assertLastMessage("Le document a été envoyé au SAE");

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($this->id_d);
        $xml = simplexml_load_file($donneesFormulaire->getFilePath('sae_bordereau'));
        $children = $xml->children(SedaValidation::SEDA_V_1_0_NS);

        $children->{'Date'} = 'NOT TESTABLE';
        $children->{'TransferIdentifier'} = "NOT TESTABLE";

        $this->assertStringEqualsFile(__DIR__ . "/../fixtures/bordereau.xml", $xml->asXML());

        $donneesFormulaire->getFilePath('sae_bordereau');
        $sae_archive = $donneesFormulaire->getFileContent('sae_archive');

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        file_put_contents("$tmp_folder/archive.tgz", $sae_archive);
        exec("tar xvzf $tmp_folder/archive.tgz -C $tmp_folder 2>/dev/null");

        $this->assertEquals(['.','..','PESALR2_XYZ.xml','archive.tgz'], scandir("$tmp_folder/"));

        $tmpFolder->delete($tmp_folder);
    }
}
