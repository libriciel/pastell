<?php

class ExtensionsTest extends PastellTestCase
{
    private function getExtensionTestPath()
    {
        return realpath(__DIR__ . '/../fixtures/extensions/extension-test/');
    }

    private function getExtensionSQLMock($extensionSQLGetAllResult)
    {
        $extensionSQL = $this->createMock('ExtensionSQL');
        $extensionSQL->method('getAll')->willReturn($extensionSQLGetAllResult);
        if (isset($extensionSQLGetAllResult[0])) {
            $extensionSQL->method('getInfo')->willReturn($extensionSQLGetAllResult[0]);
        }
        return $extensionSQL;
    }

    private function getManifestFactory()
    {
        return new ManifestFactory(__DIR__ . "/../fixtures/", new YMLLoader(new MemoryCacheNone()));
    }

    private function getExtensions($extensionSQLGetAllResult)
    {
        $extensionSQL = $this->getExtensionSQLMock($extensionSQLGetAllResult);
        /** @var ExtensionSQL $extensionSQL */
        return new Extensions(
            $extensionSQL,
            $this->getManifestFactory(),
            "/tmp",
            new MemoryCacheNone(),
            10,
            "/tmp/"
        );
    }

    private function getExtensionsTest()
    {
        $extension_test_path = $this->getExtensionTestPath();
        return $this->getExtensions([['id_e' => '42','path' => $extension_test_path]]);
    }

    public function testGetAllEmpty()
    {
        $extensions = $this->getExtensions([]);
        $this->assertEmpty($extensions->getAll());
    }

    public function testGetAll()
    {
        $extensions = $this->getExtensionsTest();

        $this->assertArrayHasKey(42, $extensions->getAll());
    }

    public function testGetAllConnecteurEmpty()
    {
        $extensions = $this->getExtensions([]);
        $this->assertEmpty($extensions->getAllConnecteur());
    }

    public function testGetAllConnecteur()
    {
        $extensions = $this->getExtensionsTest();
        $this->assertArrayHasKey('connecteur-test', $extensions->getAllConnecteur());
    }

    public function testGetConnecteurPathNotExists()
    {
        $extensions = $this->getExtensionsTest();
        $path = $extensions->getConnecteurPath('foo');
        $this->assertFalse($path);
    }

    public function testGetConnecteurPath()
    {
        $extensions = $this->getExtensionsTest();
        $path = $extensions->getConnecteurPath('connecteur-test');
        $this->assertEquals($this->getExtensionTestPath() . "/connecteur/connecteur-test", $path);
    }

    public function testGetAllModule()
    {
        $extensions = $this->getExtensionsTest();
        $this->assertArrayHasKey('module-test', $extensions->getAllModule());
    }

    public function testGetModulePathEmpty()
    {
        $extensions = $this->getExtensionsTest();
        $path = $extensions->getModulePath('bar');
        $this->assertFalse($path);
    }

    public function testGetModulePath()
    {
        $extensions = $this->getExtensionsTest();
        $path = $extensions->getModulePath('module-test');
        $this->assertEquals($this->getExtensionTestPath() . "/module/module-test", $path);
    }

    public function testGetInfo()
    {
        $extensions = $this->getExtensionsTest();
        $info = $extensions->getInfo(42);
        $this->assertEquals('glaneur', $info['id']);
    }

    public function testGetInfoRevisionNotOK()
    {
        $extension_test_path = $this->getExtensionTestPath();
        $extensionSQLGetAllResult = [['id_e' => '42','path' => $extension_test_path]];

        $extensions = $this->getExtensions($extensionSQLGetAllResult);
        $info = $extensions->getInfo(42);
        $this->assertNotEmpty($info['warning']);
    }

    public function testGetInfoNotExists()
    {
        $extensionSQLGetAllResult = [['id_e' => '42','path' => 'foo']];
        $extensions = $this->getExtensions($extensionSQLGetAllResult);
        $info = $extensions->getInfo(42);
        $this->assertNotEmpty($info['error']);
    }

    public function testGetInfoNoManifest()
    {
        $extensionSQLGetAllResult = [['id_e' => '42','path' => '/tmp']];
        $extensions = $this->getExtensions($extensionSQLGetAllResult);
        $info = $extensions->getInfo(42);
        $this->assertEquals("Le fichier manifest.yml n'a pas été trouvé dans /tmp", $info['warning-detail']);
    }

    public function testExecuteActionsOnConnector(): void
    {
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->flushAll();

        $this->getObjectInstancier()
            ->getInstance(ExtensionLoader::class)
            ->loadExtension([$this->getExtensionTestPath()]);

        $connector = $this->createConnector('connecteur-test', 'connector');

        $this->assertTrue(
            $this->triggerActionOnConnector($connector['id_ce'], 'test')
        );
        $this->assertLastMessage('Action done');

        $this->assertFalse(
            $this->triggerActionOnConnector($connector['id_ce'], 'test-not-loaded')
        );
        $this->assertLastMessage(
            'En essayant d\'inclure ExtensionTestActionTestNotLoaded : Class "ExtensionTestActionTestNotLoaded" does not exist'
        );
    }

    /**
     * @dataProvider versionProvider
     * @throws Exception
     */
    public function testAcceptedVersion(string $expected_version, bool $expectedResult): void
    {
        $extension_test_path = $this->getExtensionTestPath();
        $manifestReader = $this->getObjectInstancier()->getInstance(ManifestFactory::class)->getManifest($extension_test_path);
        static::assertSame($expectedResult, $manifestReader->isVersionOK($expected_version));
    }

    public function versionProvider(): \Generator
    {
        yield ['0.0.1', false];
        yield ['0.9', false];
        yield ['1.0.0', false];
        yield ['1.3.0', false];
        yield ['4.4.0', false];
        yield ['4.0.0', true];
        yield ['4.0', true];
        yield ['4', true];
        yield ['3.0', false];
        yield ['3.0.9999', false];
        yield ['3.9999', false];
    }

    public function testGetAllTypeEtape(): void
    {
        $extensionPathWithTypeEtape = realpath(__DIR__ . '/type-dossier/fixtures/extension_test/');
        $extensions = $this->getExtensions([['id_e' => '99', 'path' => $extensionPathWithTypeEtape]]);
        $allTypeDossier = $extensions->getAllTypeDossier();

        static::assertArrayHasKey('hal-9000', $allTypeDossier);
        static::assertEquals($extensionPathWithTypeEtape . '/type-dossier/hal-9000', $allTypeDossier['hal-9000']);
    }

    /**
     * @throws Exception
     */
    public function testGetInfoFromPathWithTypeEtape(): void
    {
        $extensionPathWithTypeEtape = realpath(__DIR__ . '/type-dossier/fixtures/extension_test/');
        $extensions = $this->getExtensions([['id_e' => '99', 'path' => $extensionPathWithTypeEtape]]);
        $info = $extensions->getInfo(99);

        static::assertArrayHasKey('type_etape', $info);
        static::assertIsArray($info['type_etape']);
        static::assertContains('hal-9000', $info['type_etape']);
    }

    public function testGetTypeEtapePath(): void
    {
        $extensionPathWithTypeEtape = realpath(__DIR__ . '/type-dossier/fixtures/extension_test/');
        $extensions = $this->getExtensions([['id_e' => '99', 'path' => $extensionPathWithTypeEtape]]);
        $path = $extensions->getTypeDossierPath('hal-9000');

        static::assertEquals($extensionPathWithTypeEtape . '/type-dossier/hal-9000', $path);
    }

    public function testGetTypeDossierPathNotExists(): void
    {
        $extensions = $this->getExtensionsTest();
        $path = $extensions->getTypeDossierPath('non-existent-type');

        static::assertFalse($path);
    }
}
