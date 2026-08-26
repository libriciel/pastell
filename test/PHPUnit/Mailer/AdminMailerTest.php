<?php

declare(strict_types=1);

use Pastell\Exception\NotificationException;
use Pastell\Mailer\AdminMailer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AdminMailerTest extends PastellTestCase
{
    use MailerTransportTestingTrait;

    public function testNotifyTextToExplicitRecipient(): void
    {
        $this->setMailerTransportForTesting();
        $adminMailer = $this->getObjectInstancier()->getInstance(AdminMailer::class);
        $adminMailer->notifyText('[Pastell] Sujet de test', 'corps du message', ['admin@example.org']);
        $this->assertMessageContainsString('Subject: [Pastell] Sujet de test');
        $this->assertMessageContainsString('admin@example.org');
        $this->assertMessageContainsString('corps du message');
    }

    public function testNotifyFromExceptionUsesHtmlTemplate(): void
    {
        $this->setMailerTransportForTesting();
        $adminMailer = $this->getObjectInstancier()->getInstance(AdminMailer::class);
        $exception = new NotificationException(
            'message glaneur',
            "[Pastell] Le traitement d'un glaneur est en erreur et suspendu",
            'glaneur_lancer_glanage.html.twig',
            ['url' => 'https://example.org', 'message' => 'message glaneur'],
        );
        $adminMailer->notifyFromException($exception);
        $this->assertMessageContainsString("Subject: [Pastell] Le traitement d'un glaneur est");
        $this->assertMessageContainsString('Glaneur en erreur');
    }
}
