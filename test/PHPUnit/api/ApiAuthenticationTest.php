<?php

declare(strict_types=1);

use Pastell\Service\LoginAttemptLimit;
use Pastell\Service\Utilisateur\UserTokenService;
use Symfony\Component\RateLimiter\RateLimit;

class ApiAuthenticationTest extends PastellTestCase
{
    public function testOk(): void
    {
        $loginAttemptLimit = $this->createMock(LoginAttemptLimit::class);
        $this->getObjectInstancier()->setInstance(LoginAttemptLimit::class, $loginAttemptLimit);
        $httpApi = $this->getObjectInstancier()->getInstance(HttpApi::class);
        $httpApi->setServerArray(['REQUEST_METHOD' => 'get', 'PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        $httpApi->setGetArray(['api_function' => 'v2/version']);
        $this->expectOutputRegex('#1.4-fixtures#');
        $httpApi->dispatch();
    }

    public function testWithADisabledAccount(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $utilisateurSQL->disable(1);
        $loginAttemptLimit = $this->createMock(LoginAttemptLimit::class);
        $this->getObjectInstancier()->setInstance(LoginAttemptLimit::class, $loginAttemptLimit);
        $httpApi = $this->getObjectInstancier()->getInstance(HttpApi::class);
        $httpApi->setServerArray(['REQUEST_METHOD' => 'get', 'PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        $httpApi->setGetArray(['api_function' => 'v2/version']);
        $this->expectOutputRegex("#Votre compte a#");
        $httpApi->dispatch();
    }

    public function testTokenAuthentication(): void
    {
        $loginAttemptLimit = $this->createMock(LoginAttemptLimit::class);
        $this->getObjectInstancier()->setInstance(LoginAttemptLimit::class, $loginAttemptLimit);

        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $token = $userTokenService->createToken(self::ID_U_ADMIN, 'token');
        $httpApi = $this->getObjectInstancier()->getInstance(HttpApi::class);
        $httpApi->setServerArray(['REQUEST_METHOD' => 'get', 'HTTP_AUTHORIZATION' => "Bearer $token"]);
        $httpApi->setGetArray(['api_function' => 'v2/version']);
        $this->expectOutputRegex('/1.4-fixtures/');
        $httpApi->dispatch();
    }
    public function testExpiredTokenAuthentication(): void
    {
        $loginAttemptLimit = $this->createMock(LoginAttemptLimit::class);
        $loginAttemptLimit->method('getRateLimit')
            ->willReturn(new RateLimit(5, new \DateTimeImmutable(), true, 5));
        $loginAttemptLimit->expects(static::once())
            ->method('consumeLoginAttempt')
            ->with('127.0.0.1');
        $this->getObjectInstancier()->setInstance(LoginAttemptLimit::class, $loginAttemptLimit);

        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $token = $userTokenService->createToken(self::ID_U_ADMIN, 'token', date(Date::DATE_ISO, strtotime('-1 day')));
        $httpApi = $this->getObjectInstancier()->getInstance(HttpApi::class);
        $httpApi->setServerArray([
            'REQUEST_METHOD' => 'get',
            'HTTP_AUTHORIZATION' => "Bearer $token",
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $httpApi->setGetArray(['api_function' => 'v2/version']);
        $this->expectOutputRegex('/401 Unauthorized/');
        $httpApi->dispatch();
    }
}
