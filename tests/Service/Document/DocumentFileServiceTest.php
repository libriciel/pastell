<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use Exception;
use NotFoundException;
use Pastell\Service\Document\DocumentFileService;
use PastellTestCase;
use UnrecoverableException;

final class DocumentFileServiceTest extends PastellTestCase
{
    private DocumentFileService $documentFileService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentFileService = $this->getObjectInstancier()->getInstance(DocumentFileService::class);
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testGetFileToDownload(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->getDonneesFormulaireFactory()->get($id_d)->addFileFromData('fichier_simple', 'foo.txt', 'bar');

        $documentFile = $this->documentFileService
            ->getFileToDownload(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'fichier_simple', 0);

        self::assertSame('foo.txt', $documentFile->name);
        self::assertSame('bar', file_get_contents($documentFile->path));
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testTruncatesLongFileName(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $file_name = str_repeat('a', 90) . '.txt';
        $this->getDonneesFormulaireFactory()->get($id_d)->addFileFromData('fichier_simple', $file_name, 'bar');

        $documentFile = $this->documentFileService
            ->getFileToDownload(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'fichier_simple', 0);

        self::assertSame(str_repeat('a', 76) . '.txt', $documentFile->name);
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testMissingFile(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Ce fichier n'existe pas");
        $this->documentFileService->getFileToDownload(self::ID_E_COL, self::ID_U_ADMIN, $id_d, 'fichier_simple', 0);
    }
}
