<?php

use OTPHP\TOTP;
use Pastell\Service\LoginAttemptLimit;
use Pastell\Service\MagicLink\MagicLinkService;
use Pastell\Service\Utilisateur\MfaService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimit;

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

        return ['link' => $link, 'token' => $matches[1], 'code' => (string)$link['code']];
    }

    private function differentCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
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
    public function testMagicLinkShowsCodePage(): void
    {
        ['token' => $token] = $this->createMagicLinkAndGetToken();

        $this->setGetInfo(['token' => $token]);
        $this->expectOutputRegex('#saisir le code à 6 chiffres#');

        $this->connexionControler->magicLinkAction();
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkLoginWithValidCode(): void
    {
        ['link' => $link, 'token' => $token, 'code' => $code] = $this->createMagicLinkAndGetToken();

        $this->setPostInfo(['token' => $token, 'code' => $code]);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $this->connexionControler->doMagicLinkCodeAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        static::assertTrue($authentification->isConnected());
        static::assertSame((int)$link['id_u'], (int)$authentification->getId());
        static::assertSame($link['id'], $authentification->getMagicLinkId());
    }

    /**
     * @throws LastMessageException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkLoginClosesExistingSession(): void
    {
        ['link' => $link, 'token' => $token, 'code' => $code] = $this->createMagicLinkAndGetToken();

        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        static::assertSame(1, (int)$authentification->getId());

        $this->setPostInfo(['token' => $token, 'code' => $code]);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $this->connexionControler->doMagicLinkCodeAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        static::assertSame((int)$link['id_u'], (int)$authentification->getId());
        static::assertNotSame(1, (int)$authentification->getId());
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkWrongCodeKeepsAccessActive(): void
    {
        ['token' => $token, 'code' => $code] = $this->createMagicLinkAndGetToken();

        $this->setPostInfo(['token' => $token, 'code' => $this->differentCode($code)]);

        try {
            $this->connexionControler->doMagicLinkCodeAction();
            static::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('Code incorrect', $e->getMessage());
        }

        $magicLinkService = $this->getObjectInstancier()->getInstance(MagicLinkService::class);
        static::assertCount(1, $magicLinkService->getActiveLinks());
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkRevokedAfterThreeWrongCodes(): void
    {
        ['token' => $token, 'code' => $code] = $this->createMagicLinkAndGetToken();

        $this->setPostInfo(['token' => $token, 'code' => $this->differentCode($code)]);
        $magicLinkService = $this->getObjectInstancier()->getInstance(MagicLinkService::class);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->connexionControler->doMagicLinkCodeAction();
            } catch (LastErrorException) {
            }
        }

        try {
            $this->connexionControler->doMagicLinkCodeAction();
            static::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('révoqué', $e->getMessage());
        }

        static::assertSame([], $magicLinkService->getActiveLinks());
        static::assertCount(1, $magicLinkService->getHistory());
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

    private function enableMfaForAdmin(): string
    {
        $mfaService = $this->getObjectInstancier()->getInstance(MfaService::class);
        $secret = $mfaService->generateSecret();
        $mfaService->enroll(1, $secret);
        $mfaService->confirm(1);
        return $secret;
    }

    private function mockLoginAttemptLimit(int $remainingTokens = 5): void
    {
        $loginAttemptLimit = $this->createMock(LoginAttemptLimit::class);
        $loginAttemptLimit->method('getRateLimit')
            ->willReturn(new RateLimit($remainingTokens, new \DateTimeImmutable(), $remainingTokens > 0, 5));
        $this->getObjectInstancier()->setInstance(LoginAttemptLimit::class, $loginAttemptLimit);
    }

    public function testMfaPendingAfterPassword(): void
    {
        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->deconnexion();
        $this->enableMfaForAdmin();
        $this->mockLoginAttemptLimit();
        $this->setPostInfo(['login' => 'admin', 'password' => 'admin', 'request_uri' => '/']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $this->connexionControler->doConnexionAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('/Connexion/mfa', $e->getMessage());
        }

        static::assertFalse($authentification->isConnected());
        static::assertSame(1, $_SESSION['mfa_pending']['id_u']);
    }

    public function testMfaValidCodeConnects(): void
    {
        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->deconnexion();
        $secret = $this->enableMfaForAdmin();
        $this->mockLoginAttemptLimit();
        $_SESSION['mfa_pending'] = ['id_u' => 1, 'login' => 'admin', 'request_uri' => '/'];
        $this->setPostInfo(['code' => TOTP::createFromSecret($secret)->now()]);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $this->connexionControler->doMfaAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        static::assertTrue($authentification->isConnected());
        static::assertSame(1, (int)$authentification->getId());
        static::assertArrayNotHasKey('mfa_pending', $_SESSION);
    }

    public function testMfaInvalidCode(): void
    {
        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->deconnexion();
        $secret = $this->enableMfaForAdmin();
        $this->mockLoginAttemptLimit();
        $_SESSION['mfa_pending'] = ['id_u' => 1, 'login' => 'admin', 'request_uri' => '/'];
        $valid = TOTP::createFromSecret($secret)->now();
        $this->setPostInfo(['code' => $this->differentCode($valid)]);

        try {
            $this->connexionControler->doMfaAction();
            static::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('code saisi est invalide', $e->getMessage());
        }

        static::assertFalse($authentification->isConnected());
        static::assertArrayHasKey('mfa_pending', $_SESSION);
        static::assertNotEmpty(
            $this->getObjectInstancier()->getInstance(Journal::class)
                ->getAll(false, false, false, false, 0, 100, 'Échec double authentification')
        );
    }

    /**
     * @throws LastErrorException
     */
    public function testMfaRecoveryCodeConnects(): void
    {
        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->deconnexion();
        $mfaService = $this->getObjectInstancier()->getInstance(MfaService::class);
        $mfaService->enroll(1, $mfaService->generateSecret());
        $mfaService->confirm(1);
        $recoveryCodes = $mfaService->generateRecoveryCodes(1);
        $this->mockLoginAttemptLimit();
        $_SESSION['mfa_pending'] = ['id_u' => 1, 'login' => 'admin', 'request_uri' => '/'];
        $this->setPostInfo(['code' => $recoveryCodes[0]]);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $this->connexionControler->doMfaAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        static::assertTrue($authentification->isConnected());
        static::assertSame(9, $mfaService->countRemainingRecoveryCodes(1));
    }

    public function testMfaNoPendingRedirects(): void
    {
        unset($_SESSION['mfa_pending']);

        try {
            $this->connexionControler->mfaAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('/Connexion/connexion', $e->getMessage());
        }
    }

    public function testMfaEnrolmentForcedByObligation(): void
    {
        $authentification = $this->getObjectInstancier()->getInstance(Authentification::class);
        $authentification->deconnexion();
        $this->getObjectInstancier()->getInstance(EntiteMfaObligationSQL::class)->enable(0);
        $this->mockLoginAttemptLimit();
        $this->setPostInfo(['login' => 'admin', 'password' => 'admin', 'request_uri' => '/']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $this->connexionControler->doConnexionAction();
            static::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('/Mfa/enrolement', $e->getMessage());
        }

        static::assertTrue($authentification->isConnected());
    }
}
