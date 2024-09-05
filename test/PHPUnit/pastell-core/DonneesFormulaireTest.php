<?php

class DonneesFormulaireTest extends PastellTestCase
{
    /**
     * @return DonneesFormulaire
     * @throws Exception
     */
    private function getDonneesFormulaire()
    {
        return $this->getDonneesFormulaireFactory()->get('toto', 'test');
    }

    /**
     * @throws Exception
     * @dataProvider getPassword
     */
    public function testPassword(string $password)
    {
        $recuperateur = new Recuperateur(['password' => $password]);
        $this->getDonneesFormulaire()->saveTab($recuperateur, new FileUploader(), 0);
        $this->assertEquals($password, $this->getDonneesFormulaire()->get('password'));
    }

    public function getPassword()
    {
        return [
                ['215900689B']
        ];
    }

    public function getProvider(): \Generator
    {
        // On checkbox field
        yield ['ma_checkbox', 'true', true];
        yield ['ma_checkbox', 'false', false];
        yield ['ma_checkbox', '0', '0'];
        yield ['ma_checkbox', '1', '1'];
        yield ['ma_checkbox', 'on', true];
        yield ['ma_checkbox', 'On', true];
        yield ['ma_checkbox', 'off', false];
        yield ['ma_checkbox', 'Off', false];
        yield ['ma_checkbox', 0, 0];
        yield ['ma_checkbox', 1, 1];
        yield ['ma_checkbox', true, true];
        yield ['ma_checkbox', false, false];
        // On text field
        yield ['test2', 'true', 'true'];
        yield ['test2', 'false', 'false'];
        yield ['test2', '0', '0'];
        yield ['test2', '1', '1'];
        yield ['test2', 'on', 'on'];
        yield ['test2', 'On', 'On'];
        yield ['test2', 'off', 'off'];
        yield ['test2', 'Off', 'Off'];
        yield ['test2', 0, 0];
        yield ['test2', 1, 1];
        yield ['test2', true, true];
        yield ['test2', false, false];
    }

    /**
     * @dataProvider getProvider
     * @throws NotFoundException
     */
    public function testGet(string $field, mixed $value, mixed $expected): void
    {
        $form = $this->getDonneesFormulaireFactory()->get('id_d', 'test');
        $form->setData($field, $value);
        self::assertSame($expected, $form->get($field));
    }

    /*
     * Bon, ben il semblerait que ca soit fait exprès...
     public function testTrue(){
        $this->getDonneesFormulaire()->setData('foo','true');
        $this->assertEquals('true',$this->getDonneesFormulaire()->get('foo'));
    }
    */

    private function getDonneesFormulaireChampsCache()
    {
        return $this->getCustomDonneesFormulaire(__DIR__ . "/../fixtures/definition-champs-cache.yml");
    }

    /**
     * @param $path_to_yaml_definition
     * @return DonneesFormulaire
     */
    private function getCustomDonneesFormulaire($path_to_yaml_definition): DonneesFormulaire
    {
        $filePath = $this->getObjectInstancier()->getInstance('workspacePath') . "/YZZT.yml";
        $ymlLoader = new YMLLoader(new MemoryCacheNone());
        $module_definition = $ymlLoader->getArray($path_to_yaml_definition);
        $documentType = new DocumentType("test-fichier", $module_definition);

        return new DonneesFormulaire($filePath, $documentType);
    }

    public function testModifOngletCache()
    {
        $donneesFormulaire = $this->getDonneesFormulaireChampsCache();
        $donneesFormulaire->setData("chaine", "12");
        $this->assertEquals("12", $donneesFormulaire->get("chaine"));
    }

    /**
     * @throws Exception
     */
    public function testModifOngletCacheFichier()
    {
        $donneesFormulaire = $this->getDonneesFormulaireChampsCache();
        $donneesFormulaire->addFileFromData("fichier_visible", "test.txt", "texte");
        $this->assertEquals("texte", $donneesFormulaire->getFileContent("fichier_visible"));
    }

    public function testSaveAllFile()
    {
        $donneesFormulaire = $this->getDonneesFormulaireChampsCache();
        $donneesFormulaire->setData("chaine", "12");

        $file_path = $this->getObjectInstancier()->getInstance('workspacePath') . "/test.txt";
        file_put_contents($file_path, "texte");

        $files = ['fichier_visible' => ['tmp_name' => $file_path,'error' => UPLOAD_ERR_OK,'name' => 'test.txt']];

        $fileUploader = new FileUploader();
        $fileUploader->setFiles($files);
        $donneesFormulaire->saveAllFile($fileUploader);
        $this->assertEquals("texte", $donneesFormulaire->getFileContent('fichier_visible'));
    }

    public function testSaveAllFileHidden()
    {
        $donneesFormulaire = $this->getDonneesFormulaireChampsCache();
        $donneesFormulaire->setData("chaine", "12");

        $file_path = $this->getObjectInstancier()->getInstance('workspacePath') . "/test.txt";
        file_put_contents($file_path, "texte");

        $files = ['fichier_hidden' => ['tmp_name' => $file_path,'error' => UPLOAD_ERR_OK,'name' => 'test.txt']];

        $fileUploader = new FileUploader();
        $fileUploader->setFiles($files);
        $donneesFormulaire->saveAllFile($fileUploader);
        $this->assertEquals("texte", $donneesFormulaire->getFileContent('fichier_hidden'));
    }

    public function testSaveAllFileSaveAgain()
    {
        $donneesFormulaire = $this->getCustomDonneesFormulaire(
            __DIR__ . "/fixtures/definition-for-save-all.yml"
        );

        $file_path = $this->getObjectInstancier()->getInstance('workspacePath') . "/test.txt";
        file_put_contents($file_path, "foo");

        $files = ['mon_fichier' => ['tmp_name' => $file_path,'error' => UPLOAD_ERR_OK,'name' => 'test.txt']];

        $fileUploader = new FileUploader();
        $fileUploader->setFiles($files);
        $donneesFormulaire->saveAllFile($fileUploader);
        $this->assertEquals("foo", $donneesFormulaire->getFileContent('mon_fichier'));

        $file_path = $this->getObjectInstancier()->getInstance('workspacePath') . "/test.txt";
        file_put_contents($file_path, "bar");

        $donneesFormulaire->saveAllFile($fileUploader);

        $this->assertEquals("bar", $donneesFormulaire->getFileContent('mon_fichier'));
    }

    /**
     * @throws Exception
     */
    public function testSerializeExport()
    {
        $donneesFormulaire = $this->getDonneesFormulaire();
        $donneesFormulaire->setData('foo', 'bar');
        $json = $donneesFormulaire->jsonExport();
        $info = json_decode($json, true);
        $this->assertEquals('bar', $info['metadata']['foo']);
    }

    /**
     * @throws Exception
     */
    public function testSerializeExportEmtpy()
    {
        $donneesFormulaire = $this->getDonneesFormulaire();
        $json = $donneesFormulaire->jsonExport();
        $info = json_decode($json, true);
        $this->assertEmpty($info['metadata']);
    }

    /**
     * @throws Exception
     */
    public function testSerializeExportFile()
    {
        $donneesFormulaire = $this->getDonneesFormulaire();
        $donneesFormulaire->setData('foo', 'bar');
        $file_content = "Hello World!";
        $donneesFormulaire->addFileFromData('fichier', 'test.txt', $file_content);
        $json = $donneesFormulaire->jsonExport();
        $info = json_decode($json, true);
        $this->assertEquals($file_content, base64_decode($info['file']['fichier'][0]));
    }

    /**
     * @throws Exception
     */
    public function testSerializeImport()
    {
        $donneesFormulaire = $this->getDonneesFormulaire();
        $donneesFormulaire->setData('foo', 'bar');
        $data = $donneesFormulaire->jsonExport();

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get("bar", "baz");
        $this->assertFalse($donneesFormulaire->get('foo'));

        $donneesFormulaire->jsonImport($data);
        $this->assertEquals("bar", $donneesFormulaire->get('foo'));
    }

    /**
     * @throws Exception
     */
    public function testSerializeImportFile()
    {
        $donneesFormulaire = $this->getDonneesFormulaire();
        $donneesFormulaire->setData('foo', 'bar');
        $file_content = "Hello World!";
        $donneesFormulaire->addFileFromData('fichier', 'test.txt', $file_content);
        $data = $donneesFormulaire->jsonExport();

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get("bar", "baz");
        $this->assertFalse($donneesFormulaire->get('fichier'));

        $donneesFormulaire->jsonImport($data);
        $this->assertEquals($file_content, $donneesFormulaire->getFileContent('fichier'));
    }

    /**
     * @throws Exception
     */
    public function testImportFileFailed()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Impossible de déchiffrer le fichier");
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get("bar", "baz");
        $donneesFormulaire->jsonImport("toto");
    }

    /**
     * @throws Exception
     */
    public function testImportFileFailedJson()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Clé metadata absente du fichier");
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get("bar", "baz");
        $donneesFormulaire->jsonImport(json_encode(["foo" => "bar"]));
    }

    /**
     * @throws Exception
     */
    public function testImportFileNoFile()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get("bar", "baz");
        $donneesFormulaire->jsonImport(json_encode(["metadata" => ["fichier" => [0 => "toto.txt"]]]));
        $this->assertEmpty($donneesFormulaire->getFileContent("fichier"));
        $this->assertEquals("toto.txt", $donneesFormulaire->getFileName("fichier"));
    }

    /**
     * @throws Exception
     */
    public function testGetFieldDataList()
    {
        $field_list = $this->getDonneesFormulaire()->getFieldDataList("editeur", 0);
        /** @var FieldData $field */
        $field = $field_list[0];
        $this->assertEquals("Mot de passe", $field->getField()->getLibelle());
    }

    /**
     * @throws Exception
     */
    public function testGetFieldDataListEmptyOnglet()
    {
        $field_list = $this->getDonneesFormulaire()->getFieldDataList("editeur", 2);
        $this->assertEmpty($field_list);
    }

    /**
     * @throws Exception
     */
    public function testGetFileNameWithoutExtension()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get("bar", "baz");
        $donneesFormulaire->jsonImport(json_encode(["metadata" => ["fichier" => [0 => "toto.txt"]]]));
        $this->assertEquals("toto", $donneesFormulaire->getFileNameWithoutExtension("fichier"));
    }

    /**
     * @throws Exception
     */
    public function testGetWithDefault()
    {
        $this->assertEquals("Ceci est un autre texte de défaut", $this->getDonneesFormulaire()->getWithDefault('test_default_onglet_2'));
    }

    /**
     * @throws Exception
     */
    public function testGetWithDefaultWithout()
    {
        $this->getDonneesFormulaire()->setData('test_default_onglet_2', "foo");
        $this->assertEquals("foo", $this->getDonneesFormulaire()->getWithDefault('test_default_onglet_2'));
    }

    /**
     * @throws Exception
     */
    public function testGetWithDefaultEmpty()
    {
        $this->getDonneesFormulaire()->setData('test_default_onglet_2', "foo");
        $this->getDonneesFormulaire()->setData('test_default_onglet_2', "");
        $this->assertEquals("Ceci est un autre texte de défaut", $this->getDonneesFormulaire()->getWithDefault('test_default_onglet_2'));
    }

    public function testEmptyForms()
    {
        $documentType = new DocumentType("test", []);
        $donneesFormulaire = new DonneesFormulaire("/tmp/toto.yml", $documentType);
        $donneesFormulaire->setDocumentIndexor(new DocumentIndexor(new DocumentIndexSQL($this->getSQLQuery()), '1'));
        $donneesFormulaire->saveTab(new Recuperateur(), new FileUploader(), 0);
        $this->assertTrue(true);
    }

    /**
     * @dataProvider copyFileProvider
     * @throws Exception
     */
    public function testCopyFile($filename)
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $donneesFormulaire->addFileFromData("fichier", $filename, "bar", 0);
        $this->assertEquals([$filename], $donneesFormulaire->get("fichier"));

        $this->assertEquals(
            "$tmp_folder/$filename",
            $donneesFormulaire->copyFile('fichier', $tmp_folder)
        );
        $this->assertFileExists("$tmp_folder/$filename");
        $tmpFolder->delete($tmp_folder);
    }

    public function copyFileProvider(): array
    {
        return [
            ['foo.txt'],
            ['école.txt']
        ];
    }

    /**
     * @dataProvider copyAllFilesProvider
     * @throws Exception
     */
    public function testCopyAllFilesWrongFieldName(array $filenames): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $donneesFormulaire = $this->getDonneesFormulaire();
        foreach ($filenames as $i => $iValue) {
            $donneesFormulaire->addFileFromData('fichier', $iValue, 'bar', $i);
        }
        static::assertEquals($filenames, $donneesFormulaire->get('fichier'));
        $donneesFormulaire->copyAllFiles('wrongFieldName', $tmp_folder);
        foreach ($filenames as $i => $filename) {
            static::assertFileDoesNotExist("$tmp_folder/$filename");
        }
        $tmpFolder->delete($tmp_folder);
    }

    /**
     * @dataProvider copyAllFilesProvider
     * @throws Exception
     */
    public function testCopyAllFiles(array $filenames): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $donneesFormulaire = $this->getDonneesFormulaire();
        foreach ($filenames as $i => $iValue) {
            $donneesFormulaire->addFileFromData('fichier', $iValue, 'bar', $i);
        }
        static::assertEquals($filenames, $donneesFormulaire->get('fichier'));
        foreach ($donneesFormulaire->copyAllFiles('fichier', $tmp_folder) as $i => $copiedFileName) {
            static::assertEquals(
                "$tmp_folder/$filenames[$i]",
                $copiedFileName
            );
            static::assertFileExists("$tmp_folder/$filenames[$i]");
        }
        $tmpFolder->delete($tmp_folder);
    }

    public function copyAllFilesProvider(): array
    {
        return [
            [['foo.txt', 'école.txt', 'toto.txt']]
        ];
    }

    /**
     * @dataProvider copyAllFilesProviderWithNewFileName
     * @throws Exception
     */
    public function testCopyAllFilesNewFileName(array $filenames, string $newFileName): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $donneesFormulaire = $this->getDonneesFormulaire();
        foreach ($filenames as $i => $iValue) {
            $donneesFormulaire->addFileFromData('fichier', $iValue, 'bar', $i);
        }
        static::assertEquals($filenames, $donneesFormulaire->get('fichier'));
        foreach ($donneesFormulaire->copyAllFiles('fichier', $tmp_folder, $newFileName) as $i => $copiedFileName) {
            $extension = $donneesFormulaire->extensionByMimeType($tmp_folder, $filenames[$i]);
            static::assertEquals(
                "$tmp_folder/$newFileName-$i$extension",
                $copiedFileName
            );
            static::assertFileExists("$tmp_folder/$newFileName-$i$extension");
        }
        $tmpFolder->delete($tmp_folder);
    }

    public function copyAllFilesProviderWithNewFileName(): array
    {
        return [
            [['foo.txt', 'école.txt', 'toto.txt'],'newName']
        ];
    }


    /**
     * @throws Exception
     */
    public function testCopyFileFailed()
    {
        $donneesFormulaire = $this->getDonneesFormulaire();
        $this->assertFalse($donneesFormulaire->copyFile('fichier', "/tmp"));
    }

    /**
     * @throws Exception
     */
    public function testCopyFileNewName()
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $donneesFormulaire->addFileFromData("fichier", "foo.txt", "bar", 0);

        $this->assertEquals(
            "$tmp_folder/bar.txt",
            $donneesFormulaire->copyFile('fichier', $tmp_folder, 0, "bar")
        );
        $this->assertFileExists("$tmp_folder/bar.txt");
        $tmpFolder->delete($tmp_folder);
    }

    /**
     * @throws Exception
     */
    public function testIndexation()
    {
        $id_d = $this->createDocument('helios-generique')['id_d'];

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donnesFormulaire->setData('id_bordereau', '42');
        $donnesFormulaire->setData('id_coll', 'foo');
        $documentIndex = $this->getObjectInstancier()->getInstance(DocumentIndexSQL::class);
        $this->assertEquals('42', $documentIndex->get($id_d, 'id_bordereau'));
        $this->assertEquals('foo', $documentIndex->get($id_d, 'id_coll'));

        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        $sqlQuery->setLogger($this->getLogger());

        $donnesFormulaire->setData('id_bordereau', 'bar');

        $this->assertCount(6, $this->getLogRecords());

        $this->assertEquals('bar', $documentIndex->get($id_d, 'id_bordereau'));
        $this->assertEquals('foo', $documentIndex->get($id_d, 'id_coll'));
    }

    /**
     * @throws Exception
     */
    public function testGetContentTypeXml()
    {
        $id_d = $this->createDocument('helios-generique')['id_d'];

        $file_path = __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml";

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donnesFormulaire->addFileFromCopy('fichier_pes', basename($file_path), $file_path);

        $this->assertEquals('application/xml', $donnesFormulaire->getContentType('fichier_pes'));
    }

    /**
     * @throws Exception
     */
    public function testMaxFileSize()
    {
        $donneesFormulaire = $this->getCustomDonneesFormulaire(__DIR__ . '/../fixtures/file-limit.yml');

        $ten_octets_string = '0123456789';
        $eleven_octets_string = '11 octets !';
        $donneesFormulaire->addFileFromData('file_10_octets', 'file1', $ten_octets_string);
        $this->assertTrue($donneesFormulaire->isValidable());

        $donneesFormulaire->addFileFromData('file_10_octets', 'file1', $eleven_octets_string);
        $this->assertFalse($donneesFormulaire->isValidable());
        $this->assertSame(
            'Le fichier «file1» (Fichier de 10 octets maximum) dépasse le poids limite autorisé :0.00 Mo (10 octets), 0.00 Mo (11 octets) trouvés',
            $donneesFormulaire->getLastError()
        );
    }

    /**
     * @throws Exception
     */
    public function testMaxMultipleFileSize()
    {
        $donneesFormulaire = $this->getCustomDonneesFormulaire(__DIR__ . '/../fixtures/file-limit.yml');
        $ten_octets_string = '0123456789';
        $eleven_octets_string = '11 octets !';
        for ($i = 0; $i < 5; ++$i) {
            $donneesFormulaire->addFileFromData('multiple_file_50_octets', 'file_' . $i, $ten_octets_string, $i);
        }
        $this->assertTrue($donneesFormulaire->isValidable());

        $donneesFormulaire->addFileFromData('multiple_file_50_octets', 'file_4', $eleven_octets_string, 4);
        $this->assertFalse($donneesFormulaire->isValidable());
        $errorMessage = 'L\'ensemble des fichiers du champ multiple «Fichiers multiples dont la somme du poids est ' .
            'limité à 50 octets» dépasse le poids limite autorisé : 0.00 Mo (50 octets), 0.00 Mo (51 octets) trouvés';
        $this->assertSame(
            $errorMessage,
            $donneesFormulaire->getLastError()
        );
    }

    /**
     * @throws Exception
     */
    public function testMaxFileSizeOnMultipleField()
    {
        $donneesFormulaire = $this->getCustomDonneesFormulaire(__DIR__ . '/../fixtures/file-limit.yml');
        $ten_octets_string = '0123456789';
        $eleven_octets_string = '11 octets !';
        for ($i = 0; $i < 2; ++$i) {
            $donneesFormulaire->addFileFromData('multiple_file_10_octets_per_file', 'file_' . $i, $ten_octets_string, $i);
        }
        $this->assertTrue($donneesFormulaire->isValidable());

        $donneesFormulaire->addFileFromData('multiple_file_10_octets_per_file', 'file_3', $eleven_octets_string, 2);
        $this->assertFalse($donneesFormulaire->isValidable());
        $this->assertSame(
            'Le fichier «file_3» (multiple_file_10_octets_per_file) dépasse le poids limite autorisé :0.00 Mo (10 octets), 0.00 Mo (11 octets) trouvés',
            $donneesFormulaire->getLastError()
        );
    }

    /**
     * @throws Exception
     */
    public function testThresholdSize()
    {
        $donneesFormulaire = $this->getCustomDonneesFormulaire(__DIR__ . '/../fixtures/file-limit.yml');
        $ten_octets_string = '0123456789';
        for ($i = 0; $i < 10; ++$i) {
            $donneesFormulaire->addFileFromData('multiple_file_10_octets_per_file', 'file_' . $i, $ten_octets_string, $i);
        }
        $this->assertTrue($donneesFormulaire->isValidable());

        $donneesFormulaire->addFileFromData('file_10_octets', 'file', $ten_octets_string);
        $this->assertFalse($donneesFormulaire->isValidable());
        $this->assertSame(
            'L\'ensemble des fichiers dépasse le poids limite autorisé : 0.00 Mo (100 octets), 0.00 Mo (110 octets) trouvés',
            $donneesFormulaire->getLastError()
        );
    }

    /**
     * @throws Exception
     */
    public function testThresholdSizeWithFields()
    {
        $donneesFormulaire = $this->getCustomDonneesFormulaire(__DIR__ . '/../fixtures/file-limit.yml');
        $ten_octets_string = '0123456789';
        for ($i = 0; $i < 20; ++$i) {
            $donneesFormulaire->addFileFromData('multiple_file', 'file_' . $i, $ten_octets_string, $i);
        }
        $this->assertTrue($donneesFormulaire->isValidable());

        for ($i = 0; $i < 10; ++$i) {
            $donneesFormulaire->addFileFromData('multiple_file_10_octets_per_file', 'file_' . $i, $ten_octets_string, $i);
        }
        $donneesFormulaire->addFileFromData('file_10_octets', 'file', $ten_octets_string);
        $this->assertFalse($donneesFormulaire->isValidable());
        $this->assertSame(
            'L\'ensemble des fichiers dépasse le poids limite autorisé : 0.00 Mo (100 octets), 0.00 Mo (110 octets) trouvés',
            $donneesFormulaire->getLastError()
        );
    }

    /**
     * @throws DonneesFormulaireException
     */
    public function testGetFileSizeException()
    {
        $this->expectException(DonneesFormulaireException::class);
        $this->expectExceptionMessage("Le fichier 50 du champ «multiple_file» (vfs://test/workspace//YZZT.yml_multiple_file_50) n'existe pas.");
        $donneesFormulaire = $this->getCustomDonneesFormulaire(__DIR__ . '/../fixtures/file-limit.yml');

        $donneesFormulaire->getFileSize('multiple_file', 50);
    }

    /**
     * @throws Exception
     */
    public function testWhenAddMultipleFileToSingleField()
    {
        $donnesFormulaire = $this->getCustomDonneesFormulaire(
            __DIR__ . "/fixtures/definition-for-multiple-field.yml"
        );
        $this->expectException(DonneesFormulaireException::class);
        $this->expectExceptionMessage("Le champ mon_fichier n'est pas multiple");
        $donnesFormulaire->addFileFromData(
            'mon_fichier',
            'fichier.txt',
            'foo',
            1
        );
    }

    /**
     * @throws Exception
     */
    public function testWhenAddSimpleFileToSingleField()
    {
        $donnesFormulaire = $this->getCustomDonneesFormulaire(
            __DIR__ . "/fixtures/definition-for-multiple-field.yml"
        );

        $donnesFormulaire->addFileFromData(
            'mon_fichier',
            'fichier.txt',
            'foo',
            0
        );
        $this->assertEquals('foo', $donnesFormulaire->getFileContent('mon_fichier'));
    }


    public function contentTypeProvider(): array
    {
        return [
            'nominal' => [
                true,
                [
                    'fichier_text' => [['foo.txt','bar']]
                ]
            ],
            'single-file-invalid' => [
                false,
                [
                    'fichier_pdf' => [['foo.txt','bar']]
                ]
            ],
            'single-file-mutliple-content-type' => [
                true,
                [
                    'fichier_pdf_or_txt' => [["foo.txt", "bar"]]
                ]
            ],
            'single-file-mutliple-content-type-invalid' => [
                false,
                [
                    'fichier_pdf_or_txt' => [
                        [
                            "foo.xml",
                            file_get_contents(__DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml")
                        ]
                    ]
                ]
            ],
            'multiple-file' => [
                true,
                [
                    'fichier_multiple_text' => [
                        ["a.txt", "foo"],
                        ["b.txt", "bar"]
                    ]
                ]
            ],
            'multiple-file-invalid' => [
                false,
                [
                    'fichier_multiple_text' => [
                        ["a.txt", "foo"],
                        [
                            "foo.xml",
                            file_get_contents(__DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1496987735_826268894.xml")
                        ]
                    ]
                ]
            ],
        ];
    }

    /**
     * @param bool $expected_validation
     * @param array $files_list
     * @throws Exception
     * @dataProvider contentTypeProvider
     */
    public function testContentType(bool $expected_validation, array $files_list)
    {
        $donneesFormulaire = $this->getCustomDonneesFormulaire(__DIR__ . '/fixtures/definition-with-content-type.yml');
        foreach ($files_list as $field_name => $field_info) {
            foreach ($field_info as $num_file => $file_info) {
                $donneesFormulaire->addFileFromData($field_name, $file_info[0], $file_info[1], $num_file);
            }
        }

        $this->assertEquals($expected_validation, $donneesFormulaire->isValidable());
    }

    /**
     * @throws Exception
     */
    public function testContentTypeOnBadFieldType()
    {
        $donneesFormulaire = $this->getCustomDonneesFormulaire(
            __DIR__ . '/fixtures/definition-with-content-type.yml'
        );
        $donneesFormulaire->setData("pas_un_fichier", "toto");
        $this->assertTrue($donneesFormulaire->isValidable());
    }
}
