<?php

declare(strict_types=1);

use OTPHP\TOTP;
use Pastell\Service\Utilisateur\MfaService;
use Random\RandomException;

class MfaServiceTest extends PastellTestCase
{
    private function getMfaService(): MfaService
    {
        return $this->getObjectInstancier()->getInstance(MfaService::class);
    }

    public function testGenerateSecretIsBase32(): void
    {
        $secret = $this->getMfaService()->generateSecret();
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testVerifyValidCode(): void
    {
        $secret = $this->getMfaService()->generateSecret();
        self::assertTrue($this->getMfaService()->verify($secret, TOTP::createFromSecret($secret)->now()));
    }

    public function testVerifyInvalidCode(): void
    {
        $secret = $this->getMfaService()->generateSecret();
        $valid = TOTP::createFromSecret($secret)->now();
        $wrong = $valid === '000000' ? '111111' : '000000';
        self::assertFalse($this->getMfaService()->verify($secret, $wrong));
    }

    public function testEnrollConfirmDelete(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN, $mfaService->generateSecret());
        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));

        $mfaService->confirm(self::ID_U_ADMIN);
        self::assertTrue($mfaService->isEnabled(self::ID_U_ADMIN));

        $mfaService->delete(self::ID_U_ADMIN);
        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));
        self::assertSame([], $mfaService->getInfo(self::ID_U_ADMIN));
    }

    public function testProvisioningUriContainsSecret(): void
    {
        $secret = $this->getMfaService()->generateSecret();
        $uri = $this->getMfaService()->getProvisioningUri($secret, 'jdupont');
        self::assertStringContainsString($secret, $uri);
        self::assertStringContainsString('Pastell', $uri);
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
        $mfaService->enroll(self::ID_U_ADMIN, $mfaService->generateSecret());
        $mfaService->generateRecoveryCodes(self::ID_U_ADMIN);

        $mfaService->delete(self::ID_U_ADMIN);
        self::assertSame(0, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    private function getEntiteMfaObligationSQL(): EntiteMfaObligationSQL
    {
        return $this->getObjectInstancier()->getInstance(EntiteMfaObligationSQL::class);
    }

    public function testNoEnrollWithoutObligation(): void
    {
        self::assertFalse($this->getMfaService()->mustEnroll(self::ID_U_ADMIN));
    }

    public function testMustEnrollWithObligation(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        self::assertTrue($this->getMfaService()->mustEnroll(self::ID_U_ADMIN));
    }

    public function testNoEnrollWhenAlreadyEnabled(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN, $mfaService->generateSecret());
        $mfaService->confirm(self::ID_U_ADMIN);
        self::assertFalse($mfaService->mustEnroll(self::ID_U_ADMIN));
    }

    public function testObligatoryWithObligation(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN, $mfaService->generateSecret());
        $mfaService->confirm(self::ID_U_ADMIN);
        self::assertTrue($mfaService->isObligatory(self::ID_U_ADMIN));
    }

    public function testNotObligatoryWithoutObligation(): void
    {
        self::assertFalse($this->getMfaService()->isObligatory(self::ID_U_ADMIN));
    }

    public function testReenrolmentForcesEnroll(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->enroll(self::ID_U_ADMIN, $mfaService->generateSecret());
        $mfaService->confirm(self::ID_U_ADMIN);

        $mfaService->requireReenrolment(self::ID_U_ADMIN);

        self::assertFalse($mfaService->isEnabled(self::ID_U_ADMIN));
        self::assertTrue($mfaService->mustEnroll(self::ID_U_ADMIN));
        self::assertSame(0, $mfaService->countRemainingRecoveryCodes(self::ID_U_ADMIN));
    }

    public function testReenrolmentClearedAfterConfirm(): void
    {
        $mfaService = $this->getMfaService();
        $mfaService->requireReenrolment(self::ID_U_ADMIN);
        $mfaService->enroll(self::ID_U_ADMIN, $mfaService->generateSecret());
        self::assertTrue($mfaService->mustEnroll(self::ID_U_ADMIN));

        $mfaService->confirm(self::ID_U_ADMIN);
        self::assertFalse($mfaService->mustEnroll(self::ID_U_ADMIN));
    }

    public function testNoEnrollWithoutReset(): void
    {
        self::assertFalse($this->getMfaService()->mustEnroll(self::ID_U_ADMIN));
    }
}
