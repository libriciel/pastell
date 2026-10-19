<?php

declare(strict_types=1);

use OTPHP\TOTP;
use Symfony\Component\Clock\Clock;
use Pastell\Service\Utilisateur\MfaService;
use Random\RandomException;

class MfaServiceTest extends PastellTestCase
{
    private function getMfaService(): MfaService
    {
        return $this->getObjectInstancier()->getInstance(MfaService::class);
    }

    public function testEnrolSecretIsBase32(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        $secret = $mfaService->getEnrolment(self::ID_U_ADMIN, 'admin')->secret;
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testVerifyInvalidCode(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        $secret = $mfaService->getEnrolment(self::ID_U_ADMIN, 'admin')->secret;
        $valid = TOTP::createFromSecret($secret, new Clock())->now();
        $wrong = $valid === '000000' ? '111111' : '000000';
        self::assertFalse($mfaService->verifyForUser(self::ID_U_ADMIN, $wrong));
    }

    public function testTotpSingleUse(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        $secret = $mfaService->getEnrolment(self::ID_U_ADMIN, 'admin')->secret;
        $code = TOTP::createFromSecret($secret, new Clock())->now();

        self::assertTrue($mfaService->verifyForUser(self::ID_U_ADMIN, $code));
        self::assertFalse($mfaService->verifyForUser(self::ID_U_ADMIN, $code));
    }

    public function testEnrollConfirmDelete(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));

        $mfaService->confirm(self::ID_U_ADMIN);
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
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        $mfaService->confirm(self::ID_U_ADMIN);

        self::assertNull($mfaService->getEnrolment(self::ID_U_ADMIN, 'jdupont'));
    }

    /**
     * @throws RandomException
     */
    public function testGenerateRecoveryCodes(): void
    {
        $codes = $this->getMfaService()->generateRecoveryCodes(self::ID_U_ADMIN);
        self::assertCount(10, $codes);
        self::assertSame(10, $this->getMfaService()->countRemainingRecoveryCodes(self::ID_U_ADMIN));
        self::assertMatchesRegularExpression('/^([a-z0-9]{5}-){3}[a-z0-9]{5}$/', $codes[0]);
    }

    /**
     * @throws RandomException
     */
    public function testRecoveryCodeSingleUse(): void
    {
        $mfaService = $this->getMfaService();
        $codes = $mfaService->generateRecoveryCodes(self::ID_U_ADMIN);

        self::assertTrue($mfaService->verifyRecoveryCode(self::ID_U_ADMIN, $codes[0]));
        self::assertFalse($mfaService->verifyRecoveryCode(self::ID_U_ADMIN, $codes[0]));
        self::assertSame(9, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    /**
     * @throws RandomException
     */
    public function testRecoveryCodeInvalid(): void
    {
        $this->getMfaService()->generateRecoveryCodes(self::ID_U_ADMIN);
        self::assertFalse($this->getMfaService()->verifyRecoveryCode(self::ID_U_ADMIN, 'not-a-code'));
    }

    /**
     * @throws RandomException
     */
    public function testRegenerateInvalidatesOld(): void
    {
        $mfaService = $this->getMfaService();
        $oldCodes = $mfaService->generateRecoveryCodes(self::ID_U_ADMIN);
        $mfaService->generateRecoveryCodes(self::ID_U_ADMIN);

        self::assertFalse($mfaService->verifyRecoveryCode(self::ID_U_ADMIN, $oldCodes[0]));
        self::assertSame(10, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    /**
     * @throws RandomException
     */
    public function testDeleteRemovesRecoveryCodes(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN);
        $mfaService->generateRecoveryCodes(self::ID_U_ADMIN);

        $mfaService->delete(self::ID_U_ADMIN);
        self::assertSame(0, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }
}
