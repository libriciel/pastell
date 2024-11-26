<?php

declare(strict_types=1);

namespace Pastell\Command\User;

use Exception;
use Pastell\Command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UtilisateurSQL;

#[AsCommand(
    name: 'app:user:update-user-email',
    description: 'Update user email',
)]
final class UpdateUserEmail extends BaseCommand
{
    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('userLogin', InputArgument::REQUIRED, 'User Login')
            ->addArgument('userNewEmail', InputArgument::REQUIRED, 'User new Email')
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userLogin = $input->getArgument('userLogin');
        $userNewEmail = $input->getArgument('userNewEmail');

        $userInfo = $this->utilisateurSQL->getInfoByLogin($userLogin);
        if (! $userInfo) {
            $this->getIO()->writeln(sprintf(
                "L'utilisateur %s n'existe pas",
                $userLogin,
            ));
            return Command::FAILURE;
        }

        $this->utilisateurSQL->setEmail($userInfo['id_u'], $userNewEmail);
        $this->getIO()->writeln(sprintf(
            "L'Email de l'utilisateur %s a été modifié : %s",
            $userLogin,
            $userNewEmail
        ));
        return Command::SUCCESS;
    }
}
