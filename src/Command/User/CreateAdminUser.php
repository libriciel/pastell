<?php

declare(strict_types=1);

namespace Pastell\Command\User;

use Pastell\Service\Utilisateur\UserCreationService;
use Exception;
use RoleUtilisateur;
use Pastell\Command\BaseCommand;
use Pastell\Service\TokenGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UtilisateurSQL;

#[AsCommand(
    name: 'app:user:create-admin-user',
    description: 'Create user with role admin'
)]
final class CreateAdminUser extends BaseCommand
{
    public function __construct(
        private readonly UserCreationService $userCreationService,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly TokenGenerator $tokenGenerator,
        private readonly RoleUtilisateur $roleUtilisateur,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('userLogin', InputArgument::REQUIRED, 'User Login')
            ->addArgument('userEmail', InputArgument::REQUIRED, 'User Email')
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userLogin = $input->getArgument('userLogin');
        $userEmail = $input->getArgument('userEmail');

        $userInfo = $this->utilisateurSQL->getInfoByLogin($userLogin);
        if ($userInfo) {
            $this->getIO()->writeln(sprintf(
                "L'utilisateur %s existe déjà",
                $userLogin,
            ));
            return Command::FAILURE;
        }

        $password = $this->tokenGenerator->generate();
        try {
            $id_u = $this->userCreationService->create(
                $userLogin,
                $userEmail,
                $userLogin,
                $userLogin,
                0,
                $password
            );
        } catch (Exception $e) {
            $this->getIO()->writeln(sprintf(
                "Erreur lors de la création de l'utilisateur %s :  %s",
                $userLogin,
                $e->getMessage()
            ));
            return Command::FAILURE;
        }
        $this->roleUtilisateur->addRole($id_u, 'admin', 0);

        $this->getIO()->writeln(sprintf(
            "Création de l'utilisateur %s avec mot de passe : %s",
            $userLogin,
            $password
        ));
        return Command::SUCCESS;
    }
}
