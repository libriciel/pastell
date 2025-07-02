<?php

declare(strict_types=1);

namespace Pastell\Command\Daemon;

use DaemonManager;
use ObjectInstancier;
use Pastell\Command\BaseCommand;
use Pastell\Mailer\Mailer;
use Pastell\System\Check\DaemonCheck;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'app:daemon:notify-check',
    description: 'Notify ADMIN_EMAIL when daemon check is KO and notify each daemon admin email when a daemon is KO',
)]
final class NotifyCheck extends BaseCommand
{
    public function __construct(
        private readonly ObjectInstancier $objectInstancier,
        private readonly DaemonCheck $daemonCheck,
        private readonly Mailer $pastellMailer,
        private readonly DaemonManager $daemonManager
    ) {
        parent::__construct();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws \UnrecoverableException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $daemonHealth = $this->daemonCheck->check()[0];
        if ($daemonHealth->isSuccess()) {
            if ($this->getIO()->isVerbose()) {
                $this->getIO()->writeln(
                    \sprintf(
                        '[OK] %s: %s',
                        $daemonHealth->label,
                        $daemonHealth->result
                    )
                );
            }
            return 0;
        }

        $site = $this->objectInstancier->getInstance('site_base');
        $errors = [];



        foreach ($daemonHealth->getDetails() ?? [] as $item) {
            if (!$item->isSuccess()) {
                $context = $item->getContext();
                $destinataires = $this->daemonManager->getAdminEmails($context['id_daemon']);
                $message = "[KO] Tâches automatiques du site {$site} : {$item->result}";
                $denomination = $context['denomination_entite'];

                $templatedEmail = (new TemplatedEmail())
                    ->to(...$destinataires)
                    ->subject("[PASTELL] Alerte tâches automatiques - {$denomination}")
                    ->text($message);

                $this->pastellMailer->send($templatedEmail);

                if ($this->getIO()->isVerbose()) {
                    $this->getIO()->writeln($message);
                }

                $errors[] = [
                    'daemon' => $denomination,
                    'message' => $item->result,
                ];
            }
        }

        if (!empty($errors)) {
            $body = "Résumé des tâches automatiques en erreur sur le site {$site} :\n\n";

            foreach ($errors as $erreur) {
                $body .= "- {$erreur['daemon']} : {$erreur['message']}\n";
            }

            $admin_email = $this->objectInstancier->getInstance('admin_email');
            $synthesisEmail = (new TemplatedEmail())
                ->to(...$admin_email)
                ->subject('[PASTELL] Alerte tâches automatiques - Synthèse')
                ->text($body);

            $this->pastellMailer->send($synthesisEmail);
        }
        return 2;
    }
}
