<?php

use Pastell\Service\MagicLink\MagicLinkService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

class ConnexionControlerTest extends ControlerTestCase
{
    use MailerTransportTestingTrait;

    /**
     * @var ConnexionControler
     */
    private $connexionControler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connexionControler = $this->getControlerInstance(ConnexionControler::class);
        $this->setMailerTransportForTesting();
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    private function createMagicLinkAndGetToken(): array
    {
        $magicLinkService = $this->getObjectInstancier()->getInstance(MagicLinkService::class);
        $magicLinkService->create('Intervention', 24, 1, 'Dupont', 'Jean', 'jean.dupont@example.org');
        $link = $magicLinkService->getActiveLinks()[0];

        $email = $this->getMailerTransport()->getSentMessage()->getOriginalMessage();
        static::assertInstanceOf(Email::class, $email);
        static::assertSame(
            1,
            preg_match('#/Connexion/magicLink\?token=([^"\s]+)#', (string)$email->getHtmlBody(), $matches),
        );

        return ['link' => $link, 'token' => $matches[1]];
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function testNotConnected()
    {
        $this->expectException(LastMessageException::class);
        $this->getObjectInstancier()->getInstance(Authentification::class)->deconnexion();
        $this->connexionControler->verifConnected();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function testConnexion()
    {
        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('admin', 1);
        $this->assertTrue($this->connexionControler->verifConnected());
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function testConnexionAction()
    {
        $this->expectOutputRegex("#Veuillez saisir vos identifiants de connexion#");
        $this->connexionControler->connexionAction();
    }

    public function testConnexionAdminAction()
    {
        $this->expectOutputRegex("#Veuillez saisir vos identifiants de connexion#");
        $this->connexionControler->adminAction();
    }

    public function testOublieIdentifiant()
    {
        $this->expectOutputRegex("##");
        $this->connexionControler->oublieIdentifiantAction();
    }

    public function testChangementMdpAction()
    {
        $this->getObjectInstancier()->setInstance('password_min_entropy', 0);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Exit called with code 0");
        $this->expectOutputString("Le lien du mail a expiré. Veuillez recommencer la procédure");
        $this->connexionControler->changementMdpAction();
    }

    public function testChangementNoDroitAction()
    {
        $this->expectOutputRegex("##");
        $this->connexionControler->noDroitAction();
    }

    /**
     * @throws NotFoundException
     */
    public function testCasErrorAction()
    {
        $this->expectOutputRegex("#Erreur lors de la connexion au serveur distant#");
        $this->connexionControler->externalErrorAction();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function testLogoutAction()
    {
        $this->expectException(LastMessageException::class);
        $this->connexionControler->logoutAction();
    }

    public function testSessionLogout()
    {
        $this->assertTrue($this->getObjectInstancier()->getInstance(Authentification::class)->isConnected());
        $this->connexionControler->sessionLogoutAction();
        $this->assertFalse($this->getObjectInstancier()->getInstance(Authentification::class)->isConnected());
    }

    /**
     * @throws Exception
     */
    public function testLogoutRemovesCSRFToken(): void
    {
        $csrfToken = $this->getObjectInstancier()->getInstance(CSRFToken::class);
        $_SESSION = [];
        $csrfToken->setSession($_SESSION);
        // Ensure token is generated
        $csrfToken->getCSRFToken();
        $this->assertArrayHasKey(CSRFToken::TOKEN_NAME, $_SESSION);

        try {
            $this->connexionControler->logoutAction();
        } catch (LastMessageException $e) { // Expect redirection
        }

        $this->assertArrayNotHasKey(CSRFToken::TOKEN_NAME, $_SESSION);
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkLogin(): void
    {
        ['link' => $link, 'token' => $token] = $this->createMagicLinkAndGetToken();

        $this->setGetInfo(['token' => $token]);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $this->connexionControler->magicLinkAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        static::assertTrue($authentification->isConnected());
        static::assertSame((int)$link['id_u'], (int)$authentification->getId());
        static::assertSame((int)$link['id'], $authentification->getMagicLinkId());
    }

    /**
     * @throws LastMessageException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkLoginClosesExistingSession(): void
    {
        ['link' => $link, 'token' => $token] = $this->createMagicLinkAndGetToken();

        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        static::assertSame(1, (int)$authentification->getId());

        $this->setGetInfo(['token' => $token]);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $this->connexionControler->magicLinkAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        static::assertSame((int)$link['id_u'], (int)$authentification->getId());
        static::assertNotSame(1, (int)$authentification->getId());
    }

    /**
     * @throws LastMessageException
     */
    public function testMagicLinkLoginInvalidToken(): void
    {
        $this->setGetInfo(['token' => 'un-token-invalide']);

        try {
            $this->connexionControler->magicLinkAction();
            static::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('invalide, a expiré ou a été révoqué', $e->getMessage());
        }
    }
}
