<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use ConflictException;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UserPasswordService;
use PastellTestCase;
use UnrecoverableException;
use UtilisateurSQL;

class UserCreationServiceTest extends PastellTestCase
{
    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testCreate(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);

        $userId = $userCreationService->create(
            'login',
            'email@example.org',
            'firstname',
            'lastname',
        );

        $user = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->getInfo($userId);

        static::assertSame('login', $user['login']);
    }

    public function testCreateAccount(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);

        $userId = $userCreationService->createAccount('login', 'N3w-P@ssw0rd', 'login@example.invalid', 'token');

        $user = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->getInfo($userId);
        static::assertSame('login', $user['login']);
        static::assertSame('token', $user['mail_verif_password']);
        static::assertTrue(
            $this->getObjectInstancier()->getInstance(UserPasswordService::class)->verifyPassword($userId, 'N3w-P@ssw0rd')
        );
    }
}
