<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use AuthenticationConnecteur;
use ConnecteurFactory;
use EntiteMfaObligationSQL;
use NotFoundException;
use Pastell\Service\Utilisateur\MfaObligationService;
use Pastell\Service\Utilisateur\MfaService;
use PastellTestCase;
use UnrecoverableException;
use UtilisateurSQL;

class MfaObligationServiceTest extends PastellTestCase
{
    private const int ID_U_ERIC = 2;

    private function getService(): MfaObligationService
    {
        return $this->getObjectInstancier()->getInstance(MfaObligationService::class);
    }

    private function getMfaService(): MfaService
    {
        return $this->getObjectInstancier()->getInstance(MfaService::class);
    }

    private function getEntiteMfaObligationSQL(): EntiteMfaObligationSQL
    {
        return $this->getObjectInstancier()->getInstance(EntiteMfaObligationSQL::class);
    }

    public function testNoObligation(): void
    {
        static::assertFalse($this->getService()->isObligatory(self::ID_U_ADMIN));
        static::assertFalse($this->getService()->mustEnroll(self::ID_U_ADMIN));
    }

    public function testMustEnrollWithObligation(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        static::assertTrue($this->getService()->mustEnroll(self::ID_U_ADMIN));
    }

    public function testNoEnrollWhenEnabled(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $this->enableMfa(self::ID_U_ADMIN);
        static::assertTrue($this->getService()->isObligatory(self::ID_U_ADMIN));
        static::assertFalse($this->getService()->mustEnroll(self::ID_U_ADMIN));
    }

    public function testApiUserNotObligatory(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->setIsAPI(self::ID_U_ERIC, true);
        static::assertFalse($this->getService()->isObligatory(self::ID_U_ERIC));
    }

    public function testExternalUserNotObligatory(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $connecteurFactory = $this->createMock(ConnecteurFactory::class);
        $connecteurFactory->method('getGlobalConnecteur')->willReturnCallback(
            fn(string $type) => $type === AuthenticationConnecteur::CONNECTEUR_TYPE_ID
                ? $this->createMock(AuthenticationConnecteur::class)
                : false
        );
        $this->getObjectInstancier()->setInstance(ConnecteurFactory::class, $connecteurFactory);

        static::assertFalse($this->getService()->isObligatory(self::ID_U_ERIC));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testEnableAndDisable(): void
    {
        $this->getService()->enableForEntity(self::ID_E_COL, self::ID_U_ADMIN);
        static::assertTrue($this->getService()->appliesTo(self::ID_E_SERVICE));
        static::assertNotEmpty(
            $this->getJournalConsultationService()->getList(recherche: 'obligation de double authentification activée')
        );

        $this->getService()->disableForEntity(self::ID_E_COL, self::ID_U_ADMIN);
        static::assertFalse($this->getService()->appliesTo(self::ID_E_SERVICE));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testDisableUnsetNotJournalised(): void
    {
        $this->getService()->disableForEntity(self::ID_E_COL, self::ID_U_ADMIN);
        static::assertEmpty(
            $this->getJournalConsultationService()->getList(recherche: 'obligation de double authentification')
        );
    }

    public function testInheritedCannotBeChanged(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(self::ID_E_COL);
        $this->expectException(UnrecoverableException::class);
        $this->getService()->disableForEntity(self::ID_E_SERVICE, self::ID_U_ADMIN);
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testSelfResetKeepsMfaEnabled(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $this->enableMfa(self::ID_U_ADMIN);

        $this->getService()->resetMfa(self::ID_U_ADMIN);

        static::assertTrue($this->getMfaService()->isEnabled(self::ID_U_ADMIN));
        static::assertTrue($this->getMfaService()->isPending(self::ID_U_ADMIN));
        static::assertFalse($this->getService()->mustEnroll(self::ID_U_ADMIN));
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testAdminResetFollowsObligation(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $this->enableMfa(self::ID_U_ERIC);

        $this->getService()->resetMfa(self::ID_U_ERIC, self::ID_U_ADMIN);
        static::assertFalse($this->getMfaService()->isEnabled(self::ID_U_ERIC));
        static::assertTrue($this->getService()->mustEnroll(self::ID_U_ERIC));

        $this->getEntiteMfaObligationSQL()->disable(0);
        static::assertFalse($this->getService()->mustEnroll(self::ID_U_ERIC));
    }

    /**
     * @throws NotFoundException
     */
    public function testResetNotObligatory(): void
    {
        $this->enableMfa(self::ID_U_ADMIN);
        $this->expectException(UnrecoverableException::class);
        $this->getService()->resetMfa(self::ID_U_ADMIN);
    }

    /**
     * @throws NotFoundException
     */
    public function testDisableBlockedWhenObligatory(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $this->enableMfa(self::ID_U_ADMIN);
        $this->expectException(UnrecoverableException::class);
        $this->getService()->disableMfa(self::ID_U_ADMIN);
    }

    public function testCancelBlockedWhenObligatory(): void
    {
        $this->getEntiteMfaObligationSQL()->enable(0);
        $this->getMfaService()->enroll(self::ID_U_ADMIN);
        $this->expectException(UnrecoverableException::class);
        $this->getService()->cancelEnrolment(self::ID_U_ADMIN);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testCancelWithoutObligation(): void
    {
        $this->getMfaService()->enroll(self::ID_U_ADMIN);
        $this->getService()->cancelEnrolment(self::ID_U_ADMIN);
        static::assertFalse($this->getMfaService()->isPending(self::ID_U_ADMIN));
    }
}
