<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use ConflictException;
use Pastell\Mailer\Mailer;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Tests\MailerTransportTesting;
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

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testCreateWithResetMail(): void
    {
        $userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $pastellMailer = $this->getObjectInstancier()->getInstance(Mailer::class);

        $mailerTransportTesting = new MailerTransportTesting();
        $mailer = new \Symfony\Component\Mailer\Mailer($mailerTransportTesting);
        $pastellMailer->setMailer($mailer);

        $userId = $userCreationService->create(
            'login_with_mail',
            'email2@example.org',
            'firstname',
            'lastname',
            0,
            null,
            true
        );

        $user = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->getInfo($userId);
        static::assertSame('login_with_mail', $user['login']);

        $sentMessage = $mailerTransportTesting->getSentMessage();
        $messageString = $sentMessage->getMessage()->toString();

        static::assertStringContainsString('Subject: [Pastell]', $messageString);
        static::assertStringContainsString('/Connexion/changementMdp?mail_verif=', $messageString);
        static::assertStringContainsString('<strong>login_with_mail</strong>', $messageString);
    }
}
