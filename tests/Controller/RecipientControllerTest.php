<?php

declare(strict_types=1);

namespace Pastell\Tests\Controller;

use DocumentEmail;
use DocumentEmailReponseSQL;
use DonneesFormulaireFactory;
use Mailsec\Kernel;
use MailSecTestHelper;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RecipientControllerTest extends WebTestCase
{
    private MailSecTestHelper $mailsec;
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
        self::$class = null;
        $this->client = self::createClient();
        $this->mailsec = new MailSecTestHelper('MailSecTestHelper');
    }

    public function testIndex(): void
    {
        $mailsecInfo = $this->mailsec->createMailSec(
            MailSecTestHelper::FLUX_MAILSEC,
            MailSecTestHelper::ACTION_MAILSEC_ENVOI_MAIL
        );
        $this->client->request('GET', '/mail/' . $mailsecInfo['key']);

        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Bourg-en-Bresse - Mail sécurisé - Pastell');
        self::assertStringContainsString('message de test', $this->client->getResponse()->getContent());
    }

    public function testReply(): void
    {
        $mailsecInfo = $this->mailsec->createMailSec(
            MailSecTestHelper::FLUX_MAILSEC_BIDIR,
            MailSecTestHelper::ACTION_MAILSEC_BIDIR_ENVOI_MAIL
        );

        $uri = '/mail/' . $mailsecInfo['key'] . '/reply';
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Bourg-en-Bresse - Réponse à un mail sécurisé - Pastell');
        self::assertStringContainsString('message de test', $this->client->getResponse()->getContent());

        $this->client->submitForm('Enregistrer', [
            'reponse' => 'The reply',
        ]);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Bourg-en-Bresse - Mail sécurisé - Validation de la réponse - Pastell');
        self::assertStringContainsString('The reply', $this->client->getResponse()->getContent());
    }

    public function testGetFile(): void
    {
        $mailsecInfo = $this->mailsec->createMailSec(
            MailSecTestHelper::FLUX_MAILSEC,
            MailSecTestHelper::ACTION_MAILSEC_ENVOI_MAIL
        );
        $donneesFormulaire = $this->mailsec->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class)->get(
            $mailsecInfo['id_d']
        );
        $donneesFormulaire->addFileFromData('document_attache', 'foo.txt', 'bar');
        $this->client->request('GET', '/mail/' . $mailsecInfo['key'] . '/downloadFile?field=document_attache');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=foo.txt');
    }

    public function testDeleteFile(): void
    {
        $mailsecInfo = $this->mailsec->createMailSec(
            MailSecTestHelper::FLUX_MAILSEC_BIDIR,
            MailSecTestHelper::ACTION_MAILSEC_BIDIR_ENVOI_MAIL
        );
        $key = $mailsecInfo['key'];

        $this->client->request('GET', '/mail/' . $key . '/reply');
        $this->client->submitForm('Enregistrer', [
            'reponse' => 'The reply',
        ]);
        $documentEmail = $this->mailsec->getObjectInstancier()->getInstance(DocumentEmail::class);
        $info = $documentEmail->getInfoFromKey($key);
        $id_de = $info['id_de'];

        $documentEmailReponseSQL = $this->mailsec->getObjectInstancier()->getInstance(DocumentEmailReponseSQL::class);
        $reponseId = $documentEmailReponseSQL->getInfo($id_de)['id_d_reponse'];

        $donneesFormulaire = $this->mailsec->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class)->get(
            $reponseId
        );
        $donneesFormulaire->addFileFromData('document_attache', 'foo.txt', 'bar');

        self::assertNotFalse($donneesFormulaire->get('document_attache'));
        $this->client->request('GET', '/mail/' . $key . '/deleteFile?field=document_attache&fichier_reponse=true');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $donneesFormulaire = $this->mailsec->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class)->get(
            $reponseId
        );
        self::assertFalse($donneesFormulaire->get('document_attache'));
    }

    public function testPassword(): void
    {
        $mailsecInfo = $this->mailsec->createMailSec(
            MailSecTestHelper::FLUX_MAILSEC,
            MailSecTestHelper::ACTION_MAILSEC_ENVOI_MAIL
        );
        $this->client->request('GET', '/mail/' . $mailsecInfo['key'] . '/password');

        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Mot de passe Mail sécurisé - Pastell');
        self::assertStringContainsString('Veuillez saisir le mot de passe', $this->client->getResponse()->getContent());
    }

    public function testPasswordPageDoesNotMarkMailAsRead(): void
    {
        $mailsecInfo = $this->mailsec->createMailSec(
            MailSecTestHelper::FLUX_MAILSEC,
            MailSecTestHelper::ACTION_MAILSEC_ENVOI_MAIL
        );
        $this->mailsec->getObjectInstancier()
            ->getInstance(DonneesFormulaireFactory::class)
            ->get($mailsecInfo['id_d'])
            ->setTabData(['password' => 'secret', 'password2' => 'secret']);

        $this->client->request('GET', '/mail/' . $mailsecInfo['key'] . '/password');
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/mail/' . $mailsecInfo['key'] . '/password', ['password' => 'wrong']);
        self::assertResponseIsSuccessful();

        $documentEmail = $this->mailsec->getObjectInstancier()->getInstance(DocumentEmail::class);
        $info = $documentEmail->getInfoFromKey($mailsecInfo['key']);
        self::assertSame(0, $info['lu']);

        $this->client->request('POST', '/mail/' . $mailsecInfo['key'] . '/password', ['password' => 'secret']);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $info = $documentEmail->getInfoFromKey($mailsecInfo['key']);
        self::assertSame(1, $info['lu']);
    }

    public function testInvalid(): void
    {
        $this->client->request('GET', '/mail/invalid');
        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Mail sécurisé invalide - Pastell');
        self::assertStringContainsString(
            'La clé du message ne correspond à aucun mail sécurisé.',
            $this->client->getResponse()->getContent()
        );
    }

    public function testUnavailable(): void
    {
        $this->client->request('GET', '/mail/unavailable');
        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Mail sécurisé indisponible - Pastell');
        self::assertStringContainsString(
            'Ce mail sécurisé n\'est plus disponible.',
            $this->client->getResponse()->getContent()
        );
    }
}
