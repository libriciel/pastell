<?php

class GlaneurConnecteurTest extends PastellTestCase
{
    /** @var  TmpFolder */
    private $tmpFolder;
    private $tmp_folder;
    private $directory_send;
    private $directory_error;

    private $last_message;
    private $created_id_d;

    private $workspace_path;

    /** @throws Exception */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpFolder = new TmpFolder();
        $this->tmp_folder = $this->tmpFolder->create();
        $this->directory_send = $this->tmpFolder->create();
        $this->directory_error = $this->tmpFolder->create();

        $this->workspace_path = $this->tmpFolder->create();
        $this->getObjectInstancier()->setInstance('workspacePath', $this->workspace_path);
    }

    protected function tearDown(): void
    {
        $this->tmpFolder->delete($this->tmp_folder);
        $this->tmpFolder->delete($this->directory_send);
        $this->tmpFolder->delete($this->directory_error);
        $this->tmpFolder->delete($this->workspace_path);
    }

    private function getGlaneurLocal(array $collectivite_properties)
    {
        $glaneurLocal = $this->getObjectInstancier()->getInstance(GlaneurLocalMock::class);
        $glaneurLocal->setLogger($this->getLogger());
        $glaneurLocal->setConnecteurInfo(['id_e' => 1]);
        $collectiviteProperties = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $collectiviteProperties->setTabData($collectivite_properties);
        $glaneurLocal->setConnecteurConfig($collectiviteProperties);
        return $glaneurLocal;
    }

    /**
     * @param $collectivite_properties
     * @return string
     * @throws Exception */
    private function glanerWithProperties(array $collectivite_properties)
    {
        $glaneurLocal = $this->getGlaneurLocal($collectivite_properties);
        $result = $glaneurLocal->glaner();
        $this->last_message = $glaneurLocal->getLastMessage();
        $this->created_id_d = $result;
        return $result;
    }

    /**
     * @throws Exception
     */
    public function testGlanerNotExistingFlux()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(__DIR__ . "/fixtures/foo.txt", $this->tmp_folder . "/" . "test1/foo.txt");

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Impossible de trouver le type not-existing-flux sur ce pastell");
        $this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::FLUX_NAME => 'not-existing-flux',
            GlaneurLocalMock::FILE_PREG_MATCH => 'fichier_pes: #.*#',
        ]);
    }

    /**
     * @throws Exception
     */
    public function testGlanerNotExistingTypeDepot()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(__DIR__ . "/fixtures/foo.txt", $this->tmp_folder . "/" . "test1/foo.txt");

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le type d'éléments à glaner est inconnu");
        $this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => "foo",
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
            GlaneurLocalMock::FILE_PREG_MATCH => 'fichier_pes: #.*#',
        ]);
    }


    /** @throws Exception */
    public function testGlanerDirectoryEmpty()
    {
        $this->expectExceptionMessage("The \"\" directory does not exist.");
        $this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER
        ]);
    }

    /** @throws Exception */
    public function testGlanerDirectoryNotFound()
    {
        $this->expectExceptionMessage("The \"foo\" directory does not exist.");
        $this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
            GlaneurLocalMock::DIRECTORY => 'foo'
        ]);
    }

    /** @throws Exception */
    public function testGlanerEmptyDirectory()
    {
        $this->assertTrue($this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder
        ]));
        $this->assertEquals(["Le répertoire est vide"], $this->last_message);
    }

    /** @throws Exception */
    public function testGlanerFolderFileNoFlux()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(__DIR__ . "/fixtures/foo.txt", $this->tmp_folder . "/" . "test1/foo.txt");
        $this->expectExceptionMessage("Impossible de trouver le nom du flux à créer");
        $this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder
        ]);
    }

    /** @throws Exception */
    public function testGlanerOneFile()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(__DIR__ . "/fixtures/vide1.pdf", $this->tmp_folder . "/" . "test1/vide1.pdf");
        //$this->expectException("Exception");
        //$this->expectExceptionMessage("Le formulaire est incomplet : le champ «Nature de l'acte» est obligatoire.");
        $this->assertNotFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::DIRECTORY_ERROR => $this->directory_error,
                GlaneurLocalMock::FILE_PREG_MATCH => 'arrete: #.*#',
                GlaneurLocalMock::FLUX_NAME => 'actes-generique',
                GlaneurLocalMock::ACTION_OK => 'send-tdt'

            ])
        );
    }

    /**
     * @throws Exception
     */
    public function testGlanerPES()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1514362287_770650402.xml",
            $this->tmp_folder . "/" . "test1/test.xml"
        );

        $this->assertNotFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::DIRECTORY_SEND  => $this->directory_send,
                GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
                GlaneurLocalMock::METADATA_STATIC => 'objet:Bordereau de test',
                GlaneurLocalMock::FILE_PREG_MATCH => 'fichier_pes: #.*#',
                GlaneurLocalMock::ACTION_OK => 'importation',
                GlaneurLocalMock::ACTION_KO => 'erreur'
            ])
        );

        $this->assertMatchesRegularExpression("#Création du document#", $this->last_message[0]);
        $id_d = $this->created_id_d;

        $document = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $info = $document->getInfo($id_d);
        $this->assertEquals("Bordereau de test", $info['titre']);
        $this->assertEquals("helios-automatique", $info['type']);

        $journal = $this->getJournal()->getAll(1, 'helios-automatique', $id_d, 0, 0, 100);
        $this->assertEquals("[glaneur] Passage en action_ok : importation", $journal[0]['message']);
        $this->assertEquals("[glaneur] Import du document", $journal[1]['message']);


        $donneesFormulaireFactory = $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);
        $donneesFormulaire = $donneesFormulaireFactory->get($id_d);
        $this->assertEquals("Bordereau de test", $donneesFormulaire->get('objet'));
        $this->assertFileEquals(
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1514362287_770650402.xml",
            $donneesFormulaire->getFilePath('fichier_pes')
        );
        $this->assertFileDoesNotExist($this->tmp_folder . "/" . "test1");
        $this->assertFileExists($this->directory_send . "/" . "test1");
    }

    /**
     * @throws Exception
     */
    public function testGlanerDirectoryWithManyFiles()
    {

        mkdir($this->tmp_folder . "/" . "test1");
        $src = __DIR__ . "/fixtures/many_files";
        $dest = $this->tmp_folder . "/" . "test1";
        shell_exec("cp  $src/* $dest");


        $this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::DIRECTORY_SEND  => $this->directory_send,
            GlaneurLocalMock::FLUX_NAME => 'test',
            GlaneurLocalMock::METADATA_STATIC => 'test2:toto',
            GlaneurLocalMock::FILE_PREG_MATCH => 'fichier: #.*#',
            GlaneurLocalMock::ACTION_OK => 'importation',
            GlaneurLocalMock::ACTION_KO => 'erreur'
        ]);

        $id_d = $this->created_id_d;

        $donneesFormulaireFactory = $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);
        $donneesFormulaire = $donneesFormulaireFactory->get($id_d);

        $this->assertEquals(["a.txt","b.txt","c.txt"], $donneesFormulaire->get('fichier'));
    }


    /**
     * @throws Exception
     */
    public function testMetadataWithFileName()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1514362287_770650402.xml",
            $this->tmp_folder . "/" . "test1/test.xml"
        );

        $this->assertNotFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
                GlaneurLocalMock::METADATA_STATIC => 'objet: %fichier_pes%',
                GlaneurLocalMock::FILE_PREG_MATCH => 'fichier_pes: #.*#',
                GlaneurLocalMock::ACTION_OK => 'importation',
                GlaneurLocalMock::ACTION_KO => 'erreur'
            ])
        );

        $this->assertMatchesRegularExpression("#Création du document#", $this->last_message[0]);
        $id_d = $this->created_id_d;

        $document = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $info = $document->getInfo($id_d);
        $this->assertEquals("test.xml", $info['titre']);

        $donneesFormulaireFactory = $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);
        $donneesFormulaire = $donneesFormulaireFactory->get($id_d);
        $this->assertEquals("test.xml", $donneesFormulaire->get('objet'));
    }

    /**
     * @throws Exception
     */
    public function testMetadataWithBadFileName()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1514362287_770650402.xml",
            $this->tmp_folder . "/" . "test1/test.xml"
        );

        $this->assertFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::DIRECTORY_ERROR => $this->directory_error,
                GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
                GlaneurLocalMock::METADATA_STATIC => 'objet: %not-existing-element%',
                GlaneurLocalMock::FILE_PREG_MATCH => 'fichier_pes: #.*#',
                GlaneurLocalMock::ACTION_OK => 'importation',
                GlaneurLocalMock::ACTION_KO => 'erreur'
            ])
        );
        $this->assertEquals(['not-existing-element n\'a pas été trouvé dans la correspondance des fichiers'], $this->last_message);
    }

    /**
     * @throws Exception
     */
    public function testGlanerDeleteFolder()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1514362287_770650402.xml",
            $this->tmp_folder . "/" . "test1/test.xml"
        );

        $this->assertNotFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
                GlaneurLocalMock::METADATA_STATIC => 'objet:Bordereau de test',
                GlaneurLocalMock::FILE_PREG_MATCH => 'fichier_pes: #.*#',
                GlaneurLocalMock::ACTION_OK => 'importation',
                GlaneurLocalMock::ACTION_KO => 'erreur'
            ])
        );


        $this->assertFileDoesNotExist($this->tmp_folder . "/" . "test1");
        $this->assertFileDoesNotExist($this->directory_send . "/" . "test1");
    }


    /**
     * @throws Exception
     */
    public function testGlanerDepotVrac()
    {

        $fixtures_dir = __DIR__ . "/fixtures/pes_depot_vrac/";
        foreach (scandir($fixtures_dir) as $file) {
            if (is_file($fixtures_dir . "/" . $file)) {
                copy($fixtures_dir . "/" . $file, $this->tmp_folder . "/$file");
            }
        }

        $this->assertNotFalse($this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_VRAC,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
                GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
                GlaneurLocalMock::FILE_PREG_MATCH =>  'fichier_pes: #^(PESALR2.*)$#' . "\n" . 'fichier_reponse:#^ACQUIT_$matches[0][1]$#',
                GlaneurLocalMock::METADATA_STATIC => "objet:%fichier_pes%\nenvoi_sae:true\nhas_information_complementaire:true",
                GlaneurLocalMock::ACTION_OK => 'importation',
                GlaneurLocalMock::ACTION_KO => 'erreur'
            ]));

        $this->assertMatchesRegularExpression("#Création du document#", $this->last_message[0]);

        $id_d = $this->created_id_d;
        $document = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $info = $document->getInfo($id_d);
        $this->assertEquals("PESALR2_49101169800000_171227_2045.xml", $info['titre']);
        $this->assertEquals("helios-automatique", $info['type']);

        $donneesFormulaireFactory = $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);
        $donneesFormulaire = $donneesFormulaireFactory->get($id_d);
        $this->assertEquals("PESALR2_49101169800000_171227_2045.xml", $donneesFormulaire->get('objet'));
        $this->assertFileEquals(
            __DIR__ . "/fixtures/pes_depot_vrac/PESALR2_49101169800000_171227_2045.xml",
            $donneesFormulaire->getFilePath('fichier_pes')
        );

        $this->assertFileExists($this->directory_send . "/PESALR2_49101169800000_171227_2045.xml");
        $this->assertFileExists($this->directory_send . "/ACQUIT_PESALR2_49101169800000_171227_2045.xml");
        $this->assertFileDoesNotExist($this->tmp_folder . "/PESALR2_49101169800000_171227_2045.xml");
    }

    /**
     * @throws Exception
     */
    public function testGlanerVracEmpty()
    {
        $this->assertTrue($this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_VRAC,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
            GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
            GlaneurLocalMock::FILE_PREG_MATCH =>  'fichier_pes: #^(PESALR2.*)$#' . "\n" . 'fichier_reponse:#ACQUIT_$matches[1][1]#',
            GlaneurLocalMock::METADATA_STATIC => "objet:%fichier_pes%\nenvoi_sae:true\nhas_information_complementaire:true",
            GlaneurLocalMock::ACTION_OK => 'importation',
            GlaneurLocalMock::ACTION_KO => 'erreur'
        ]));
        $this->assertMatchesRegularExpression("#Le répertoire est vide#", $this->last_message[0]);
    }

    /**
     * @throws Exception
     */
    public function testGlanerZip()
    {
        copy(__DIR__ . "/fixtures/pes_exemple.zip", $this->tmp_folder . "/pes_exemple.zip");

        $this->assertNotFalse($this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_ZIP,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
            GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
            GlaneurLocalMock::FILE_PREG_MATCH =>  'fichier_pes: #^(PESALR2.*)$#' . "\n" . 'fichier_reponse:#ACQUIT_$matches[1][1]#',
            GlaneurLocalMock::METADATA_STATIC => "objet:%fichier_pes%\nenvoi_sae:true\nhas_information_complementaire:true",
            GlaneurLocalMock::ACTION_OK => 'importation',
            GlaneurLocalMock::ACTION_KO => 'erreur'
        ]));

        $this->assertMatchesRegularExpression("#Création du document#", $this->last_message[0]);

        $id_d = $this->created_id_d;
        $document = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $info = $document->getInfo($id_d);
        $this->assertEquals("PESALR2_49101169800000_171227_2045.xml", $info['titre']);
        $this->assertEquals("helios-automatique", $info['type']);
        $this->assertFileExists($this->directory_send . "/pes_exemple.zip");
        $this->assertFileDoesNotExist($this->tmp_folder . "/pes_exemple.zip");
    }

    /**
     * @throws Exception
     */
    public function testMenageExists()
    {
        copy(__DIR__ . "/fixtures/pes_exemple.zip", $this->tmp_folder . "/pes_exemple.zip");
        copy(__DIR__ . "/fixtures/pes_exemple.zip", $this->directory_send . "/pes_exemple.zip");

        $this->assertNotFalse($this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_ZIP,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
            GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
            GlaneurLocalMock::FILE_PREG_MATCH =>  'fichier_pes: #^(PESALR2.*)$#' . "\n" . 'fichier_reponse:#ACQUIT_$matches[1][1]#',
            GlaneurLocalMock::METADATA_STATIC => "objet:%fichier_pes%\nenvoi_sae:true\nhas_information_complementaire:true",
            GlaneurLocalMock::ACTION_OK => 'importation',
            GlaneurLocalMock::ACTION_KO => 'erreur'
        ]));

        $this->assertFileExists($this->directory_send . "/pes_exemple.zip-0");
        $this->assertFileDoesNotExist($this->tmp_folder . "/pes_exemple.zip");
    }

    /**
     * @throws Exception
     */
    public function testGlanerZipEmptyFolder()
    {

        $this->assertNotFalse($this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_ZIP,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
            GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
            GlaneurLocalMock::FILE_PREG_MATCH =>  'fichier_pes: #^(PESALR2.*)$#' . "\n" . 'fichier_reponse:#ACQUIT_$matches[1][1]#',
            GlaneurLocalMock::METADATA_STATIC => "objet:%fichier_pes%\nenvoi_sae:true\nhas_information_complementaire:true",
            GlaneurLocalMock::ACTION_OK => 'importation',
            GlaneurLocalMock::ACTION_KO => 'erreur'
        ]));
        $this->assertMatchesRegularExpression("#Le répertoire est vide#", $this->last_message[0]);
    }

    /**
     * @throws Exception
     */
    public function testGlanerZipNotAZipFile()
    {
        copy(__DIR__ . "/fixtures/foo.txt", $this->tmp_folder . "/pes_exemple.zip");

        $this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_ZIP,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
            GlaneurLocalMock::DIRECTORY_ERROR => $this->directory_error,
            GlaneurLocalMock::FLUX_NAME => 'helios-automatique',
            GlaneurLocalMock::FILE_PREG_MATCH => 'fichier_pes: #^(PESALR2.*)$#' . "\n" . 'fichier_reponse:#ACQUIT_$matches[1][1]#',
            GlaneurLocalMock::METADATA_STATIC => "objet:%fichier_pes%\nenvoi_sae:true\nhas_information_complementaire:true",
            GlaneurLocalMock::ACTION_OK => 'importation',
            GlaneurLocalMock::ACTION_KO => 'erreur'
        ]);
        $this->assertMatchesRegularExpression("#Impossible d'ouvrir le fichier zip#", $this->last_message[0]);
    }

    /**
     * @throws Exception
     */
    public function testGlanerManifest()
    {

        mkdir($this->tmp_folder . "/test1/");
        $fixtures_dir = __DIR__ . "/fixtures/pes_manifest/";
        foreach (scandir($fixtures_dir) as $file) {
            if (is_file($fixtures_dir . "/" . $file)) {
                copy($fixtures_dir . "/" . $file, $this->tmp_folder . "/test1/$file");
            }
        }

        $this->assertNotFalse($this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
            GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
            GlaneurLocalMock::MANIFEST_TYPE => GlaneurLocalMock::MANIFEST_TYPE_XML,
            GlaneurLocalMock::ACTION_KO => 'erreur'
        ]));

        $this->assertMatchesRegularExpression("#Création du document#", $this->last_message[0]);

        $id_d = $this->created_id_d;
        $document = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $info = $document->getInfo($id_d);
        $this->assertEquals("Exemple d'import d'un fichier PES", $info['titre']);
        $this->assertEquals("helios-automatique", $info['type']);

        $donneesFormulaireFactory = $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class);
        $donneesFormulaire = $donneesFormulaireFactory->get($id_d);
        $this->assertEquals("Exemple d'import d'un fichier PES", $donneesFormulaire->get('objet'));
        $this->assertFileEquals(
            __DIR__ . "/fixtures/pes_depot_vrac/PESALR2_49101169800000_171227_2045.xml",
            $donneesFormulaire->getFilePath('fichier_pes')
        );
    }

    /**
     * @throws Exception
     */
    public function testGlanerManifestNoManifest()
    {

        mkdir($this->tmp_folder . "/test1/");
        $fixtures_dir = __DIR__ . "/fixtures/pes_manifest/";
        foreach (scandir($fixtures_dir) as $file) {
            if (is_file($fixtures_dir . "/" . $file)) {
                if ($file == 'manifest.xml') {
                    continue;
                }
                copy($fixtures_dir . "/" . $file, $this->tmp_folder . "/test1/$file");
            }
        }

        $this->assertFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
                GlaneurLocalMock::DIRECTORY_ERROR => $this->directory_error,
                GlaneurLocalMock::MANIFEST_TYPE => GlaneurLocalMock::MANIFEST_TYPE_XML,
                GlaneurLocalMock::ACTION_KO => 'erreur'
            ])
        );
        $this->assertEquals(["Le fichier manifest.xml n'existe pas"], $this->last_message);
    }

    /**
     * @throws Exception
     */
    public function testGlanerNoActif()
    {
        $this->assertFalse($this->glanerWithProperties([
            GlaneurLocalMock::TRAITEMENT_ACTIF => '0',
        ]));
        $this->assertEquals(["Le traitement du glaneur est désactivé"], $this->last_message);
    }

    /**
     * @throws Exception
     */
    public function testListDirectories()
    {
        file_put_contents($this->tmp_folder . "/foo.txt", "bar");
        $glaneurLocal = $this->getGlaneurLocal([
            GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
            GlaneurLocalMock::DIRECTORY_ERROR => $this->directory_error,
            GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send
        ]);

        $directories_info = $glaneurLocal->listDirectories();
        $this->assertStringContainsString("directory - 1 fichier", $directories_info);
    }

    /**
     * @throws Exception
     */
    public function testGlanerFolderButItsAFile()
    {
        file_put_contents($this->tmp_folder . "/foo.txt", "bar");
        $this->assertFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::DIRECTORY_SEND => $this->directory_send,
                GlaneurLocalMock::DIRECTORY_ERROR => $this->directory_error,
                GlaneurLocalMock::MANIFEST_TYPE => GlaneurLocalMock::MANIFEST_TYPE_XML,
                GlaneurLocalMock::ACTION_KO => 'erreur'
            ])
        );
        $this->assertFileExists($this->directory_error . "/foo.txt");
        $this->assertFileDoesNotExist($this->tmp_folder . "/foo.txt");
        $this->assertFileDoesNotExist($this->directory_send . "/foo.txt");
    }

    /**
     * @throws Exception
     */
    public function testGlanerPESActionKO()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1514362287_770650402.xml",
            $this->tmp_folder . "/" . "test1/test.xml"
        );

        $this->assertNotFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::DIRECTORY_SEND  => $this->directory_send,
                GlaneurLocalMock::DIRECTORY_ERROR => $this->directory_error,
                GlaneurLocalMock::FLUX_NAME => 'actes-automatique',
                GlaneurLocalMock::FILE_PREG_MATCH => 'arrete: #.*#',
                GlaneurLocalMock::ACTION_OK => 'importation',
                GlaneurLocalMock::ACTION_KO => 'erreur',
            ])
        );

        $this->assertMatchesRegularExpression("#Création du document#", $this->last_message[0]);
        $id_d = $this->created_id_d;

        $journal = $this->getJournal()->getAll(1, 'actes-automatique', $id_d, 0, 0, 100);
        $this->assertEquals("[glaneur] Le dossier n'est pas valide : Le formulaire est incomplet : le champ «Nature de l'acte» est obligatoire.", $journal[0]['message']);
        $this->assertEquals("[glaneur] Import du document", $journal[1]['message']);
    }

    /**
     * @throws Exception
     */
    public function testGlanerPESForceActionOK()
    {
        mkdir($this->tmp_folder . "/" . "test1");
        copy(
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1514362287_770650402.xml",
            $this->tmp_folder . "/" . "test1/test.xml"
        );

        $this->assertNotFalse(
            $this->glanerWithProperties([
                GlaneurLocalMock::TRAITEMENT_ACTIF => '1',
                GlaneurLocalMock::TYPE_DEPOT => GlaneurLocalMock::TYPE_DEPOT_FOLDER,
                GlaneurLocalMock::DIRECTORY => $this->tmp_folder,
                GlaneurLocalMock::DIRECTORY_SEND  => $this->directory_send,
                GlaneurLocalMock::FLUX_NAME => 'actes-automatique',
                GlaneurLocalMock::FILE_PREG_MATCH => 'arrete: #.*#',
                GlaneurLocalMock::FORCE_ACTION_OK => true,
                GlaneurLocalMock::ACTION_OK => 'importation',
                GlaneurLocalMock::ACTION_KO => 'erreur'
            ])
        );

        $this->assertMatchesRegularExpression("#Création du document#", $this->last_message[0]);
        $id_d = $this->created_id_d;

        $journal = $this->getJournal()->getAll(1, 'actes-automatique', $id_d, 0, 0, 100);
        $this->assertEquals("[glaneur] Passage en action_ok forcé : importation", $journal[0]['message']);
        $this->assertEquals("[glaneur] Import du document", $journal[1]['message']);
    }
}
