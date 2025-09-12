<?php

declare(strict_types=1);

namespace Pastell\Command\Journal;

use JournalManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:journal:archive-to-history',
    description: 'Archive les entrées du journal de plus de N mois dans journal_historique puis les supprime du journal.',
)]
final class ArchiveToHistory extends Command
{
    public function __construct(
        private readonly Logger $logger,
        private readonly JournalManager $journalManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->pushHandler(new StreamHandler('php://stdout'));
        $io = new SymfonyStyle($input, $output);

        try {
            if ($this->journalManager->purgeToHistorique()) {
                $io->success('Purge vers historique effectuée avec succès.');
                return Command::SUCCESS;
            }
            $io->error('Purge vers historique non effectuée.');
        } catch (Throwable $e) {
            $io->error('Erreur pendant la purge: ' . $e->getMessage());
        }
        return Command::FAILURE;
    }
}
