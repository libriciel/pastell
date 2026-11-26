<?php

declare(strict_types=1);

use OTPHP\TOTP;
use Random\RandomException;
use Symfony\Component\Clock\Clock;
use Pastell\Service\LoginAttemptLimit;
use Pastell\Service\Utilisateur\MfaService;
use Symfony\Component\RateLimiter\RateLimit;

class MfaControlerTest extends ControlerTestCase
{
    private const int ID_U_ERIC = 2;

    private function getMfaControler(): MfaControler
    {
        return $this->getControlerInstance(MfaControler::class);
    }

    private function getMfaService(): MfaService
    {
        return $this->getObjectInstancier()->getInstance(MfaService::class);
    }

    private function assertJournalContains(string $needle): void
    {
        $lines = $this->getJournalConsultationService()->getList(recherche: $needle);
        self::assertNotEmpty($lines, "Aucune entrée de journal contenant : $needle");
    }

    private function startEnrolement(): void
    {
        try {
            $this->doAuthRequired('enable', 'admin');
            self::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            self::assertStringContainsString('/Mfa/enrolement', $e->getMessage());
        }
    }

    private function enrollAuthorised(): void
    {
        $this->getMfaService()->enroll(self::ID_U_ADMIN);
        $this->getObjectInstancier()->getInstance(Authentification::class)->authoriseMfaEnrolment();
    }

    public function testStartEnrolementCreatesSecret(): void
    {
        $this->startEnrolement();
        self::assertNotEmpty($this->getMfaService()->getEnrolment(self::ID_U_ADMIN, 'admin')?->secret);
    }

    public function testStartEnrolementKeepsSecret(): void
    {
        $this->startEnrolement();
        $first = $this->getMfaService()->getEnrolment(self::ID_U_ADMIN, 'admin')?->secret;
        $this->startEnrolement();
        $second = $this->getMfaService()->getEnrolment(self::ID_U_ADMIN, 'admin')?->secret;

        self::assertSame($first, $second);
    }

    public function testStartEnrolementForApi(): void
    {
        $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->setIsAPI(self::ID_U_ADMIN, true);
        $this->startEnrolement();
        self::assertNotEmpty($this->getMfaService()->getEnrolment(self::ID_U_ADMIN, 'admin')?->secret);
    }

    public function testStartEnrolementWrongPassword(): void
    {
        try {
            $this->doAuthRequired('enable', 'wrong');
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('mot de passe est incorrect', $e->getMessage());
        }
        self::assertFalse($this->getMfaService()->isPending(self::ID_U_ADMIN));
    }

    public function testEnrolementRequiresPassword(): void
    {
        $this->getMfaService()->enroll(self::ID_U_ADMIN);
        try {
            $this->getMfaControler()->enrolementAction();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            self::assertStringContainsString('/Mfa/authRequired?action=enable', $e->getMessage());
        }
    }

    public function testEnrolementAuthorisationExpires(): void
    {
        $this->enrollAuthorised();
        $_SESSION['connexion']['mfa_enrolment_authorised_at'] = time() - 3600;
        try {
            $this->getMfaControler()->enrolementAction();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            self::assertStringContainsString('/Mfa/authRequired?action=enable', $e->getMessage());
        }
    }

    public function testActivationRequiresPassword(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        $secret = $mfaService->getEnrolment(self::ID_U_ADMIN, 'admin')->secret;
        $this->setPostInfo(['code' => TOTP::createFromSecret($secret, new Clock())->now()]);

        try {
            $this->doEnrolement();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function testEnrolementDisplaysQr(): void
    {
        $this->enrollAuthorised();

        $this->expectOutputRegex('#Activer la double authentification#');
        $this->getMfaControler()->enrolementAction();
    }

    public function testEnrolementNeedsPending(): void
    {
        try {
            $this->getMfaControler()->enrolementAction();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }
        self::assertFalse($this->getMfaService()->isPending(self::ID_U_ADMIN));
    }

    private function doEnrolement(): void
    {
        $controler = $this->getMfaControler();
        $controler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        $controler->doEnrolementAction();
    }

    public function testActivationShowsRecoveryCodes(): void
    {
        $mfaService = $this->getMfaService();
        $this->enrollAuthorised();
        $secret = $mfaService->getEnrolment(self::ID_U_ADMIN, 'admin')->secret;
        $this->setPostInfo(['code' => TOTP::createFromSecret($secret, new Clock())->now()]);

        $this->expectOutputRegex('#[Cc]odes de récupération#');
        $this->doEnrolement();

        self::assertTrue($mfaService->isEnabled(self::ID_U_ADMIN));
        $this->assertJournalContains('double authentification activée');
        self::assertSame(10, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    public function testActivationAlreadyEnabled(): void
    {
        $secret = $this->enableMfa(self::ID_U_ADMIN);
        $this->getObjectInstancier()->getInstance(Authentification::class)->authoriseMfaEnrolment();
        $this->setPostInfo(['code' => TOTP::createFromSecret($secret, new Clock())->now()]);

        try {
            $this->doEnrolement();
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('déjà activée', $e->getMessage());
        }

        self::assertSame(0, $this->getMfaService()->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    public function testActivationRequiresPost(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        $secret = $mfaService->getEnrolment(self::ID_U_ADMIN, 'admin')->secret;
        $this->setPostInfo(['code' => TOTP::createFromSecret($secret, new Clock())->now()]);

        try {
            $this->getMfaControler()->doEnrolementAction();
            self::fail('Une redirection était attendue');
        } catch (LastErrorException) {
        }

        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));
    }

    private function mockLoginAttemptLimit(int $remainingTokens = 5): void
    {
        $loginAttemptLimit = $this->createMock(LoginAttemptLimit::class);
        $loginAttemptLimit->method('getRateLimit')
            ->willReturn(new RateLimit($remainingTokens, new \DateTimeImmutable(), $remainingTokens > 0, 5));
        $this->getObjectInstancier()->setInstance(LoginAttemptLimit::class, $loginAttemptLimit);
    }

    private function doAuthRequired(string $action, string $password): void
    {
        $this->mockLoginAttemptLimit();
        $controler = $this->getMfaControler();
        $controler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        $this->setPostInfo(['action' => $action, 'password' => $password]);
        $controler->doAuthRequiredAction();
    }

    private function cancelEnrolement(): void
    {
        $controler = $this->getMfaControler();
        $controler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        try {
            $controler->cancelEnrolementAction();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }
    }

    public function testCancelEnrolementDeletesRow(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);

        $this->cancelEnrolement();

        self::assertFalse($mfaService->isPending(self::ID_U_ADMIN));
    }

    public function testCancelKeepsActiveMfa(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);

        $this->cancelEnrolement();

        self::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
    }

    public function testActivationInvalidCode(): void
    {
        $mfaService = $this->getMfaService();
        $this->enrollAuthorised();
        $secret = $mfaService->getEnrolment(self::ID_U_ADMIN, 'admin')->secret;
        $valid = TOTP::createFromSecret($secret, new Clock())->now();
        $this->setPostInfo(['code' => $valid === '000000' ? '111111' : '000000']);

        try {
            $this->doEnrolement();
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('code saisi est invalide', $e->getMessage());
        }

        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));
    }

    public function testAuthRequiredRendersPage(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        $this->setGetInfo(['action' => 'disable']);
        $this->expectOutputRegex('#Authentification nécessaire#');
        $this->getMfaControler()->authRequiredAction();
    }

    public function testAuthRequiredUnknownRedirects(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        $this->setGetInfo(['action' => 'bogus']);
        try {
            $this->getMfaControler()->authRequiredAction();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            self::assertStringContainsString('/Utilisateur/moi', $e->getMessage());
        }
    }

    public function testDoAuthUnknownRedirects(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        try {
            $this->doAuthRequired('bogus', 'admin');
            self::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            self::assertStringContainsString('/Utilisateur/moi', $e->getMessage());
        }

        self::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
    }

    public function testDoAuthNotEnabled(): void
    {
        try {
            $this->doAuthRequired('disable', 'wrong-password');
            self::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            self::assertStringContainsString('/Utilisateur/moi', $e->getMessage());
        }
    }

    public function testRegenerateRecoveryCodes(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        $this->expectOutputRegex('#[Cc]odes de récupération#');
        $this->doAuthRequired('regenerate', 'admin');

        self::assertSame(10, $this->getMfaService()->countRemainingRecoveryCodes(self::ID_U_ADMIN));
        $this->assertJournalContains('codes de récupération régénérés');
    }

    public function testRegenerateWrongPassword(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        try {
            $this->doAuthRequired('regenerate', 'wrong');
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('mot de passe est incorrect', $e->getMessage());
        }

        self::assertSame(0, $this->getMfaService()->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    public function testDisableDeletesMfa(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        try {
            $this->doAuthRequired('disable', 'admin');
            self::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        self::assertFalse($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
        $this->assertJournalContains('double authentification désactivée');
    }

    public function testDisableWrongPassword(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        try {
            $this->doAuthRequired('disable', 'wrong');
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('mot de passe est incorrect', $e->getMessage());
        }

        self::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
    }

    /**
     * @param array<string, Connecteur> $connecteurs
     */
    private function mockGlobalConnecteurs(array $connecteurs): void
    {
        $connecteurFactory = $this->createMock(ConnecteurFactory::class);
        $connecteurFactory->method('getGlobalConnecteur')
            ->willReturnCallback(static fn(string $type) => $connecteurs[$type] ?? false);
        $this->getObjectInstancier()->setInstance(ConnecteurFactory::class, $connecteurFactory);
    }

    private function getMfaControlerAsEric(): MfaControler
    {
        $controler = $this->getMfaControler();
        $this->getObjectInstancier()->getInstance(Authentification::class)->connexion('eric', self::ID_U_ERIC);
        return $controler;
    }

    /**
     * @throws LastErrorException
     */
    public function testExternalUserRedirected(): void
    {
        $this->mockGlobalConnecteurs([AuthenticationConnecteur::CONNECTEUR_TYPE_ID => $this->createMock(AuthenticationConnecteur::class)]);
        try {
            $this->getMfaControlerAsEric()->_beforeAction();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            self::assertStringContainsString('/Utilisateur/moi', $e->getMessage());
        }
    }

    /**
     * @throws LastMessageException
     * @throws RandomException
     * @throws NotFoundException
     */
    public function testDoAuthLdapError(): void
    {
        $ldapVerification = $this->createMock(LDAPVerification::class);
        $ldapVerification->method('verifLogin')->willThrowException(new UnrecoverableException('LDAP injoignable'));
        $this->mockGlobalConnecteurs([LDAPVerification::CONNECTEUR_TYPE_ID => $ldapVerification]);
        $this->enableMfa(self::ID_U_ERIC);
        $this->mockLoginAttemptLimit();

        $controler = $this->getMfaControlerAsEric();
        $controler->setServerInfo(['REQUEST_METHOD' => 'POST']);
        $this->setPostInfo(['action' => 'disable', 'password' => 'secret']);
        try {
            $controler->doAuthRequiredAction();
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('LDAP injoignable', $e->getMessage());
        }

        self::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ERIC));
    }
}
