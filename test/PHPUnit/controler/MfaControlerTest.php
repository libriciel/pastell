<?php

declare(strict_types=1);

use OTPHP\TOTP;
use Pastell\Service\LoginAttemptLimit;
use Pastell\Service\Utilisateur\MfaService;
use Symfony\Component\RateLimiter\RateLimit;

class MfaControlerTest extends ControlerTestCase
{
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
        $lines = $this->getObjectInstancier()->getInstance(Journal::class)
            ->getAll(false, false, false, false, 0, 100, $needle);
        self::assertNotEmpty($lines, "Aucune entrée de journal contenant : $needle");
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function testEnrolementCreatesSecret(): void
    {
        $this->expectOutputRegex('#Activer la double authentification#');
        $this->getMfaControler()->enrolementAction();
        self::assertNotEmpty($this->getMfaService()->getInfo(self::ID_U_ADMIN)['secret']);
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function testEnrolementKeepsSecret(): void
    {
        $this->expectOutputRegex('#Activer la double authentification#');
        $controler = $this->getMfaControler();

        $controler->enrolementAction();
        $first = $this->getMfaService()->getInfo(self::ID_U_ADMIN)['secret'];
        $controler->enrolementAction();
        $second = $this->getMfaService()->getInfo(self::ID_U_ADMIN)['secret'];

        self::assertSame($first, $second);
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function testEnrolementAllowedForApi(): void
    {
        $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->setIsAPI(self::ID_U_ADMIN, true);

        $this->expectOutputRegex('#Activer la double authentification#');
        $this->getMfaControler()->enrolementAction();

        self::assertNotEmpty($this->getMfaService()->getInfo(self::ID_U_ADMIN)['secret']);
    }

    public function testActivationShowsRecoveryCodes(): void
    {
        $mfaService = $this->getMfaService();
        $secret = $mfaService->generateSecret();
        $mfaService->enroll(self::ID_U_ADMIN, $secret);
        $this->setPostInfo(['code' => TOTP::createFromSecret($secret)->now()]);

        $this->expectOutputRegex('#[Cc]odes de récupération#');
        $this->getMfaControler()->doEnrolementAction();

        self::assertTrue($mfaService->isEnabled(self::ID_U_ADMIN));
        $this->assertJournalContains('double authentification activée');
        self::assertSame(10, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    private function enableMfa(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN, $mfaService->generateSecret());
        $mfaService->confirm(self::ID_U_ADMIN);
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

    public function testCancelEnrolementDeletesRow(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN, $mfaService->generateSecret());

        try {
            $this->getMfaControler()->cancelEnrolementAction();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        self::assertEmpty($mfaService->getInfo(self::ID_U_ADMIN));
    }

    public function testCancelKeepsActiveMfa(): void
    {
        $this->enableMfa();

        try {
            $this->getMfaControler()->cancelEnrolementAction();
            self::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        self::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
    }

    public function testActivationInvalidCode(): void
    {
        $mfaService = $this->getMfaService();
        $secret = $mfaService->generateSecret();
        $mfaService->enroll(self::ID_U_ADMIN, $secret);
        $valid = TOTP::createFromSecret($secret)->now();
        $this->setPostInfo(['code' => $valid === '000000' ? '111111' : '000000']);

        try {
            $this->getMfaControler()->doEnrolementAction();
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('code saisi est invalide', $e->getMessage());
        }

        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));
    }

    public function testAuthRequiredRendersPage(): void
    {
        $this->enableMfa();
        $this->setGetInfo(['action' => 'desactivation']);
        $this->expectOutputRegex('#Authentification nécessaire#');
        $this->getMfaControler()->authRequiredAction();
    }

    public function testAuthRequiredUnknownRedirects(): void
    {
        $this->enableMfa();
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
        $this->enableMfa();
        try {
            $this->doAuthRequired('bogus', 'admin');
            self::fail('Une redirection était attendue');
        } catch (LastMessageException $e) {
            self::assertStringContainsString('/Utilisateur/moi', $e->getMessage());
        }

        self::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
    }

    public function testRegenerateRecoveryCodes(): void
    {
        $this->enableMfa();
        $this->expectOutputRegex('#[Cc]odes de récupération#');
        $this->doAuthRequired('regenerate', 'admin');

        self::assertSame(10, $this->getMfaService()->countRemainingRecoveryCodes(self::ID_U_ADMIN));
        $this->assertJournalContains('codes de récupération régénérés');
    }

    public function testRegenerateWrongPassword(): void
    {
        $this->enableMfa();
        try {
            $this->doAuthRequired('regenerate', 'wrong');
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('mot de passe est incorrect', $e->getMessage());
        }

        self::assertSame(0, $this->getMfaService()->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    public function testDesactivationDeletesMfa(): void
    {
        $this->enableMfa();
        try {
            $this->doAuthRequired('desactivation', 'admin');
            self::fail('Une redirection était attendue');
        } catch (LastMessageException) {
        }

        self::assertFalse($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
        $this->assertJournalContains('double authentification désactivée');
    }

    public function testDesactivationWrongPassword(): void
    {
        $this->enableMfa();
        try {
            $this->doAuthRequired('desactivation', 'wrong');
            self::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            self::assertStringContainsString('mot de passe est incorrect', $e->getMessage());
        }

        self::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
    }
}
