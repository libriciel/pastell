<?php

declare(strict_types=1);

namespace Pastell\Command\Database;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:database:optimize',
    description: 'Optimise les tables worker, job_queue et journal (équivalent du script CLI).',
)]
final class DatabaseOptimize extends Command
{
    public function __construct(
        private readonly \SQLQuery $sqlQuery,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $queries = [
            'OPTIMIZE TABLE worker',
            'OPTIMIZE TABLE job_queue',
            'OPTIMIZE TABLE journal',
        ];

        try {
            foreach ($queries as $q) {
                $io->write($q . ':...');
                $this->sqlQuery->query($q);
                $io->writeln('[OK]');
            }
        } catch (Throwable $e) {
            $io->writeln('[KO]');
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
