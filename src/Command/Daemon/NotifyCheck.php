<?php

declare(strict_types=1);

namespace Pastell\Command\Daemon;

use ConfigurationSQL;
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
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:daemon:notify-check',
    description: 'Notify ADMIN_EMAIL when daemon check is KO and notify each admin_emails when a daemon is KO',
)]
final class NotifyCheck extends BaseCommand
{
    public function __construct(
        private readonly ObjectInstancier $objectInstancier,
        private readonly DaemonCheck $daemonCheck,
        private readonly Mailer $pastellMailer,
        private readonly DaemonManager $daemonManager,
        private readonly ConfigurationSQL $configurationSQL,
        private readonly string $plateforme_mail,
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
        $error_items = [];

        $libelle_plateforme_mail = $this->configurationSQL->getLibellePlateformeMail();
        foreach ($daemonHealth->getDetails() ?? [] as $item) {
            if (!$item->isSuccess()) {
                $context       = $item->getContext();
                $denomination  =  $context['denomination_entite'];
                $id_daemon      = $context['id_daemon'];
                $destinataires = $this->daemonManager->getAdminEmails($id_daemon);
                $body = "[KO] Tâches automatiques du site $site — $denomination [entité #{$context['id_e']}]" .
                    "— daemon #$id_daemon : {$item->result}";
                $templatedEmail = new TemplatedEmail()
                    ->from(new Address($this->plateforme_mail, $libelle_plateforme_mail))
                    ->to(...$destinataires)
                    ->subject("[Pastell] Alerte tâches automatiques - {$denomination}")
                    ->text($body);
                $this->pastellMailer->send($templatedEmail);
                if ($this->getIO()->isVerbose()) {
                    $this->getIO()->writeln($body);
                }

                $error_items[] = $item;
            }
        }

        if (!empty($error_items)) {
            $body = "Résumé des tâches automatiques en erreur sur le site {$site} :\n\n";

            foreach ($error_items as $error_item) {
                $context = $error_item->getContext();
                $body .= "- {$context['denomination_entite']} [entité #{$context['id_e']}] — daemon #{$context['id_daemon']} : {$error_item->result}\n";
            }

            $synthesisEmail = new TemplatedEmail()
                ->from(new Address($this->plateforme_mail, $libelle_plateforme_mail))
                ->to(...$this->configurationSQL->getAdminEmails())
                ->subject('[Pastell] Alerte tâches automatiques - Synthèse')
                ->text($body);

            $this->pastellMailer->send($synthesisEmail);
        }
        return 2;
    }
}
