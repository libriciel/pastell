<?php

class PieceMarcheEnvoiSAETest extends PastellMarcheTestCase
{
    private const PIECE_MARCHE = 'piece-marche';

    private $id_d;
    private $id_ce;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createConnecteurForTypeDossier(self::PIECE_MARCHE, FakeSAE::CONNECTEUR_ID);

        $this->id_ce = $this->createConnecteurForTypeDossier(self::PIECE_MARCHE, SedaNG::CONNECTEUR_ID);

        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($this->id_ce);
        $connecteurDonneesFormulaire->addFileFromCopy('schema_rng', "profil.rng", __DIR__ . "/../profil/PROFIL_PIECES_MARCHES_LS.rng");
        $connecteurDonneesFormulaire->addFileFromCopy('profil_agape', 'profil.xml', __DIR__ . "/../profil/PROFIL_PIECES_MARCHES_LS.xml");
        $connecteurDonneesFormulaire->addFileFromCopy('connecteur_info_content', 'connecteur_info_content.json', __DIR__ . "/../profil/PROFIL_PIECES_MARCHES_LS.json");
        $connecteurDonneesFormulaire->addFileFromCopy('flux_info_content', 'flux_info_content.json', __DIR__ . "/../profil/PROFIL_PIECES_MARCHES_LS.json");

        $this->id_d = $this->createDocument(self::PIECE_MARCHE)['id_d'];
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
            "date_document" => "2018-01-01",
            "libelle" => "mon marché",
            "numero_marche" => "1234",
            "type_marche" => "T",
            "numero_consultation" => "12",
            "type_consultation" => "MAPA",
            "etape" => "EB",
            "type_piece_marche" => "AC",
            "libelle_piece" => "pièce",
            "soumissionnaire" => "toto",
        ]);

        $donneesFormulaire->addFileFromCopy('document', 'vide.pdf', __DIR__ . "/../fixtures/vide.pdf");

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

        //$this->assertStringEqualsFile(__DIR__."/../fixtures/bordereau.xml",$xml->asXML());

        $donneesFormulaire->getFilePath('sae_bordereau');

        $sae_archive = $donneesFormulaire->getFileContent('sae_archive');

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        file_put_contents("$tmp_folder/archive.tgz", $sae_archive);
        exec("tar xvzf $tmp_folder/archive.tgz -C $tmp_folder 2>/dev/null");

        $this->assertFileExists($tmp_folder . '/archive.tgz');
        $this->assertFileExists($tmp_folder . '/vide.pdf');

        $tmpFolder->delete($tmp_folder);
    }
}
