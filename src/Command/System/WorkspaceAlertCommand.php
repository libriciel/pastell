<?php

declare(strict_types=1);

namespace Pastell\Command\System;

use ConfigurationSQL;
use FreeSpace;
use Pastell\Command\BaseCommand;
use Pastell\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:system:workspace-alert',
    description: "Envoie une alerte mail aux administrateurs si le taux d'occupation du workspace dépasse le seuil configuré.",
)]
final class WorkspaceAlertCommand extends BaseCommand
{
    public function __construct(
        private readonly FreeSpace $freeSpace,
        private readonly ConfigurationSQL $configurationSQL,
        private readonly Mailer $mailer,
        private readonly string $plateforme_mail,
    ) {
        parent::__construct();
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $threshold = $this->configurationSQL->getWorkspaceAlertThreshold();
        $usagePercent = $this->freeSpace->getUsagePercent(WORKSPACE_PATH);

        if ($usagePercent < $threshold) {
            $this->getIO()->success(
                \sprintf(
                    "Taux d'occupation : %.2f%% (seuil configuré : %d%%).",
                    $usagePercent,
                    $threshold
                )
            );
            return self::SUCCESS;
        }

        $adminEmails = $this->configurationSQL->getAdminEmails();
        $libelle = $this->configurationSQL->getLibellePlateformeMail();
        $body = \sprintf(
            "Le taux d'occupation du workspace est de %.2f%% (seuil configuré : %d%%).",
            $usagePercent,
            $threshold
        );

        $this->mailer->send(
            new TemplatedEmail()
                ->from(new Address($this->plateforme_mail, $libelle))
                ->to(...$adminEmails)
                ->subject("[Pastell] Alerte taux d'occupation du workspace")
                ->text($body)
        );

        $this->getIO()->warning($body);
        return self::SUCCESS;
    }
}
