<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Utilisateur\UserTokenService;
use PastellTestCase;
use UserTokenSQL;

final class UserTokenServiceTest extends PastellTestCase
{
    public function testTokenStoredHashed(): void
    {
        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $token = $userTokenService->createToken(self::ID_U_ADMIN, 'token-test');

        $userTokenSQL = $this->getObjectInstancier()->getInstance(UserTokenSQL::class);
        self::assertNotNull($userTokenSQL->getInfoByToken(\hash('sha256', $token)));
        self::assertNull($userTokenSQL->getInfoByToken($token));
    }

    public function testRenewTokenStoredHashed(): void
    {
        $userTokenService = $this->getObjectInstancier()->getInstance(UserTokenService::class);
        $old_token = $userTokenService->createToken(self::ID_U_ADMIN, 'token-test');
        $token_id = (int) $userTokenService->getTokenInfo($old_token)['id'];

        $new_token = $userTokenService->renewToken($token_id);

        self::assertNull($userTokenService->getTokenInfo($old_token));
        self::assertSame(\hash('sha256', $new_token), $userTokenService->getTokenInfo($new_token)['token']);
    }
}
