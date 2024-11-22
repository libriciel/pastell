<?php

declare(strict_types=1);

namespace Pastell\Command\User;

use AdminControler;
use Exception;
use Pastell\Command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user:refresh-role-admin',
    description: 'Refresh role admin'
)]
final class RefreshRoleAdmin extends BaseCommand
{
    public function __construct(
        private readonly AdminControler $adminControler,
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->adminControler->fixDroit();
        $this->getIO()->writeln(
            'Mise à jour du role admin et fixe des droits sur les entités avec succés',
        );
        return Command::SUCCESS;
    }
}
