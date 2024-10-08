<?php

declare(strict_types=1);

namespace Pastell\Command\System;

use Flow\FileOpenException;
use Flow\Uploader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:system:prune-upload-chunks',
    description: 'Delete old upload chunks',
)]
final class PruneUploadChunksCommand extends Command
{
    public function __construct(
        private readonly string $uploadChunkDirectory,
    ) {
        parent::__construct();
    }

    /**
     * @throws FileOpenException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Uploader::pruneChunks($this->uploadChunkDirectory);
        return Command::SUCCESS;
    }
}
