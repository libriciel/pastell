<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use DocumentIndexSQL;
use NotFoundException;
use Pastell\Service\Document\DocumentReindexService;
use PastellTestCase;
use UnrecoverableException;

final class DocumentReindexServiceTest extends PastellTestCase
{
    private DocumentReindexService $documentReindexService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentReindexService = $this->getObjectInstancier()->getInstance(DocumentReindexService::class);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testUnknownType(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le type de dossier unknown n'existe pas sur cette plateforme.");
        $this->documentReindexService->getDocumentsToReindex('unknown', ['nom']);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testUnknownField(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le champs unknown n'existe pas pour le type de dossier test");
        $this->documentReindexService->getDocumentsToReindex('test', ['unknown']);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testFieldNotIndexed(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le champs test:test2 n'est pas indexé");
        $this->documentReindexService->getDocumentsToReindex('test', ['test2']);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testOffsetAndLimit(): void
    {
        $this->createDocument('test');
        $id_d = $this->createDocument('test')['id_d'];

        $document_list = $this->documentReindexService->getDocumentsToReindex('test', ['nom'], 1, 1);

        self::assertSame([$id_d], array_column($document_list, 'id_d'));
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function testReindexDocument(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $this->configureDocument($id_d, ['nom' => 'foo']);
        $this->getSQLQuery()->query('DELETE FROM document_index');
        $documentIndexSQL = $this->getObjectInstancier()->getInstance(DocumentIndexSQL::class);
        self::assertFalse($documentIndexSQL->get($id_d, 'nom'));

        $document_list = $this->documentReindexService->getDocumentsToReindex('test', ['nom']);
        self::assertSame([$id_d], array_column($document_list, 'id_d'));
        $this->documentReindexService->reindexDocument($id_d, ['nom']);

        self::assertSame('foo', $documentIndexSQL->get($id_d, 'nom'));
    }
}
