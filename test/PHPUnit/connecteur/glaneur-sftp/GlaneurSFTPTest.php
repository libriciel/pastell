<?php

class GlaneurSFTPTest extends PastellTestCase
{
    /** @var  TmpFolder */
    private $tmpFolder;
    private $tmp_folder;
    private $directory_send;
    private $directory_error;

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

    private function getGlaneurSFTP(array $collectivite_properties)
    {
        $glaneurSFTP = $this->getObjectInstancier()->getInstance(GlaneurSFTP::class);
        $glaneurSFTP->setLogger($this->getLogger());
        $glaneurSFTP->setConnecteurInfo(['id_e' => 1]);
        $collectiviteProperties = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $collectiviteProperties->setTabData($collectivite_properties);
        $glaneurSFTP->setConnecteurConfig($collectiviteProperties);
        return $glaneurSFTP;
    }

    /**
     * @param $collectivite_properties
     * @return string
     * @throws Exception */
    private function glanerWithProperties(array $collectivite_properties, SFTPFactory $sftpFactory = null)
    {

        if (! $sftpFactory) {
            $sftpFactory = $this->getSFTPFactory();
        }
        $glaneurSFTP = $this->getGlaneurSFTP($collectivite_properties);
        $glaneurSFTP->setSFTPFactory($sftpFactory);
        return $glaneurSFTP->glaner();
    }


    /** @throws Exception */
    public function testGlanerVrac()
    {
        $sftp = $this->getMockBuilder(SFTP::class)->disableOriginalConstructor()->getMock();

        $sftp->expects($this->any())
            ->method('listDirectory')
            ->willReturn([".","..","vide1.pdf"]);

        $sftp->expects($this->any())
            ->method('isDir')
            ->willReturn(false);

        $sftp->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($a, $b) {
                copy($this->tmp_folder . "/vide1.pdf", $b);
                return true;
            });

        $sftpFactory = $this->getMockBuilder(SFTPFactory::class)->disableOriginalConstructor()->getMock();

        $sftpFactory->expects($this->any())
            ->method('getInstance')
            ->willReturn($sftp);

        mkdir($this->tmp_folder . "/" . "test1");
        copy(__DIR__ . "/fixtures/actes-automatique/vide1.pdf", $this->tmp_folder . "/vide1.pdf");
        $this->assertNotFalse(
            $this->glanerWithProperties([
                GlaneurConnecteur::TRAITEMENT_ACTIF => '1',
                GlaneurConnecteur::TYPE_DEPOT => GlaneurConnecteur::TYPE_DEPOT_VRAC,
                GlaneurConnecteur::FILE_PREG_MATCH => 'arrete: #.*#',
                GlaneurConnecteur::FLUX_NAME => 'actes-generique',
                GlaneurConnecteur::ACTION_OK => 'send-tdt',
                GlaneurConnecteur::DIRECTORY => $this->tmp_folder,
                GlaneurConnecteur::DIRECTORY_ERROR => $this->directory_error,
            ], $sftpFactory)
        );

        $document = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $id_d = $document->getAllByType('actes-generique')[0]['id_d'];
        $donneesFormulaireFactory = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEquals('vide1.pdf', $donneesFormulaireFactory->getFileName('arrete'));
        $this->assertFileEquals(__DIR__ . "/fixtures/actes-automatique/vide1.pdf", $donneesFormulaireFactory->getFilePath('arrete'));
    }


    /** @throws Exception */
    public function testGlanerFolder()
    {

        $sftp = $this->getMockBuilder(SFTP::class)->disableOriginalConstructor()->getMock();

        $sftp->expects($this->any())
            ->method('listDirectory')
            ->willReturnCallback(function ($b) {
                if (basename($b) == 'test1') {
                    return ['.','..','vide1.pdf'];
                } else {
                    return [".","..","test1"];
                }
            });

        $sftp->expects($this->any())
            ->method('isDir')
            ->willReturnCallback(function ($b) {
                return basename($b) == 'test1';
            });


        $sftp->expects($this->any())
            ->method('exists')
            ->willReturnCallback(function ($b) {
                return false;
            });

        $sftp->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($a, $b) {
                copy($a, $b);
                return true;
            });


        $sftpFactory = $this->getMockBuilder(SFTPFactory::class)->disableOriginalConstructor()->getMock();

        $sftpFactory->expects($this->any())
            ->method('getInstance')
            ->willReturn($sftp);


        mkdir($this->tmp_folder . "/" . "test1");
        copy(__DIR__ . "/fixtures/actes-automatique/vide1.pdf", $this->tmp_folder . "/test1/vide1.pdf");
        $this->assertNotFalse(
            $this->glanerWithProperties([
                GlaneurConnecteur::TRAITEMENT_ACTIF => '1',
                GlaneurConnecteur::TYPE_DEPOT => GlaneurConnecteur::TYPE_DEPOT_FOLDER,
                GlaneurConnecteur::FILE_PREG_MATCH => 'arrete: #.*#',
                GlaneurConnecteur::FLUX_NAME => 'actes-generique',
                GlaneurConnecteur::ACTION_OK => 'send-tdt',
                GlaneurConnecteur::DIRECTORY => $this->tmp_folder,
                GlaneurConnecteur::DIRECTORY_SEND => $this->directory_send,
                GlaneurConnecteur::DIRECTORY_ERROR => $this->directory_error,
            ], $sftpFactory)
        );

        $document = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $id_d = $document->getAllByType('actes-generique')[0]['id_d'];
        $donneesFormulaireFactory = $this->getDonneesFormulaireFactory()->get($id_d);
        $this->assertEquals('vide1.pdf', $donneesFormulaireFactory->getFileName('arrete'));
        $this->assertFileEquals(
            __DIR__ . "/fixtures/actes-automatique/vide1.pdf",
            $donneesFormulaireFactory->getFilePath('arrete')
        );
    }

    /**
     * @throws Exception
     */
    public function testListFile()
    {
        $sftp = $this->getMockBuilder(SFTP::class)->disableOriginalConstructor()->getMock();

        $sftp->expects($this->any())
            ->method('listDirectory')
            ->willReturnCallback(function ($b) {
                if (basename($b) == 'test1') {
                    return ['.','..','foo.txt'];
                } else {
                    return [".","..","test1"];
                }
            });

        $sftp->expects($this->any())
            ->method('isDir')
            ->willReturnCallback(function ($b) {
                return basename($b) == 'test1';
            });


        $sftp->expects($this->any())
            ->method('exists')
            ->willReturnCallback(function ($b) {
                return false;
            });

        $sftp->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($a, $b) {
                copy($a, $b);
            });


        $sftpFactory = $this->getMockBuilder(SFTPFactory::class)->disableOriginalConstructor()->getMock();

        $sftpFactory->expects($this->any())
            ->method('getInstance')
            ->willReturn($sftp);

        $glaneurSFTP = $this->getGlaneurSFTP([
            GlaneurConnecteur::TRAITEMENT_ACTIF => '1',
            GlaneurConnecteur::TYPE_DEPOT => GlaneurConnecteur::TYPE_DEPOT_FOLDER,
            GlaneurConnecteur::FILE_PREG_MATCH => 'arrete: #.*#',
            GlaneurConnecteur::FLUX_NAME => 'actes-generique',
            GlaneurConnecteur::ACTION_OK => 'send-tdt',
            GlaneurConnecteur::DIRECTORY => $this->tmp_folder,
            GlaneurConnecteur::DIRECTORY_SEND => $this->directory_send,
            GlaneurConnecteur::DIRECTORY_ERROR => $this->directory_error,
        ]);

        $glaneurSFTP->setSFTPFactory($sftpFactory);
        $this->assertMatchesRegularExpression("#test1#", $glaneurSFTP->listDirectories());
    }

    /**
     * @return SFTPFactory
     */
    private function getSFTPFactory()
    {
        $sftp = $this->getMockBuilder(SFTP::class)->disableOriginalConstructor()->getMock();

        $sftp->expects($this->any())
            ->method('listDirectory')
            ->willReturnCallback(function ($b) {
                if (basename($b) == 'test1') {
                    return ['.','..','foo.txt'];
                } else {
                    return [".","..","test1"];
                }
            });

        $sftp->expects($this->any())
            ->method('isDir')
            ->willReturnCallback(function ($b) {
                return basename($b) == 'test1';
            });


        $sftp->expects($this->any())
            ->method('exists')
            ->willReturn(false);

        $sftp->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($a, $b) {
                copy($a, $b);
            });


        $sftpFactory = $this->getMockBuilder(SFTPFactory::class)->disableOriginalConstructor()->getMock();

        $sftpFactory->expects($this->any())
            ->method('getInstance')
            ->willReturn($sftp);
        return $sftpFactory;
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testGlanerFicExample()
    {
        $glaneurSFTP = $this->getObjectInstancier()->getInstance(GlaneurSFTP::class);
        $glaneurSFTP->setLogger($this->getLogger());
        $glaneurSFTP->setConnecteurInfo(['id_e' => 1]);
        $collectiviteProperties = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $collectiviteProperties->setTabData([
            GlaneurConnecteur::TRAITEMENT_ACTIF => '1',
            GlaneurConnecteur::TYPE_DEPOT => GlaneurConnecteur::TYPE_DEPOT_ZIP,
            GlaneurConnecteur::FILE_PREG_MATCH => 'fichier_reponse: /^(.*)_ack.xml$/' . "\n" . 'fichier_pes: /^$matches[0][1].xml$/',
            GlaneurConnecteur::METADATA_STATIC =>
                "objet: %fichier_pes%\n
                envoi_sae: true\n
                has_reponse: true",
            GlaneurConnecteur::FLUX_NAME => 'helios-automatique',
            GlaneurConnecteur::ACTION_OK => 'importation',
            GlaneurConnecteur::DIRECTORY => $this->tmp_folder,
            GlaneurConnecteur::DIRECTORY_SEND => $this->directory_send,
            GlaneurConnecteur::DIRECTORY_ERROR => $this->directory_error,
        ]);
        $collectiviteProperties->addFileFromCopy(
            GlaneurConnecteur::FICHER_EXEMPLE,
            'pes.zip',
            __DIR__ . '/fixtures/HELIOS_SIMU_ALR2_1547544424_844200543.zip'
        );
        $glaneurSFTP->setConnecteurConfig($collectiviteProperties);

        $glaneurSFTP->setSFTPFactory($this->getSFTPFactory());

        $id_d = $glaneurSFTP->glanerFicExemple();
        $this->assertSame("Création du document $id_d", $glaneurSFTP->getLastMessage()[0]);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $this->assertSame(
            'HELIOS_SIMU_ALR2_1547544424_844200543.xml',
            $donneesFormulaire->getFileName('fichier_pes')
        );
        $this->assertSame(
            'HELIOS_SIMU_ALR2_1547544424_844200543_ack.xml',
            $donneesFormulaire->getFileName('fichier_reponse')
        );
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testEmptyHeliosFile(): void
    {
        $zipLocation = $this->tmp_folder . '/zip.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipLocation, ZipArchive::CREATE)) {
            $zip->addFromString('empty.xml', '');
            $zip->close();
        }

        $glaneurSFTP = $this->getObjectInstancier()->getInstance(GlaneurSFTP::class);
        $glaneurSFTP->setLogger($this->getLogger());
        $glaneurSFTP->setConnecteurInfo(['id_e' => 1]);
        $collectiviteProperties = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $collectiviteProperties->setTabData([
            GlaneurConnecteur::TRAITEMENT_ACTIF => '1',
            GlaneurConnecteur::TYPE_DEPOT => GlaneurConnecteur::TYPE_DEPOT_ZIP,
            GlaneurConnecteur::FILE_PREG_MATCH => 'fichier_pes: /^(.*)\.xml$/',
            GlaneurConnecteur::METADATA_STATIC => 'envoi_tdt: true',
            GlaneurConnecteur::FLUX_NAME => 'helios-automatique',
            GlaneurConnecteur::ACTION_OK => 'importation',
            GlaneurConnecteur::DIRECTORY => $this->tmp_folder,
            GlaneurConnecteur::DIRECTORY_SEND => $this->directory_send,
            GlaneurConnecteur::DIRECTORY_ERROR => $this->directory_error,
        ]);
        $collectiviteProperties->addFileFromCopy(
            GlaneurConnecteur::FICHER_EXEMPLE,
            'pes.zip',
            $zipLocation
        );
        $glaneurSFTP->setConnecteurConfig($collectiviteProperties);

        $glaneurSFTP->setSFTPFactory($this->getSFTPFactory());

        $id_d = $glaneurSFTP->glanerFicExemple();
        $this->assertSame("Création du document $id_d", $glaneurSFTP->getLastMessage()[0]);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $this->assertSame(
            'empty.xml',
            $donneesFormulaire->getFileName('fichier_pes')
        );

        $this->assertLastDocumentAction('fatal-error', $id_d);
    }
}
