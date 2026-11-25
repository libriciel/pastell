<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use DocumentSQL;
use Pastell\Service\Document\DocumentIdService;
use Pastell\Utilities\Identifier\IdentifierGeneratorInterface;
use PastellTestCase;
use UnrecoverableException;

final class DocumentIdServiceTest extends PastellTestCase
{
    private const EXISTING_ID_D = 'existing-id-d';
    private const NEW_ID_D = 'new-id-d';

    private DocumentSQL $documentSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $this->documentSQL->save(self::EXISTING_ID_D, 'document-type-test');
    }

    private function getDocumentIdService(string ...$generated_id_list): DocumentIdService
    {
        $identifierGenerator = $this->createMock(IdentifierGeneratorInterface::class);
        $identifierGenerator->method('generate')->willReturnOnConsecutiveCalls(...array_values($generated_id_list));
        return new DocumentIdService($this->documentSQL, $identifierGenerator);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGenerate(): void
    {
        self::assertSame(self::NEW_ID_D, $this->getDocumentIdService(self::NEW_ID_D)->generate());
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGenerateSkipsExistingId(): void
    {
        $documentIdService = $this->getDocumentIdService(self::EXISTING_ID_D, self::NEW_ID_D);

        self::assertSame(self::NEW_ID_D, $documentIdService->generate());
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGenerateTooManyCollisions(): void
    {
        $generated_id_list = array_fill(0, DocumentIdService::MAX_ATTEMPTS, self::EXISTING_ID_D);
        $documentIdService = $this->getDocumentIdService(...$generated_id_list);

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Impossible de trouver un numéro de transaction');
        $documentIdService->generate();
    }
}
