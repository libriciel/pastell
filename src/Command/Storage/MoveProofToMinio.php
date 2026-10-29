<?php

declare(strict_types=1);

namespace Pastell\Command\Storage;

use JournalSQL;
use Pastell\Service\Journal\JournalEntryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MoveProofToMinio extends Command
{
    public function __construct(
        private readonly JournalSQL $journalSQL,
        private readonly JournalEntryService $journalEntryService,
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->setName('app:storage:minio:move-proof-to-minio')
            ->setDescription('Move all existing proof from DB to MinIO (object storage)')
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->journalSQL->getIdListWithProof() as $id_j) {
            $this->journalEntryService->saveProof($id_j, $this->journalSQL->getProof($id_j));
            $this->journalSQL->clearProof($id_j);
        }
        return Command::SUCCESS;
    }
}
