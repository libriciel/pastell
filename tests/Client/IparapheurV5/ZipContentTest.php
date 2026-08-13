<?php

namespace Pastell\Tests\Client\IparapheurV5;

use Pastell\Client\IparapheurV5\ZipContent;
use TmpFolder;
use UnrecoverableException;

class ZipContentTest extends \PastellTestCase
{
    public function testExtractCorruptedZip(): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $corruptedZipPath = $tmp_folder . '/corrupted.zip';
        \file_put_contents($corruptedZipPath, "ceci n'est pas une archive zip valide");

        $zipContent = new ZipContent();

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Impossible d'ouvrir le fichier zip");
        $zipContent->extract($corruptedZipPath, $tmp_folder);
    }

    public function testExtract()
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $zipContent = new ZipContent();
        $zipContentModel = $zipContent->extract(__DIR__ . "/fixtures/response.zip", $tmp_folder);
        $all_dir = scandir($tmp_folder);
        self::assertContains('Documents principaux', $all_dir);
        self::assertContains('TEST 1_bordereau.pdf', $all_dir);
        self::assertContains('i_Parapheur_internal_premis.xml', $all_dir);
        self::assertEquals('TEST 1', $zipContentModel->name);
        self::assertEquals('60124458-8687-11ed-b28f-0242c0a8b013', $zipContentModel->id);
        self::assertFileEquals(
            __DIR__ . "/fixtures/i_Parapheur_internal_premis.xml",
            $tmp_folder . "/" . $zipContentModel->premisFile
        );
        self::assertFileExists(
            $tmp_folder . "/" . $zipContentModel->bordereau
        );
        self::assertFileExists(
            $tmp_folder . "/" . $zipContentModel->documentPrincipaux[0]
        );
    }
}
