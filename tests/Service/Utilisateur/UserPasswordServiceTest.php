<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Utilisateur\UserPasswordService;
use PastellTestCase;

final class UserPasswordServiceTest extends PastellTestCase
{
    private function getUserPasswordService(): UserPasswordService
    {
        return $this->getObjectInstancier()->getInstance(UserPasswordService::class);
    }

    public function testVerifyPassword(): void
    {
        $this->getUserPasswordService()->setPassword(1, 'N3w-P@ssw0rd');

        self::assertTrue($this->getUserPasswordService()->verifyPassword(1, 'N3w-P@ssw0rd'));
    }

    public function testVerifyWrongPassword(): void
    {
        $this->getUserPasswordService()->setPassword(1, 'N3w-P@ssw0rd');

        self::assertFalse($this->getUserPasswordService()->verifyPassword(1, 'wrong'));
    }

    public function testVerifyPasswordUnknownUser(): void
    {
        self::assertFalse($this->getUserPasswordService()->verifyPassword(42, 'N3w-P@ssw0rd'));
    }
}
