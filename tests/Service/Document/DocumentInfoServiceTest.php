<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use Pastell\Service\Document\DocumentInfoService;
use PastellTestCase;

final class DocumentInfoServiceTest extends PastellTestCase
{
    private DocumentInfoService $documentInfoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentInfoService = $this->getObjectInstancier()->getInstance(DocumentInfoService::class);
    }

    public function testGetInfo(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        self::assertSame($id_d, $this->documentInfoService->getInfo($id_d)['id_d']);
        self::assertSame('test', $this->documentInfoService->getType($id_d));
    }

    public function testGetInfoUnknownDocument(): void
    {
        self::assertNull($this->documentInfoService->getInfo('unknown'));
        self::assertNull($this->documentInfoService->getType('unknown'));
    }
}
