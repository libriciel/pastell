<?php

use Pastell\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;

class NotificationMail
{
    public function __construct(
        private readonly Notification $notification,
        private readonly Mailer $mailer,
        private readonly Journal $journal,
        private readonly NotificationDigestSQL $notificationDigestSQL,
        private readonly EntiteSQL $entiteSQL,
        private readonly DocumentSQL $documentSQL,
        private readonly ConfigurationSQL $configurationSQL,
        private readonly string $site_base,
        private readonly string $plateforme_mail,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function notify($id_e, $id_d, $action, $type, $message, array $attachment = []): void
    {
        $lesEmails = $this->notification->getAllInfo($id_e, $type, $action);
        foreach ($lesEmails as $mail_info) {
            if ($mail_info['daily_digest']) {
                $this->register($mail_info['email'], $id_e, $id_d, $action, $type, $message);
            } else {
                $this->sendMail($mail_info['email'], $id_e, $id_d, $action, $type, $message, $attachment);
            }
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendMail($mail, $id_e, $id_d, $action, $type, $message, array $attachment = []): void
    {
        $entityInfo = $this->entiteSQL->getInfo($id_e);
        $documentInfo = $this->documentSQL->getInfo($id_d);

        $url = sprintf('%s/Document/detail?id_d=%s&id_e=%d', $this->site_base, $id_d, $id_e);
        $libelle_plateforme_mail = $this->configurationSQL->getLibellePlateformeMail();
        $templatedEmail = new TemplatedEmail()
            ->from(new Address($this->plateforme_mail, $libelle_plateforme_mail))
            ->to($mail)
            ->subject('[Pastell] Notification')
            ->htmlTemplate('notification.html.twig')
            ->context([
                'message' => $message,
                'entityName' => $entityInfo['denomination'],
                'documentTitle' => $documentInfo['titre'] ?? $id_d,
                'url' => $url,
                'action' => $action,
                'type' => $type,
                'SITE_BASE' => $this->site_base,
            ]);
        foreach ($attachment as $filename => $filepath) {
            $templatedEmail->attachFromPath($filepath, $filename);
        }
        $this->mailer->send($templatedEmail);
        $this->journal->addActionAutomatique(
            Journal::NOTIFICATION,
            $id_e,
            $id_d,
            $action,
            "notification envoyée à $mail"
        );
    }

    private function register($mail, $id_e, $id_d, $action, $type, $message): void
    {
        $this->notificationDigestSQL->add($mail, $id_e, $id_d, $action, $type, $message);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendDailyDigest(): void
    {
        $all = $this->notificationDigestSQL->getAll();
        foreach ($all as $email => $all_info) {
            $libelle_plateforme_mail = $this->configurationSQL->getLibellePlateformeMail();
            $templatedEmail = new TemplatedEmail()
                ->from(new Address($this->plateforme_mail, $libelle_plateforme_mail))
                ->to($email)
                ->subject('[Pastell] Notification (résumé journalier)')
                ->htmlTemplate('notification-daily-digest.html.twig')
                ->context(['info' => $all_info, 'SITE_BASE' => $this->site_base]);
            $this->mailer->send($templatedEmail);
            $this->journal->addActionAutomatique(
                Journal::NOTIFICATION,
                0,
                0,
                false,
                "Résumé des notifications envoyée à $email"
            );
            foreach ($all_info as $info) {
                $this->notificationDigestSQL->delete($info['id_nd']);
            }
        }
    }
}
