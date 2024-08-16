<?php

use Pastell\Service\Connecteur\ConnecteurAssociationService;

class MailSecConnecteurTest extends PastellTestCase
{
    use MailerTransportTestingTrait;

    public const FLUX_ID =  'mailsec';
    private const EMAIL = 'foo@test.com';

    private DonneesFormulaire $connecteurConfig;

    private string $contentHTML;
    private array $embeddedImage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setMailerTransportForTesting();
        $this->contentHTML = '';
        $this->embeddedImage = [];
    }

    /**
     * @return DocumentEmail
     */
    private function getDocumentEmail(): DocumentEmail
    {
        return $this->getObjectInstancier()->getInstance(DocumentEmail::class);
    }

    /**
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    private function getMailSec(): MailSec
    {
        $result = $this->createConnector('mailsec', "Connecteur mailsec de test");
        $id_ce  = $result['id_ce'];
        $this->configureConnector(
            $id_ce,
            ['mailsec_subject' => 'entite: %ENTITE% -- titre : %TITRE%']
        );
        $this->connecteurConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce);
        if ($this->contentHTML) {
            $this->connecteurConfig->addFileFromCopy(
                'content_html',
                'content.html',
                $this->contentHTML,
            );
        }
        foreach ($this->embeddedImage as $filenum => $filename) {
            $this->connecteurConfig->addFileFromCopy(
                'embeded_image',
                $filename,
                __DIR__ . "/fixtures/image-exemple.png",
                $filenum
            );
        }

        $id_d = $this->createDocument('test')['id_d'];
        $this->configureDocument(
            $id_d,
            ['raison_sociale' => 'Libriciel SCOP', 'numero_facture' => 'FOO42', 'toto' => 'mon titre']
        );
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->addFileFromCopy(
            'metadata',
            "metadata.json",
            __DIR__ . "/fixtures/mail-metadata.json"
        );
        /** @var MailSec $mailsec */
        $mailsec = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $mailsec->setDocDonneesFormulaire($donneesFormulaire);
        return $mailsec;
    }

    /**
     * @throws DonneesFormulaireException
     */
    private function addContentHTML(string $filepath): void
    {
        $this->contentHTML = $filepath;
    }

    /**
     * @param string $filename
     * @param int $filenum
     * @throws DonneesFormulaireException
     */
    private function addEmbededImage(string $filename = 'image.png', int $filenum = 0): void
    {
        $this->embeddedImage[$filenum] = $filename;
    }

    /**
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function testTest()
    {
        $this->getMailSec()->test();
        $this->assertMessageContainsString('Subject: entite: Bourg-en-Bresse -- titre : mon titre');
    }

    /**
     * @throws Exception
     */
    public function testSendOneMail()
    {
        $documentId = '1';
        $key = $this->getDocumentEmail()->add($documentId, self::EMAIL, "to");
        $document_email_info = $this->getDocumentEmail()->getInfoFromKey($key);

        $this->getMailSec()->sendOneMail(1, $documentId, $document_email_info[DocumentEmail::ID_DE]);
        $this->assertMailEqualsFile(__DIR__ . "/fixtures/mail-text-output.txt");
    }

    private function assertMailEqualsFile(string $filename): void
    {
        $mailAsString = $this->getMailerTransport()->getSentMessage()->toString();
        $this->assertMailContentEqualsFile($filename, $mailAsString);
    }
    /**
     * @throws Exception
     */
    public function testSendAllMail()
    {
        $documentId = '1';
        $this->getDocumentEmail()->add($documentId, self::EMAIL, "to");
        $this->getMailSec()->sendAllMail(1, $documentId);
        $this->assertMailEqualsFile(__DIR__ . "/fixtures/mail-text-output.txt");
    }

    /**
     * @throws Exception
     */
    public function testSendHTML(): void
    {
        $this->addContentHTML(__DIR__ . '/fixtures/mail-exemple.html');
        $this->addEmbededImage('image1.png');
        $this->addEmbededImage('image2.png', 1);

        $mailsec = $this->getMailSec();

        $documentId = '1';
        $key = $this->getDocumentEmail()->add($documentId, self::EMAIL, 'to');

        $document_email_info = $this->getDocumentEmail()->getInfoFromKey($key);

        $mailsec->sendOneMail(1, $documentId, $document_email_info[DocumentEmail::ID_DE]);

        $this->assertMailEqualsFile(__DIR__ . '/fixtures/mail-html-output.txt');
    }

    /**
     * @throws Exception
     */
    public function testSendHTMLFluxKeyNotFound()
    {
        $this->addContentHTML(__DIR__ . "/fixtures/mail-exemple-key-not-found.html");
        $this->addEmbededImage();

        $mailsec = $this->getMailSec();

        $documentId = '1';
        $key = $this->getDocumentEmail()->add($documentId, self::EMAIL, "to");

        $document_email_info = $this->getDocumentEmail()->getInfoFromKey($key);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("La clé foo de @metadata:facturx:data:foo n'existe pas, vérifier la syntaxe.");
        $mailsec->sendOneMail(1, $documentId, $document_email_info[DocumentEmail::ID_DE]);
    }

    /**
     * @throws Exception
     */
    public function testSendHTMLFluxMetadataFileNotFound()
    {
        $this->addContentHTML(__DIR__ . "/fixtures/mail-exemple-metadata-file-not-found.html");
        $this->addEmbededImage();

        $mailsec = $this->getMailSec();

        $documentId = '1';
        $key = $this->getDocumentEmail()->add($documentId, self::EMAIL, "to");

        $document_email_info = $this->getDocumentEmail()->getInfoFromKey($key);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Erreur de lecture du contenu de metadata_not_found");
        $mailsec->sendOneMail(1, $documentId, $document_email_info[DocumentEmail::ID_DE]);
    }

    /**
     * @throws Exception
     */
    public function testSendHTMLFluxKeyBadType()
    {
        $this->addContentHTML(__DIR__ . "/fixtures/mail-exemple-key-bad-type.html");

        $this->addEmbededImage();

        $mailsec = $this->getMailSec();

        $documentId = '1';
        $key = $this->getDocumentEmail()->add($documentId, "eric.pommateau@adullact-projet.com", "to");

        $document_email_info = $this->getDocumentEmail()->getInfoFromKey($key);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "La valeur de @metadata:facturx:data n'est pas un type simple, vérifier la syntaxe."
        );
        $mailsec->sendOneMail(1, $documentId, $document_email_info['id_de']);
    }

    /**
     * @throws Exception
     */
    public function testSendLinkTest()
    {
        $mailsec = $this->getMailSec();

        $this->connecteurConfig->setData(
            'mailsec_content',
            "Un lien ici : %LINK%. C'était mon lien"
        );

        $documentId = '1';
        $key = $this->getDocumentEmail()->add($documentId, "eric.pommateau@adullact-projet.com", "to");
        $document_email_info = $this->getDocumentEmail()->getInfoFromKey($key);

        $mailsec->sendOneMail(1, $documentId, $document_email_info['id_de']);

        $this->assertMailEqualsFile(__DIR__ . "/fixtures/mail-link.txt");
    }

    /**
     * @throws Exception
     */
    public function testSendAllMailWithMultiplePeople()
    {
        $this->addContentHTML(__DIR__ . "/fixtures/mail-exemple.html");
        $mailsec = $this->getMailSec();

        $documentId = '1';
        $this->getDocumentEmail()->add($documentId, "jdoe@example.org", "to");
        $this->getDocumentEmail()->add($documentId, "john.doe@example.org", "to");

        $mailsec->sendAllMail(1, $documentId);

        $this->assertMailContentEqualsFile(
            __DIR__ . "/fixtures/mail_jdoe.txt",
            $this->getMailerTransport()->getAllSentMessages()[0]->toString(),
        );
        $this->assertMailContentEqualsFile(
            __DIR__ . "/fixtures/mail_john.doe.txt",
            $this->getMailerTransport()->getAllSentMessages()[1]->toString(),
        );
    }

    /**
     * @throws Exception
     */
    public function testReturnPath()
    {
        $id_ce = $this->createConnector('undelivered-mail', "Undelivered mail", 0)['id_ce'];
        $this->configureConnector($id_ce, ['return_path' => 'foo@libriciel.invalid'], 0);
        /** @var ConnecteurAssociationService $connecteurAssociationService */
        $connecteurAssociationService = $this->getObjectInstancier()->getInstance(
            ConnecteurAssociationService::class
        );
        $connecteurAssociationService->addConnecteurAssociation(
            0,
            $id_ce,
            UndeliveredMail::CONNECTOR_TYPE
        );

        $documentId = '1';
        $key = $this->getDocumentEmail()->add($documentId, self::EMAIL, "to");
        $document_email_info = $this->getDocumentEmail()->getInfoFromKey($key);

        $this->getMailSec()->sendOneMail(1, $documentId, $document_email_info[DocumentEmail::ID_DE]);
        $this->assertMailEqualsFile(__DIR__ . "/fixtures/mail-text-output-return-path.txt");
    }

    private function assertMailContentEqualsFile(string $filename, string $mailAsString): void
    {
        $mailAsString = preg_replace(
            "#X-PASTELL-DOCUMENT: .*#",
            "X-PASTELL-DOCUMENT: NOTTESTABLE\r",
            $mailAsString
        );
        $mailAsString = preg_replace(
            "#Date: .*#",
            "DATE: NOTTESTABLE\r",
            $mailAsString
        );
        $mailAsString = preg_replace(
            "#Message-ID: .*#",
            "Message-ID: NOTTESTABLE\r",
            $mailAsString
        );
        $mailAsString = preg_replace(
            '#/mail/(.*)#',
            '/mail/NOTTESTABLE',
            $mailAsString
        );
        $mailAsString = preg_replace(
            "#boundary=.*#",
            "boundary=NOTTESTABLE\r",
            $mailAsString
        );
        $mailAsString = preg_replace(
            "#--[^ \n]+#",
            "--NOTTESTABLE\r",
            $mailAsString
        );
        $mailAsString = preg_replace(
            '#cid:[0-9a-f]+#',
            'cid:NOTTESTABLE',
            $mailAsString
        );
        $mailAsString = preg_replace(
            '/cid:(\S|\s)*@/U',
            'cid:NOTTESTABLE@',
            $mailAsString
        );

        $mailAsString = preg_replace(
            "#Content-ID: <[0-9a-f]*#",
            "Content-ID: <NOTTESTABLE",
            $mailAsString
        );

        $mailAsString = preg_replace(
            '/name="(.*)@symfony"/',
            'name="NOT_TESTABLE@symfony"',
            $mailAsString
        );

//        \file_put_contents($filename, $mailAsString);
        self::assertStringEqualsFile(
            $filename,
            $mailAsString
        );
    }

    public function testTesterEnvoiReplyTo(): void
    {
        $result = $this->createConnector('mailsec', "Connecteur mailsec de test");
        $id_ce  = $result['id_ce'];
        $this->configureConnector(
            $id_ce,
            ['mailsec_reply_to' => 'test@libriciel.invalid']
        );
        $actionResult = $this->triggerActionOnConnector($id_ce, 'test');
        $this->assertTrue($actionResult);
        $this->assertLastMessage('Un email a été envoyé à test@libriciel.invalid');
    }
}
