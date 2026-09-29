<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Exception;
use Pastell\Service\Utilisateur\PasswordResetService;
use Pastell\Mailer\Mailer;
use Pastell\Service\Utilisateur\PasswordResetMailService;
use Pastell\Tests\MailerTransportTesting;
use PastellTestCase;
use UtilisateurSQL;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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
