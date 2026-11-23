<?php

declare(strict_types=1);

use OTPHP\TOTP;
use Symfony\Component\Clock\Clock;
use Pastell\Service\Utilisateur\MfaLoginResult;
use Pastell\Service\Utilisateur\MfaService;
use Random\RandomException;

class MfaServiceTest extends PastellTestCase
{
    private function getMfaService(): MfaService
    {
        return $this->getObjectInstancier()->getInstance(MfaService::class);
    }

    private function enrollAndGetCode(int $id_u): string
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll($id_u);
        $secret = $mfaService->getEnrolment($id_u, 'admin')->secret;
        return TOTP::createFromSecret($secret, new Clock())->now();
    }

    private function enableMfaAndGetCode(int $id_u): string
    {
        return TOTP::createFromSecret($this->enableMfa($id_u), new Clock())->now();
    }

    private function verifyLoginCode(string $code): MfaLoginResult
    {
        return $this->getMfaService()->verifyLoginCode(self::ID_U_ADMIN, $code, '127.0.0.1');
    }

    private function assertJournalContains(string $needle): void
    {
        self::assertNotEmpty($this->getJournalConsultationService()->getList(recherche: $needle));
    }

    public function testEnrolSecretIsBase32(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        $secret = $mfaService->getEnrolment(self::ID_U_ADMIN, 'admin')->secret;
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testEnrollEnableDelete(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));

        $this->enableMfa(self::ID_U_ADMIN);
        self::assertTrue($mfaService->isEnabled(self::ID_U_ADMIN));

        $mfaService->delete(self::ID_U_ADMIN);
        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));
        self::assertFalse($mfaService->isPending(self::ID_U_ADMIN));
    }

    public function testGetEnrolment(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);

        $enrolment = $mfaService->getEnrolment(self::ID_U_ADMIN, 'jdupont');
        self::assertNotEmpty($enrolment?->secret);
        self::assertStringContainsString('<svg', $enrolment->qrCodeSvg);
    }

    public function testEnrolmentHiddenOnceEnabled(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);

        self::assertNull($this->getMfaService()->getEnrolment(self::ID_U_ADMIN, 'jdupont'));
    }

    /**
     * @throws RandomException
     * @throws UnrecoverableException
     */
    public function testDeleteRemovesRecoveryCodes(): void
    {
        $mfaService = $this->getMfaService();
        $this->enableMfa(self::ID_U_ADMIN);
        $mfaService->regenerateRecoveryCodes(self::ID_U_ADMIN);

        $mfaService->delete(self::ID_U_ADMIN);
        self::assertSame(0, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    public function testDeleteAllExceptAdmin(): void
    {
        $mfaService = $this->getMfaService();
        $this->enableMfa(self::ID_U_ADMIN);
        $this->enableMfa(2);

        $mfaService->deleteAllExceptAdmin();

        self::assertFalse($mfaService->isEnabled(2));
        self::assertTrue($mfaService->isEnabled(self::ID_U_ADMIN));
    }

    /**
     * @throws RandomException
     * @throws UnrecoverableException
     */
    public function testActivate(): void
    {
        $code = $this->enrollAndGetCode(self::ID_U_ADMIN);

        $recovery_codes = $this->getMfaService()->activate(self::ID_U_ADMIN, $code);

        self::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
        self::assertCount(10, $recovery_codes);
        self::assertMatchesRegularExpression('/^([a-z0-9]{5}-){3}[a-z0-9]{5}$/', $recovery_codes[0]);
        self::assertSame(10, $this->getMfaService()->countRemainingRecoveryCodes(self::ID_U_ADMIN));
        $this->assertJournalContains('double authentification activée');
    }

    /**
     * @throws RandomException
     */
    public function testActivateInvalidCode(): void
    {
        $code = $this->enrollAndGetCode(self::ID_U_ADMIN);

        try {
            $this->getMfaService()->activate(self::ID_U_ADMIN, $code === '000000' ? '111111' : '000000');
            self::fail('Une UnrecoverableException était attendue');
        } catch (UnrecoverableException $e) {
            self::assertSame('Le code saisi est invalide.', $e->getMessage());
        }
        self::assertFalse($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
    }

    /**
     * @throws RandomException
     */
    public function testActivateAlreadyEnabled(): void
    {
        $code = $this->enableMfaAndGetCode(self::ID_U_ADMIN);

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('déjà activée');
        $this->getMfaService()->activate(self::ID_U_ADMIN, $code);
    }

    /**
     * @throws RandomException
     * @throws UnrecoverableException
     */
    public function testRegenerateRecoveryCodes(): void
    {
        $mfaService = $this->getMfaService();
        $this->enableMfa(self::ID_U_ADMIN);
        $old_codes = $mfaService->regenerateRecoveryCodes(self::ID_U_ADMIN);

        $mfaService->regenerateRecoveryCodes(self::ID_U_ADMIN);

        self::assertSame(MfaLoginResult::INVALID, $this->verifyLoginCode($old_codes[0]));
        self::assertSame(10, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
        $this->assertJournalContains('codes de récupération régénérés');
    }

    /**
     * @throws RandomException
     */
    public function testRegenerateNotEnabled(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("n'est pas activée");
        $this->getMfaService()->regenerateRecoveryCodes(self::ID_U_ADMIN);
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testDisable(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);

        $this->getMfaService()->disable(self::ID_U_ADMIN);

        self::assertFalse($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
        $this->assertJournalContains('a désactivé la double authentification');
    }

    /**
     * @throws NotFoundException
     */
    public function testDisableNotEnabled(): void
    {
        $this->getMfaService()->enroll(self::ID_U_ADMIN);

        try {
            $this->getMfaService()->disable(self::ID_U_ADMIN);
            self::fail('Une UnrecoverableException était attendue');
        } catch (UnrecoverableException $e) {
            self::assertStringContainsString("n'est pas activée", $e->getMessage());
        }
        self::assertTrue($this->getMfaService()->isPending(self::ID_U_ADMIN));
        self::assertEmpty($this->getJournalConsultationService()->getList(recherche: 'a désactivé la double'));
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testDisableByAdmin(): void
    {
        $this->enableMfa(2);

        $this->getMfaService()->disable(2, self::ID_U_ADMIN);

        self::assertFalse($this->getMfaService()->isEnabled(2));
        $this->assertJournalContains('désactivée par un administrateur');
    }

    /**
     * @throws NotFoundException
     */
    public function testAdminDisableNotEnabled(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("n'est pas activée");
        $this->getMfaService()->disable(2, self::ID_U_ADMIN);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testAdminDisableUnknownUser(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getMfaService()->disable(999, self::ID_U_ADMIN);
    }

    public function testVerifyLoginCodeTotp(): void
    {
        $code = $this->enableMfaAndGetCode(self::ID_U_ADMIN);

        self::assertSame(MfaLoginResult::TOTP, $this->verifyLoginCode($code));
        self::assertSame(MfaLoginResult::INVALID, $this->verifyLoginCode($code));
    }

    public function testVerifyLoginCodeUnknownUser(): void
    {
        self::assertSame(
            MfaLoginResult::INVALID,
            $this->getMfaService()->verifyLoginCode(999, '000000', '127.0.0.1')
        );
    }

    public function testVerifyLoginCodePending(): void
    {
        $code = $this->enrollAndGetCode(self::ID_U_ADMIN);

        self::assertSame(MfaLoginResult::INVALID, $this->verifyLoginCode($code));
    }

    /**
     * @throws RandomException
     * @throws UnrecoverableException
     */
    public function testVerifyLoginCodeRecovery(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        $recovery_codes = $this->getMfaService()->regenerateRecoveryCodes(self::ID_U_ADMIN);

        self::assertSame(MfaLoginResult::RECOVERY_CODE, $this->verifyLoginCode($recovery_codes[0]));
        self::assertSame(MfaLoginResult::INVALID, $this->verifyLoginCode($recovery_codes[0]));
        self::assertSame(9, $this->getMfaService()->countRemainingRecoveryCodes(self::ID_U_ADMIN));
        $this->assertJournalContains('Connexion par code de récupération');
    }

    public function testVerifyLoginCodeInvalid(): void
    {
        $code = $this->enableMfaAndGetCode(self::ID_U_ADMIN);

        self::assertSame(MfaLoginResult::INVALID, $this->verifyLoginCode($code === '000000' ? '111111' : '000000'));
        self::assertSame(MfaLoginResult::INVALID, $this->verifyLoginCode('not-a-code'));
        $this->assertJournalContains('Échec double authentification');
    }

    /**
     * @throws RandomException
     * @throws UnrecoverableException
     */
    public function testLowRecoveryCodeCount(): void
    {
        $mfaService = $this->getMfaService();
        $this->enableMfa(self::ID_U_ADMIN);
        $recovery_codes = $mfaService->regenerateRecoveryCodes(self::ID_U_ADMIN);
        self::assertFalse($mfaService->isRecoveryCodeCountLow(
            $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN)
        ));

        foreach (array_slice($recovery_codes, 0, 7) as $recovery_code) {
            $this->verifyLoginCode($recovery_code);
        }
        self::assertTrue($mfaService->isRecoveryCodeCountLow(
            $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN)
        ));
    }
}
