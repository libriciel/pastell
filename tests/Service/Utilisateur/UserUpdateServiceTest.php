<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use ConflictException;
use NotificationDigestSQL;
use Pastell\Service\Utilisateur\UserUpdateService;
use PastellTestCase;
use UnrecoverableException;
use UtilisateurSQL;

class UserUpdateServiceTest extends PastellTestCase
{
    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testUpdate(): void
    {
        $userUpdateService = $this->getObjectInstancier()->getInstance(UserUpdateService::class);

        $userId = $userUpdateService->update(
            1,
            'admin',
            'email@example.org',
            'firstname',
            'lastname',
        );

        $user = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->getInfo($userId);

        static::assertSame('admin', $user['login']);
        static::assertSame('email@example.org', $user['email']);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testUpdateSyncsNotificationDigestEmail(): void
    {
        $notificationDigestSQL = $this->getObjectInstancier()->getInstance(NotificationDigestSQL::class);
        $notificationDigestSQL->add('eric@sigmalis.com', 1, 'id-d-fake', 'action-fake', 'type-fake', 'message-fake');

        $this->getObjectInstancier()->getInstance(UserUpdateService::class)->update(
            1,
            'admin',
            'new@example.com',
            'Eric',
            'Pommateau',
        );

        $all = $notificationDigestSQL->getAll();
        static::assertArrayHasKey('new@example.com', $all);
        static::assertArrayNotHasKey('eric@sigmalis.com', $all);
    }
}
