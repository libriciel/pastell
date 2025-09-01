<?php

declare(strict_types=1);

namespace Pastell\Tests\Mailer;

use Pastell\Mailer\Mailer;
use Pastell\Tests\MailerTransportTesting;
use PastellTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MailerTest extends PastellTestCase
{
    /**
     * @throws TransportExceptionInterface
     */
    public function testSendMailWithCustomTransport(): void
    {
        $templatedEmail = (new TemplatedEmail())
            ->to('foo@bar.foo')
            ->subject('[Pastell] Mail de test')
            ->text('texte');
        $pastellMailer = $this->getObjectInstancier()->getInstance(Mailer::class);
        $mailerTransportTesting = new MailerTransportTesting();
        $mailer = new \Symfony\Component\Mailer\Mailer($mailerTransportTesting);
        $pastellMailer->setMailer($mailer);

        $pastellMailer->send($templatedEmail);
        self::assertStringContainsString(
            'Subject: [Pastell] Mail de test',
            $mailerTransportTesting->getSentMessage()->getMessage()->toString()
        );
    }
}
