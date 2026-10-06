<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Exception;
use Pastell\Service\Utilisateur\PasswordResetService;
use PastellTestCase;
use UtilisateurSQL;

class PasswordResetServiceTest extends PastellTestCase
{
    private function getService(): PasswordResetService
    {
        return $this->getObjectInstancier()->getInstance(PasswordResetService::class);
    }

    private function getUtilisateurSQL(): UtilisateurSQL
    {
        return $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
    }

    public function testChangePassword(): void
    {
        $this->getUtilisateurSQL()->reinitPassword(1, 'old-token');

        $this->getService()->changePassword(1, 'N3w-P@ssw0rd-Str0ng!');

        static::assertTrue($this->getUtilisateurSQL()->verifPassword(1, 'N3w-P@ssw0rd-Str0ng!'));
        static::assertNotSame('old-token', $this->getUtilisateurSQL()->getInfo(1)['mail_verif_password']);
    }

    /**
     * @throws Exception
     */
    public function testGenerateResetToken(): void
    {
        $token = $this->getService()->generateResetToken(1);

        static::assertNotEmpty($token);
        static::assertSame($token, $this->getUtilisateurSQL()->getInfo(1)['mail_verif_password']);
    }
}
