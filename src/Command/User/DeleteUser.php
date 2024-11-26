<?php

declare(strict_types=1);

namespace Pastell\Command\User;

use Exception;
use Pastell\Service\Utilisateur\UtilisateurDeletionService;
use Pastell\Command\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UtilisateurSQL;

#[AsCommand(
    name: 'app:user:delete-user',
    description: 'Delete user'
)]
final class DeleteUser extends BaseCommand
{
    public function __construct(
        private readonly UtilisateurDeletionService $utilisateurDeletionService,
        private readonly UtilisateurSQL $utilisateurSQL,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('userLogin', InputArgument::REQUIRED, 'User Login')
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userLogin = $input->getArgument('userLogin');

        $userInfo = $this->utilisateurSQL->getInfoByLogin($userLogin);
        if (! $userInfo) {
            $this->getIO()->writeln(sprintf(
                "L'utilisateur %s n'existe pas",
                $userLogin,
            ));
            return Command::FAILURE;
        }
        $answer = $this->getIO()->ask(sprintf(
            "Etes-vous sûr de vouloir supprimer l'utilisateur %s id_u=%s (o/N) ?",
            $userLogin,
            $userInfo['id_u'],
        ));
        if ($answer !== 'o') {
            $this->getIO()->writeln(sprintf(
                "L'utilisateur %s n'a pas été supprimé",
                $userLogin,
            ));
            return Command::SUCCESS;
        }

        $this->utilisateurDeletionService->delete($userInfo['id_u']);

        $this->getIO()->writeln(sprintf(
            "L'utilisateur %s a été supprimé",
            $userLogin,
        ));
        return Command::SUCCESS;
    }
}
