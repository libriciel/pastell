<?php

namespace Pastell\Tests\Service\Document;

use DocumentEmailSQL;
use DocumentEmailReponseSQL;
use DocumentSQL;
use Pastell\Service\Document\DocumentEmailRecipientType;
use Pastell\Service\Document\DocumentEmailService;
use PastellTestCase;

class DocumentEmailServiceTest extends PastellTestCase
{
    public function testGetDocumentEmailFromReponse(): void
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
        $key = $documentEmailService->addRecipient($id_d, 'foo@bar.com', DocumentEmailRecipientType::TO);
        $id_de = $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class)->getByKey($key)['id_de'];
        $id_d_reponse = $this->createDocument('test')['id_d'];
        $documentEmailResponse = $this->getObjectInstancier()->getInstance(DocumentEmailReponseSQL::class);
        $documentEmailResponse->addDocumentReponseId($id_de, $id_d_reponse);
        $documentEmailResponse->validateReponse($id_de);

        $this->assertEquals(
            $documentEmailService->getDocumentEmailFromIdReponse($id_d_reponse),
            $this->getObjectInstancier()->getInstance(DocumentSQL::class)->getInfo($id_d)
        );
    }

    public function testAddRecipientTwice(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);

        $key = $documentEmailService->addRecipient($id_d, 'foo@bar.com', DocumentEmailRecipientType::TO);

        static::assertSame($key, $documentEmailService->addRecipient($id_d, 'foo@bar.com', DocumentEmailRecipientType::CC));
        static::assertCount(
            1,
            $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class)->getAllByDocument($id_d)
        );
    }

    public function testAddErrorOnAllRecipients(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
        $documentEmailSQL = $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class);
        $documentEmailService->addRecipient($id_d, 'a@bar.com', DocumentEmailRecipientType::TO);
        $documentEmailService->addRecipient($id_d, 'b@bar.com', DocumentEmailRecipientType::TO);
        [$id_de1, $id_de2] = $documentEmailSQL->getAllRecipientIds($id_d);

        $documentEmailService->addError($id_de1, 'error');
        $documentEmailService->addError($id_de2, 'error');

        $this->assertLastDocumentAction('erreur', $id_d);
    }
}
