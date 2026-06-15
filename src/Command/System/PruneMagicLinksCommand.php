<?php

declare(strict_types=1);

namespace Pastell\Command\System;

use Pastell\Service\MagicLink\MagicLinkService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:system:prune-magic-links',
    description: 'Supprime les utilisateurs temporaires des accès support révoqués ou expirés',
)]
final class PruneMagicLinksCommand extends Command
{
    public function __construct(
        private readonly MagicLinkService $magicLinkService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->magicLinkService->pruneExpired();
        $output->writeln("$count accès support nettoyé(s)");
        return Command::SUCCESS;
    }
}
