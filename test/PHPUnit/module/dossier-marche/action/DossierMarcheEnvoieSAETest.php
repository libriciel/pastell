<?php

class DossierMarcheEnvoieSAETest extends PastellMarcheTestCase
{
    private const DOSSIER_MARCHE = 'dossier-marche';

    private $id_d;
    private $id_ce;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createConnecteurForTypeDossier(self::DOSSIER_MARCHE, FakeSAE::CONNECTEUR_ID);

        $this->id_ce = $this->createConnecteurForTypeDossier(self::DOSSIER_MARCHE, SedaNG::CONNECTEUR_ID);

        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($this->id_ce);
        $connecteurDonneesFormulaire->addFileFromCopy('schema_rng', "profil.rng", __DIR__ . "/../profil/PROFIL_DOSSIERS_MARCHES_LS_schema.rng");
        $connecteurDonneesFormulaire->addFileFromCopy('profil_agape', 'profil.xml', __DIR__ . "/../profil/PROFIL_DOSSIERS_MARCHES_LS.xml");
        $connecteurDonneesFormulaire->addFileFromCopy('connecteur_info_content', 'connecteur_info_content.json', __DIR__ . "/../profil/PROFIL_DOSSIERS_MARCHES_LS.json");
        $connecteurDonneesFormulaire->addFileFromCopy('flux_info_content', 'flux_info_content.json', __DIR__ . "/../profil/PROFIL_DOSSIERS_MARCHES_LS.json");

        $this->id_d = $this->createDocument(self::DOSSIER_MARCHE)['id_d'];
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
            "date_debut" => '1981-01-01',
            "date_fin" => '1982-07-12',
            "date_notification" => '1982-07-13',
            "numero_consultation" => "123456",
            "numero_marche" => "123",
            "code_cpv" => "200001_X",
            "type_consultation" =>  "MAPA",
            "type_marche" =>  "S",
            "infructueux" => 1,
            "recurrent" => 1,
            "attributaire" =>  "Libriciel SCOP\nAPI",
            "mot_cle" =>  "Mot clé 1\nMot clé 2",
            "libelle" =>  "Achat d'un bus logiciel",
            "contenu_versement" => "premiere partie"
        ]);

        $donneesFormulaire->addFileFromCopy('fichier_zip', 'archive.zip', __DIR__ . "/../fixtures/42007_achat_de_materiel_de_bureau.zip");

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

        $this->assertEquals(['.',
            '..',
            '1. Pr--paration',
            '11.4. Offres non retenues',
            '12.5. Envoi Contr--leur Financier',
            '2. Lancement',
            '3. Analyses',
            'OFFRES DEMAT',
            'archive.tgz'
        ], scandir("$tmp_folder/"));

        $this->assertEquals("non vide\n", file_get_contents("$tmp_folder/OFFRES DEMAT/ADULLACT/foo.txt"));

        $tmpFolder->delete($tmp_folder);
    }

    /**
     * @throws Exception
     */
    public function testNoDateNotification()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($this->id_d);
        $donneesFormulaire->setTabData([
            "date_debut" => '1981-01-01',
            "date_fin" => '1982-07-12',
            "date_notification" => '', //Si pas de date de notif
            "numero_consultation" => "123456",
            "numero_marche" => "123",
            "code_cpv" => "200001_X",
            "type_consultation" =>  "MAPA",
            "type_marche" =>  "S",
            "infructueux" => 1,
            "recurrent" => 1,
            "attributaire" =>  "Libriciel SCOP\nAPI",
            "libelle" =>  "Achat d'un bus logiciel",
            "contenu_versement" => "premiere partie"
        ]);

        $donneesFormulaire->addFileFromCopy('fichier_zip', 'archive.zip', __DIR__ . "/../fixtures/42007_achat_de_materiel_de_bureau.zip");

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

        $this->assertStringEqualsFile(__DIR__ . "/../fixtures/bordereau-no-date-notification.xml", $xml->asXML());
    }

    /**
     * @throws Exception
     */
    public function testWithMetadataJson()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($this->id_d);
        $donneesFormulaire->setTabData([
            "date_debut" => '1981-01-01',
            "date_fin" => '1982-07-12',
            "date_notification" => '1982-07-13',
            "numero_consultation" => "123456",
            "numero_marche" => "123",
            "code_cpv" => "200001_X",
            "type_consultation" =>  "MAPA",
            "type_marche" =>  "S",
            "infructueux" => 1,
            "recurrent" => 1,
            "attributaire" =>  "Libriciel SCOP\nAPI",
            "libelle" =>  "Achat d'un bus logiciel",
            "contenu_versement" => "premiere partie"
        ]);

        $donneesFormulaire->addFileFromCopy('fichier_zip', 'archive.zip', __DIR__ . "/../fixtures/42007_achat_de_materiel_de_bureau.zip");
        $donneesFormulaire->addFileFromCopy('sae_config', 'config_sae.json', __DIR__ . "/../fixtures/config_sae.json");

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

        $this->assertStringEqualsFile(__DIR__ . "/../fixtures/bordereau_metadata_json.xml", $xml->asXML());
    }
}
