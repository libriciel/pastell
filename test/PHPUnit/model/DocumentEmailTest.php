<?php

declare(strict_types=1);

final class DocumentEmailTest extends PastellTestCase
{
    use MailsecTestTrait;

    private DocumentEmail $documentEmail;

    protected function setUp(): void
    {
        $this->documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        parent::setUp();
    }

    public function testGetNumberOfMailRead(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        $key1 = $this->documentEmail->add($id_d, '1@example.org', 'to');
        $key2 = $this->documentEmail->add($id_d, '2@example.org', 'to');

        $this->assertSame(0, $this->documentEmail->getNumberOfMailRead($id_d));

        $this->documentEmail->consulter($key1);
        $this->assertSame(1, $this->documentEmail->getNumberOfMailRead($id_d));
        $this->documentEmail->consulter($key2);
        $this->assertSame(2, $this->documentEmail->getNumberOfMailRead($id_d));
    }

    public function fluxActionProvider(): iterable
    {
        yield [MailSecTestHelper::FLUX_MAILSEC_BIDIR, MailSecTestHelper::ACTION_MAILSEC_BIDIR_ENVOI_MAIL];
        yield [MailSecTestHelper::FLUX_MAILSEC, MailSecTestHelper::ACTION_MAILSEC_ENVOI_MAIL];
    }

    /**
     * @dataProvider fluxActionProvider
     */
    public function testSupprimerMailSecActionWhenReception(string $flux_name, string $action_envoi)
    {
        $mail_sec_info = $this->createMailSec($flux_name, $action_envoi);
        $id_d = $mail_sec_info['id_d'];

        $this->assertLastMessage('Le document a été envoyé au(x) destinataire(s)');
        $this->assertActionPossible(['renvoi', 'non-recu'], $id_d);

        $documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        $document_email_info = $documentEmail->getInfo($id_d);
        $documentEmail->consulter($document_email_info[0]['key']);

        $this->assertLastDocumentAction('reception', $id_d);
        $this->assertActionPossible(['supression', 'renvoi'], $id_d);
    }

    /**
     * @dataProvider fluxActionProvider
     */
    public function testSupprimerMailSecActionWhenNonRecu(string $flux_name, string $action_envoi)
    {
        $mail_sec_info = $this->createMailSec($flux_name, $action_envoi);
        $id_d = $mail_sec_info['id_d'];

        $this->assertLastMessage('Le document a été envoyé au(x) destinataire(s)');
        $this->assertActionPossible(['renvoi', 'non-recu'], $id_d);

        $this->assertTrue(
            $this->triggerActionOnDocument($id_d, 'non-recu')
        );
        $this->assertLastMessage('Mail défini comme non-reçu.');

        $documentEmail = $this->getObjectInstancier()->getInstance(DocumentEmail::class);
        $this->assertEmpty($documentEmail->getInfo('id_d'));

        $this->assertLastDocumentAction('non-recu', $id_d);
        $this->assertActionPossible(['supression'], $id_d);
    }
}
