<?php

declare(strict_types=1);

namespace Pastell\Command\Journal;

use Exception;
use JournalHistoriqueSQL;
use Pastell\Service\Journal\JournalHistoriqueService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteHistory extends Command
{
    private $journalHistoriqueSQL;
    private $journalHistoriqueService;

    public function __construct(JournalHistoriqueSQL $journalHistoriqueSQL, JournalHistoriqueService $journalHistoriqueService)
    {
        parent::__construct();
        $this->journalHistoriqueSQL = $journalHistoriqueSQL;
        $this->journalHistoriqueService = $journalHistoriqueService;
    }

    protected function configure(): void
    {
        $this
            ->setName('app:journal:delete-history')
            ->setDescription('Vide la table journal_historique')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Truncate the table journal_historique without asking'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        $force = $input->getOption('force') ?? false;
        $count = $this->journalHistoriqueSQL->getCount();

        if (! $force) {
            $confirm = $io->confirm(
                "Are you sure you want to delete the $count row of journal_historique ?",
                false
            );
        } else {
            $confirm = true;
        }
        if (! $confirm) {
            $io->note('Abort the deletion of the content of table journal_historique');
            return 1;
        }
        $this->journalHistoriqueService->truncate();

        $io->success("$count row(s) deleted");
        return 0;
    }
}
