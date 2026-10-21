<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use AuthenticationConnecteur;
use Connecteur;
use ConnecteurFactory;
use LDAPVerification;
use Pastell\Service\Utilisateur\UserAuthenticationService;
use PastellTestCase;
use UnrecoverableException;

class UserAuthenticationServiceTest extends PastellTestCase
{
    private const int ID_U_ERIC = 2;

    private function getService(): UserAuthenticationService
    {
        return $this->getObjectInstancier()->getInstance(UserAuthenticationService::class);
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

    private function mockLdap(bool $passwordValid): void
    {
        $ldapVerification = $this->createMock(LDAPVerification::class);
        $ldapVerification->method('verifLogin')->with('eric', 'ldap-password')->willReturn($passwordValid);
        $this->mockGlobalConnecteurs(['Vérification' => $ldapVerification]);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testVerifyLocalPassword(): void
    {
        static::assertTrue($this->getService()->verifyPassword(self::ID_U_ADMIN, 'admin'));
        static::assertFalse($this->getService()->verifyPassword(self::ID_U_ADMIN, 'wrong'));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testVerifyLdapPassword(): void
    {
        $this->mockLdap(true);
        static::assertTrue($this->getService()->verifyPassword(self::ID_U_ERIC, 'ldap-password'));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testVerifyWrongLdapPassword(): void
    {
        $this->mockLdap(false);
        static::assertFalse($this->getService()->verifyPassword(self::ID_U_ERIC, 'ldap-password'));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testAdminIgnoresLdap(): void
    {
        $this->mockLdap(false);
        static::assertTrue($this->getService()->verifyPassword(self::ID_U_ADMIN, 'admin'));
    }

    public function testIsNotExternal(): void
    {
        static::assertFalse($this->getService()->isExternal(self::ID_U_ERIC));
    }

    public function testIsExternal(): void
    {
        $this->mockGlobalConnecteurs(['Authentification' => $this->createMock(AuthenticationConnecteur::class)]);
        static::assertTrue($this->getService()->isExternal(self::ID_U_ERIC));
        static::assertFalse($this->getService()->isExternal(self::ID_U_ADMIN));
    }
}
