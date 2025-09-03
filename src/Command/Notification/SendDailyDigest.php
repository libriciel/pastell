<?php

declare(strict_types=1);

namespace Pastell\Command\Notification;

use NotificationMail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:notification:daily-digest',
    description: 'Envoie le récapitulatif quotidien par e-mail.',
)]
final class SendDailyDigest extends Command
{
    public function __construct(
        private readonly NotificationMail $notificationMail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->notificationMail->sendDailyDigest();
            $io->success('Récapitulatif quotidien envoyé.');
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Échec de l’envoi : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
