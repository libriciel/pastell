<?php

declare(strict_types=1);

namespace Pastell\Command\Connector;

use FluxEntiteSQL;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:connector:remove-global-auth',
    description: "Supprime l'association globale avec le connecteur cas-authentification.",
)]
final class RemoveGlobalAuth extends Command
{
    public function __construct(
        private readonly FluxEntiteSQL $fluxEntiteSQL,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $status = Command::SUCCESS;

        $connecteurInfo = $this->fluxEntiteSQL->getConnecteur(0, 'global', 'authentification');

        if ($connecteurInfo) {
            $this->fluxEntiteSQL->deleteConnecteur(0, 'global', 'authentification');
            $io->success("L'association globale avec le connecteur cas-authentification a été supprimée.");
        } else {
            $io->warning("Il n'y a pas de connecteur cas-authentification associé dans les connecteurs globaux.");
            $status = Command::INVALID;
        }
        return $status;
    }
}
