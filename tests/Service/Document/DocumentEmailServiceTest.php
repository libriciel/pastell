<?php

namespace Pastell\Tests\Service\Document;

use DocumentEmailSQL;
use DocumentEmailReponseSQL;
use DocumentSQL;
use Pastell\Service\Document\DocumentEmailRecipientType;
use Pastell\Service\Document\DocumentEmailService;
use UnrecoverableException;
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

    public function testGetRecipientsWithResponse(): void
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
        $documentEmailSQL = $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class);
        $key = $documentEmailService->addRecipient($id_d, 'johndoe@example.invalid', DocumentEmailRecipientType::TO);
        $documentEmailSQL->setReponse($documentEmailSQL->getByKey($key)['id_de'], json_encode(['nom' => 'Doe']));

        $recipient_list = $documentEmailService->getRecipients($id_d);

        self::assertSame(DocumentEmailRecipientType::TO->label(), $recipient_list[0]['type_destinataire_libelle']);
        self::assertSame('Doe', $recipient_list[0]['nom']);
        self::assertSame(['nom'], $documentEmailService->getResponseColumns($recipient_list));
    }

    public function testLastErrorHidesKey(): void
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
        $documentEmailSQL = $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class);
        $key = $documentEmailService->addRecipient($id_d, 'johndoe@example.invalid', DocumentEmailRecipientType::TO);
        $id_de = $documentEmailSQL->getByKey($key)['id_de'];
        $documentEmailSQL->setError($id_de, "link with $key failed");

        self::assertSame(
            'link with XXXX-LA-CLE-NE-PEUT-ETRE-DIVULGUEE-ICI-XXXX failed',
            $documentEmailService->getLastErrorWithoutKey($documentEmailSQL->getById($id_de))
        );
    }

    private function createResponseDocument(string $key): string
    {
        $id_de = $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class)->getByKey($key)['id_de'];
        $id_d_reponse = $this->createDocument('test')['id_d'];
        $documentEmailReponseSQL = $this->getObjectInstancier()->getInstance(DocumentEmailReponseSQL::class);
        $documentEmailReponseSQL->addDocumentReponseId($id_de, $id_d_reponse);
        $documentEmailReponseSQL->validateReponse($id_de);
        return $id_d_reponse;
    }

    /**
     * @throws UnrecoverableException
     */
    public function testReadResponse(): void
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
        $key = $documentEmailService->addRecipient($id_d, 'johndoe@example.invalid', DocumentEmailRecipientType::TO);
        $id_d_reponse = $this->createResponseDocument($key);

        $document_email_info = $documentEmailService->readResponse(self::ID_E_COL, $id_d, $id_d_reponse, 'admin');

        self::assertSame('johndoe@example.invalid', $document_email_info['email']);
        self::assertTrue(
            (bool) $this->getObjectInstancier()->getInstance(DocumentEmailReponseSQL::class)
                ->getInfoFromIdReponse($id_d_reponse)['is_lu']
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testReadOtherDocumentResponse(): void
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
        $key = $documentEmailService->addRecipient($id_d, 'johndoe@example.invalid', DocumentEmailRecipientType::TO);
        $id_d_reponse = $this->createResponseDocument($key);
        $other_id_d = $this->createDocument('mailsec-bidir')['id_d'];

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Impossible de lire ce document');
        $documentEmailService->readResponse(self::ID_E_COL, $other_id_d, $id_d_reponse, 'admin');
    }

    public function testGetRecipient(): void
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
        $key = $documentEmailService->addRecipient($id_d, 'johndoe@example.invalid', DocumentEmailRecipientType::TO);
        $id_de = (int) $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class)->getByKey($key)['id_de'];

        self::assertSame('johndoe@example.invalid', $documentEmailService->getRecipient($id_de)['email']);
        self::assertNull($documentEmailService->getRecipient(0));
    }

    public function testGetResponses(): void
    {
        $id_d = $this->createDocument('mailsec-bidir')['id_d'];
        $documentEmailService = $this->getObjectInstancier()->getInstance(DocumentEmailService::class);
        $key = $documentEmailService->addRecipient($id_d, 'johndoe@example.invalid', DocumentEmailRecipientType::TO);
        $id_d_reponse = $this->createResponseDocument($key);

        $response_list = $documentEmailService->getResponses($id_d);

        self::assertCount(1, $response_list);
        self::assertSame($id_d_reponse, current($response_list)['id_d_reponse']);
    }
}
