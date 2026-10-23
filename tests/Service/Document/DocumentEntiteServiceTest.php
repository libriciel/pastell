<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use DocumentEntiteSQL;
use DocumentSQL;
use Pastell\Service\Document\DocumentEntiteService;
use PastellTestCase;

final class DocumentEntiteServiceTest extends PastellTestCase
{
    private const ID_D = 'id-d-test';
    private const TYPE = 'document-type-test';

    private DocumentEntiteService $documentEntiteService;
    private DocumentEntiteSQL $documentEntiteSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getObjectInstancier()->getInstance(DocumentSQL::class)->save(self::ID_D, self::TYPE);
        $this->documentEntiteSQL = $this->getObjectInstancier()->getInstance(DocumentEntiteSQL::class);
        $this->documentEntiteService = $this->getObjectInstancier()->getInstance(DocumentEntiteService::class);
    }

    public function testGetEntiteId(): void
    {
        static::assertNull($this->documentEntiteService->getEntiteId(self::ID_D));
        $this->documentEntiteSQL->insert(self::ID_D, self::ID_E_COL, 'editeur');
        static::assertSame(self::ID_E_COL, $this->documentEntiteService->getEntiteId(self::ID_D));
    }

    public function testCountDocuments(): void
    {
        $this->documentEntiteSQL->insert(self::ID_D, self::ID_E_COL, 'editeur');
        static::assertSame(1, $this->documentEntiteService->countDocuments(self::ID_E_COL));
        static::assertSame(1, $this->documentEntiteService->countDocuments(self::ID_E_COL, self::TYPE));
        static::assertSame(0, $this->documentEntiteService->countDocuments(self::ID_E_COL, 'other'));
    }

    public function testGetDocumentsOlderThan(): void
    {
        $this->documentEntiteSQL->insert(self::ID_D, self::ID_E_COL, 'editeur');
        $this->documentEntiteService->updateLastAction(self::ID_E_COL, self::ID_D, 'creation', date('Y-m-d H:i:s'));
        static::assertCount(
            1,
            $this->documentEntiteService->getDocumentsWithLastActionOlderThan(0, self::TYPE, self::ID_E_COL)
        );
        static::assertEmpty(
            $this->documentEntiteService->getDocumentsWithLastActionOlderThan(1, self::TYPE, self::ID_E_COL)
        );
    }
}
