<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Mailer\Mailer;
use Pastell\Service\Utilisateur\PasswordResetMailService;
use Pastell\Tests\MailerTransportTesting;
use PastellTestCase;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetServiceTest extends PastellTestCase
{
    /**
     * @throws TransportExceptionInterface
     */
    public function testSendResetMail(): void
    {
        $passwordResetService = $this->getObjectInstancier()->getInstance(PasswordResetMailService::class);
        $pastellMailer = $this->getObjectInstancier()->getInstance(Mailer::class);

        $mailerTransportTesting = new MailerTransportTesting();
        $mailer = new \Symfony\Component\Mailer\Mailer($mailerTransportTesting);
        $pastellMailer->setMailer($mailer);

        $passwordResetService->sendResetMail(self::ID_U_ADMIN);

        $sentMessage = $mailerTransportTesting->getSentMessage();
        $messageString = $sentMessage->getMessage()->toString();

        self::assertStringContainsString(
            'Subject: [Pastell]',
            $messageString
        );
        self::assertStringContainsString(
            '/Connexion/changementMdp?mail_verif=',
            $messageString
        );
        self::assertStringContainsString(
            '<strong>admin</strong>',
            $messageString
        );
    }
}
