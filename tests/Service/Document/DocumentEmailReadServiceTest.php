<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use DocumentEmailSQL;
use Pastell\Service\Document\DocumentEmailReadService;
use Pastell\Service\Document\DocumentEmailRecipientType;
use Pastell\Service\Document\DocumentEmailService;
use PastellTestCase;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class DocumentEmailReadServiceTest extends PastellTestCase
{
    private DocumentEmailReadService $documentEmailReadService;
    private DocumentEmailService $documentEmailService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentEmailReadService = $this->getObjectInstancier()->getInstance(DocumentEmailReadService::class);
        $this->documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testRead(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $key1 = $this->documentEmailService->addRecipient($id_d, 'a@example.org', DocumentEmailRecipientType::TO);
        $key2 = $this->documentEmailService->addRecipient($id_d, 'b@example.org', DocumentEmailRecipientType::TO);

        self::assertSame(1, $this->documentEmailReadService->read($key1)['lu']);
        $this->assertLastDocumentAction('reception-partielle', $id_d);

        $this->documentEmailReadService->read($key2);
        $this->assertLastDocumentAction('reception', $id_d);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testReadUnknownKey(): void
    {
        self::assertNull($this->documentEmailReadService->read('unknown'));
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testReadNotReceived(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $key = $this->documentEmailService->addRecipient($id_d, 'a@example.org', DocumentEmailRecipientType::TO);
        $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class)->setNotReceived($id_d);

        self::assertNull($this->documentEmailReadService->read($key));
    }
}
