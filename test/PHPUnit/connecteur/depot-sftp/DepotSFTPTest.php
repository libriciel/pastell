<?php

class DepotSFTPTest extends PastellTestCase
{
    /** @var  DepotSFTP */
    private $depotSFTP;

    protected function setUp(): void
    {
        parent::setUp();
        $SFTP = $this->createMock('SFTP');
        $SFTP->method('listDirectory')->willReturn(['foo']);

        $SFTPFactory = $this->createMock(SFTPFactory::class);
        $SFTPFactory->method('getInstance')->willReturn($SFTP);

        $connecteurConfig = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $connecteurConfig->setData(DepotSFTP::DEPOT_SFTP_DIRECTORY, '/foo/');

        $this->getObjectInstancier()->setInstance(SFTPFactory::class, $SFTPFactory);

        $this->depotSFTP = $this->getObjectInstancier()->getInstance(DepotSFTP::class);
        $this->depotSFTP->setLogger($this->getLogger());
        $this->depotSFTP->setConnecteurConfig($connecteurConfig);
    }

    /**
     * @throws Exception
     */
    public function testList()
    {
        $this->assertEquals(
            ['foo'],
            $this->depotSFTP->listDirectory()
        );
    }

    /**
     * @throws Exception
     */
    public function testMakeDirectory()
    {
        $this->assertEquals(
            '/foo/bar',
            $this->depotSFTP->makeDirectory('bar')
        );
    }

    /**
     * @throws Exception
     */
    public function testSaveDocument()
    {
        $this->assertEquals(
            '/foo/foo/bar',
            $this->depotSFTP->saveDocument('foo', 'bar', __DIR__ . "/fixtures/toto.txt")
        );
    }

    /**
     * @throws Exception
     */
    public function testSaveDocumentRename()
    {
        $SFTP = $this->createMock('SFTP');
        $SFTP->method('listDirectory')->willReturn(['foo']);

        $SFTPFactory = $this->createMock(SFTPFactory::class);
        $SFTPFactory->method('getInstance')->willReturn($SFTP);

        $connecteurConfig = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $connecteurConfig->setData(DepotSFTP::DEPOT_SFTP_DIRECTORY, '/foo/');
        $connecteurConfig->setData(DepotSFTP::DEPOT_SFTP_RENAME_SUFFIX, ".part");

        $this->depotSFTP = new DepotSFTP($SFTPFactory);
        $this->depotSFTP->setConnecteurConfig($connecteurConfig);
        $this->depotSFTP->setLogger($this->getLogger());
        $this->assertEquals(
            '/foo/foo/bar',
            $this->depotSFTP->saveDocument('foo', 'bar', __DIR__ . "/fixtures/toto.txt")
        );
    }

    /**
     * @throws Exception
     */
    public function testDirectoryExists()
    {
        $this->assertFalse(
            $this->depotSFTP->directoryExists('bar')
        );
    }

    /**
     * @throws Exception
     */
    public function testFileExists()
    {
        $this->assertFalse(
            $this->depotSFTP->fileExists('bar')
        );
    }
}
