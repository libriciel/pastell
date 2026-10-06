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
        $sql = "SELECT id_j FROM journal WHERE preuve != ''";
        $proofIdList = $this->journalSQL->query($sql);

        foreach ($proofIdList as $proofId) {
            $sql = "SELECT preuve FROM journal WHERE id_j = ?";
            $proof = $this->journalSQL->query($sql, $proofId['id_j'])[0];
            $this->journalEntryService->saveProof($proofId['id_j'], $proof['preuve']);
            $sql = "UPDATE journal SET preuve = '' WHERE id_j = ?";
            $this->journalSQL->query($sql, $proofId['id_j']);
        }
        return Command::SUCCESS;
    }
}
