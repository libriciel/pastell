<?php

namespace Pastell\Tests\Service\Document;

use DocumentSQL;
use JobQueueSQL;
use NotificationDigestSQL;
use NotFoundException;
use Pastell\Service\Document\DocumentDeletionService;
use PastellTestCase;

class DocumentDeletionServiceTest extends PastellTestCase
{
    private function getDocumentDeletionService(): DocumentDeletionService
    {
        return $this->getObjectInstancier()->getInstance(DocumentDeletionService::class);
    }

    /**
     * @throws NotFoundException
     */
    public function testDelete()
    {
        $documentDeletionService = $this->getDocumentDeletionService();
        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);

        $id_d = $this->createDocument('test')['id_d'];

        $documentDeletionService->delete($id_d);

        DocumentSQL::clearCache();
        $this->assertFalse($documentSQL->getInfo($id_d));

        $journal_message = $this->getJournal()->getAll()[0]['message'];
        $expected_journal_message = "Le document «  » ($id_d) a été supprimé ";
        $this->assertEquals(
            $expected_journal_message,
            $journal_message
        );
    }

    /**
     * @throws NotFoundException
     */
    public function testDeleteNoJobLeft(): void
    {
        $document = $this->createDocument('test');
        $id_d = $document['id_d'];
        $this->triggerActionOnDocument($id_d, 'action-auto');
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        static::assertTrue($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));
        $this->getDocumentDeletionService()->delete($id_d);
        static::assertFalse($jobQueueSQL->hasDocumentJob(self::ID_E_COL, $id_d));
    }

    /**
     * @throws NotFoundException
     */
    public function testDeleteClearsNotificationDigest(): void
    {
        $id_d = $this->createDocument('test')['id_d'];

        $notificationDigestSQL = $this->getObjectInstancier()->getInstance(NotificationDigestSQL::class);
        $notificationDigestSQL->add('user@example.com', self::ID_E_COL, $id_d, 'action', 'test', 'message');

        $this->assertNotEmpty($notificationDigestSQL->getAll());
        $this->getDocumentDeletionService()->delete($id_d);
        $this->assertEmpty($notificationDigestSQL->getAll());
    }

    /**
     * @throws NotFoundException
     */
    public function testDeleteNoMailLeft(): void
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];

        $documentEmail = $this->getObjectInstancier()->getInstance(\DocumentEmail::class);
        $key = $documentEmail->add($id_d, 'foo@bar.com', 'to');
        $id_de = $documentEmail->getInfoFromKey($key)['id_de'];
        $id_d_reponse = $this->createDocument('test')['id_d'];
        $documentEmailResponse = $this->getObjectInstancier()->getInstance(\DocumentEmailReponseSQL::class);
        $documentEmailResponse->addDocumentReponseId($id_de, $id_d_reponse);
        $documentEmailResponse->validateReponse($id_de);

        self::assertNotEmpty($documentEmailResponse->getInfo($id_de));
        self::assertNotEmpty($this->getObjectInstancier()->getInstance(DocumentSQL::class)->getInfo($id_d_reponse));
        $this->getDocumentDeletionService()->delete($id_d);
        DocumentSQL::clearCache();
        self::assertEmpty($documentEmailResponse->getInfo($id_de));
        self::assertEmpty($this->getObjectInstancier()->getInstance(DocumentSQL::class)->getInfo($id_d_reponse));
    }
}
