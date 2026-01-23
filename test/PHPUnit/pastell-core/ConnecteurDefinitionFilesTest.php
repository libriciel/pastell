<?php

use Pastell\Service\Pack\PackService;

class ConnecteurDefinitionFilesTest extends PastellTestCase
{
    /** @var  ConnecteurDefinitionFiles */
    private $connecteurDefinitionFiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connecteurDefinitionFiles =
            $this->getObjectInstancier()->getInstance(ConnecteurDefinitionFiles::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->setListPack(["suppl_test" => true]);
    }

    public function testGetAllType()
    {
        $result = $this->connecteurDefinitionFiles->getAllType();
        $this->assertContains("mailsec", $result);
    }

    public function testGetAllTypeTwoConnecteur()
    {
        $this->getInternalAPI()->post(
            "/Extension/",
            ['path' => __DIR__ . '/../fixtures/extensions/extension-test']
        );
        $result = $this->connecteurDefinitionFiles->getAllType();
        $this->assertEquals(1, array_count_values($result)['test']);
    }

    public function testGetAllRestricted()
    {
        $this->setListPack(["suppl_test" => false]);
        $result = $this->connecteurDefinitionFiles->getAllRestricted();
        $this->assertContains("test", $result);
        $result = $this->connecteurDefinitionFiles->getAllRestricted(true);
        $this->assertContains("test", $result);

        $this->setListPack(["suppl_test" => true]);
        $result = $this->connecteurDefinitionFiles->getAllRestricted();
        $this->assertEmpty($result);
        $result = $this->connecteurDefinitionFiles->getAllRestricted(true);
        $this->assertEmpty($result);
    }


    private function mockExtensionsWithFixtures(): void
    {
        $fixturesPath = __DIR__ . '/fixtures/connectors';

        $extensions = $this->createMock('Extensions');
        $extensions
            ->method('getAllConnecteur')
            ->willReturn([
                'allowed-on-entite-racine' => $fixturesPath . '/allowed-on-entite-racine',
                'not-allowed-on-entite-racine' => $fixturesPath . '/not-allowed-on-entite-racine',
            ]);

        $this->getObjectInstancier()->setInstance(Extensions::class, $extensions);
        $this->connecteurDefinitionFiles = new ConnecteurDefinitionFiles(
            $extensions,
            $this->getObjectInstancier()->getInstance(YMLLoader::class),
            $this->getObjectInstancier()->getInstance(PackService::class)
        );
    }

    public function testGetAll(): void
    {
        $this->mockExtensionsWithFixtures();
        $result = $this->connecteurDefinitionFiles->getAllConnecteursEntite(false);
        static::assertArrayHasKey('allowed-on-entite-racine', $result);
        static::assertArrayHasKey('not-allowed-on-entite-racine', $result);
        static::assertCount(2, $result);
    }

    public function testGetAllEntiteRacine(): void
    {
        $this->mockExtensionsWithFixtures();
        $result = $this->connecteurDefinitionFiles->getAllConnecteursEntite(true);
        static::assertArrayHasKey('allowed-on-entite-racine', $result);
        static::assertArrayNotHasKey('not-allowed-on-entite-racine', $result);
        static::assertCount(1, $result);
    }
}
