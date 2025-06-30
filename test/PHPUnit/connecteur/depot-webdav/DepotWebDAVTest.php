<?php

class DepotWebDAVTest extends PastellTestCase
{
    /** @var  DepotWebDAV */
    private $depotWebDAV;

    protected function setUp(): void
    {
        parent::setUp();
        $webdavWrapper = $this->createMock(WebdavWrapper::class);
        $webdavWrapper->method('listFolder')->willReturn(['foo']);
        $webdavWrapper->method('exists')->willReturn(false);

        $connecteurConfig = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $connecteurConfig->setData(DepotWebDAV::DEPOT_WEBDAV_URL, 'https://foo/');

        $this->depotWebDAV = new DepotWebDAV($webdavWrapper);
        $this->depotWebDAV->setConnecteurConfig($connecteurConfig);
    }

    public function testList()
    {
        $this->assertEquals(
            ['foo'],
            $this->depotWebDAV->listDirectory()
        );
    }

    public function testMakeDirectory()
    {
        $this->assertEquals(
            'bar',
            $this->depotWebDAV->makeDirectory('bar')
        );
    }

    public function testSaveDocument()
    {
        $this->assertEquals(
            'https://foo/foo/bar',
            $this->depotWebDAV->saveDocument('foo', 'bar', __DIR__ . "/fixtures/toto.txt")
        );
    }

    public function testDirectoryExists()
    {
        $this->assertFalse(
            $this->depotWebDAV->directoryExists('bar')
        );
    }

    public function testFileExists()
    {
        $this->assertFalse(
            $this->depotWebDAV->fileExists('bar')
        );
    }
}
