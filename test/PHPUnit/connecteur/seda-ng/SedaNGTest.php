<?php

class SedaNGTest extends PastellTestCase
{
    private SedaNG $sedaNG;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sedaNG = $this->getObjectInstancier()->getInstance(SedaNG::class);
    }
    /**
     * @throws Exception
     */
    public function testGenerateArchive()
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        $archive_path = $tmp_folder . "/archive.tar.gz";

        $fluxData = $this->createMock(FluxData::class);

        $fluxData->method('getFilelist')->willReturn([[
            'key' => 'fichier',
            'filename' => 'connecteur_exemple.yml',
            'filepath' => __DIR__ . '/fixtures/connecteur_exemple.yml',
        ]
        ]);

        /** @var FluxData $fluxData */
        $this->sedaNG->generateArchive($fluxData, $archive_path);

        exec("tar xvzf $archive_path -C $tmp_folder 2>/dev/null");
        $tmp_content = scandir($tmp_folder);
        $this->assertEquals('connecteur_exemple.yml', $tmp_content[3]);
        $this->assertFileEquals(
            __DIR__ . '/fixtures/connecteur_exemple.yml',
            $tmp_folder . "/$tmp_content[3]"
        );
    }

    /**
     * @throws Exception
     */
    public function testGenerateArchiveFileInSubFolder()
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        $archive_path = $tmp_folder . "/archive.tar.gz";

        $fluxData = $this->createMock(FluxData::class);

        $fluxData->method('getFilelist')->willReturn([[
            'key' => 'fichier',
            'filename' => 'fixtures/connecteur_exemple.yml',
            'filepath' => __DIR__ . '/fixtures/connecteur_exemple.yml',
        ]
        ]);

        /** @var FluxData $fluxData */
        $this->sedaNG->generateArchive($fluxData, $archive_path);

        exec("tar xvzf $archive_path -C $tmp_folder 2>/dev/null");
        $tmp_content = scandir($tmp_folder . "/fixtures/");
        $this->assertEquals('connecteur_exemple.yml', $tmp_content[2]);
        $this->assertFileEquals(
            __DIR__ . '/fixtures/connecteur_exemple.yml',
            $tmp_folder . "/fixtures/$tmp_content[2]"
        );
        $tmpFolder->delete($tmp_folder);
    }

    /**
     * @throws Exception
     */
    public function testgetProprietePastellConnecteur()
    {
        $connecteurConfig = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $connecteurConfig->addFileFromCopy(
            'schema_rng',
            'shema.rng',
            __DIR__ . "/fixtures/connecteur_info_schema.rng"
        );
        $connecteurConfig->addFileFromCopy(
            'profil_agape',
            'profil_agape.xml',
            __DIR__ . "/fixtures/connecteur_info.xml"
        );
        $this->sedaNG->setConnecteurConfig($connecteurConfig);

        $info = $this->sedaNG->getProprietePastellConnecteur();
        $this->assertEquals(['id_service_archive','id_producteur_hors_rh','id_producteur_rh'], $info);
    }

    /**
     * @doesNotPerformAssertions
     * @throws Exception
     */
    public function testGenerateArchiveLimit(): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        $archive_path = $tmp_folder . "/archive.tar.gz";

        $fluxData = $this->createMock(FluxData::class);

        $fileList = [];

        for ($i = 0; $i < 1500; ++$i) {
            $fileList[] = [
                'filename' => "___________________________________________________this is a very long file name_$i.yml",
                'filepath' => __DIR__ . '/fixtures/connecteur_exemple.yml',
            ];
        }
        $fluxData->method('getFilelist')->willReturn($fileList);

        $this->sedaNG->generateArchive($fluxData, $archive_path);
        $tmpFolder->delete($tmp_folder);
    }
}
