<?php

namespace Pastell\Tests\Service;

use Notification;
use NotificationDigestSQL;
use Pastell\Service\Utilisateur\MfaService;
use Pastell\Service\Utilisateur\UtilisateurDeletionService;
use PastellTestCase;
use UtilisateurNewEmailSQL;
use UtilisateurSQL;
use UsersToken;

class UtilisateurDeletionServiceTest extends PastellTestCase
{
    public function testDelete(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $this->assertTrue($utilisateurSQL->exists(2));

        $entiteDeletionService = $this->getObjectInstancier()->getInstance(
            UtilisateurDeletionService::class
        );
        $entiteDeletionService->delete(2);
        $journal_message = $this->getJournal()->getAll()[0]['message'];
        $expected_journal_message = "Suppression de l'utilisateur eric (id_u=2)";
        $this->assertEquals(
            $expected_journal_message,
            $journal_message
        );
        $this->assertFalse($utilisateurSQL->exists(2));
        $log_message = $this->getLogRecords()[0]['message'];
        $this->assertMatchesRegularExpression(
            "#^Ajout au journal \(id_j=1\): 4 - 0 - 1 - 0 - Supprimé - " . preg_quote($expected_journal_message, '#') . "#",
            $log_message
        );
    }

    public function testDeleteCleansNotifications(): void
    {
        $notification = $this->getObjectInstancier()->getInstance(Notification::class);
        $notification->add(2, 1, 'type-fake', 'action-fake', 0);
        self::assertNotEmpty($notification->getAll(2));

        $this->getObjectInstancier()->getInstance(UtilisateurDeletionService::class)->delete(2);

        self::assertEmpty($notification->getAll(2));
    }

    public function testDeleteCleansTokens(): void
    {
        $usersToken = $this->getObjectInstancier()->getInstance(UsersToken::class);
        $usersToken->create(2, 'token-test', 'my-token');
        self::assertNotEmpty($usersToken->getTokensOfUser(2));

        $this->getObjectInstancier()->getInstance(UtilisateurDeletionService::class)->delete(2);

        self::assertEmpty($usersToken->getTokensOfUser(2));
    }

    public function testDeleteCleansNewEmail(): void
    {
        $utilisateurNewEmailSQL = $this->getObjectInstancier()->getInstance(UtilisateurNewEmailSQL::class);
        $password = $utilisateurNewEmailSQL->add(2, 'newemail@test.fr');
        self::assertNotEmpty($utilisateurNewEmailSQL->confirm($password));

        $this->getObjectInstancier()->getInstance(UtilisateurDeletionService::class)->delete(2);

        self::assertEmpty($utilisateurNewEmailSQL->confirm($password));
    }

    public function testDeleteCleansNotificationDigest(): void
    {
        $notificationDigestSQL = $this->getObjectInstancier()->getInstance(NotificationDigestSQL::class);
        $notificationDigestSQL->add('eric2@sigmalis.com', 1, 'id-d-fake', 'action-fake', 'type-fake', 'message-fake');
        self::assertNotEmpty($notificationDigestSQL->getAll());

        $this->getObjectInstancier()->getInstance(UtilisateurDeletionService::class)->delete(2);

        self::assertEmpty($notificationDigestSQL->getAll());
    }

    public function testDeleteCleansMfa(): void
    {
        $mfaService = $this->getObjectInstancier()->getInstance(MfaService::class);
        $mfaService->enroll(2, $mfaService->generateSecret());
        $mfaService->confirm(2);
        $mfaService->generateRecoveryCodes(2);
        self::assertTrue($mfaService->isEnabled(2));
        self::assertSame(10, $mfaService->countRemainingRecoveryCodes(2));

        $this->getObjectInstancier()->getInstance(UtilisateurDeletionService::class)->delete(2);

        self::assertFalse($mfaService->isEnabled(2));
        self::assertSame(0, $mfaService->countRemainingRecoveryCodes(2));
    }
}
