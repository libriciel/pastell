<?php

declare(strict_types=1);

namespace Pastell\Mailer;

use ConfigurationSQL;
use Pastell\Exception\NotificationException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;

readonly class AdminMailer
{
    public function __construct(
        private Mailer $mailer,
        private ConfigurationSQL $configurationSQL,
        private string $plateforme_mail,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function notify(string $subject, string $htmlTemplate, array $context, array $to = []): void
    {
        $templatedEmail = $this->createEmail($subject, $to)
            ->htmlTemplate($htmlTemplate)
            ->context($context);
        $this->mailer->send($templatedEmail);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function notifyText(string $subject, string $body, array $to = []): void
    {
        $templatedEmail = $this->createEmail($subject, $to)->text($body);
        $this->mailer->send($templatedEmail);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function notifyFromException(NotificationException $exception): void
    {
        $htmlTemplate = $exception->getHtmlTemplate();
        if ($htmlTemplate === null) {
            $this->notifyText($exception->getSubject(), $exception->getMessage());
            return;
        }
        $this->notify($exception->getSubject(), $htmlTemplate, $exception->getContext());
    }

    private function createEmail(string $subject, array $to): TemplatedEmail
    {
        $recipients = $to ?: $this->configurationSQL->getAdminEmails();
        return new TemplatedEmail()
            ->from(new Address($this->plateforme_mail, $this->configurationSQL->getLibellePlateformeMail()))
            ->to(...$recipients)
            ->subject($subject);
    }
}
