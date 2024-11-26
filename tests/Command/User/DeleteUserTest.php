<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\User;

use ConflictException;
use Pastell\Command\User\DeleteUser;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UtilisateurDeletionService;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use UnrecoverableException;
use UtilisateurSQL;

class DeleteUserTest extends PastellTestCase
{
    private CommandTester $commandTester;
    private UserCreationService $userCreationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $command = new DeleteUser(
            $this->getObjectInstancier()->getInstance(UtilisateurDeletionService::class),
            $this->getObjectInstancier()->getInstance(UtilisateurSQL::class),
        );
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testDeleteUser(): void
    {
        $this->userCreationService->create(
            'LoginNewUser',
            'a@a.invalid',
            'a',
            'a',
            0,
            'passwordpasswordpassword'
        );
        $this->commandTester->setInputs(['o']);
        $this->commandTester->execute(['userLogin' => 'LoginNewUser']);
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString("L'utilisateur LoginNewUser a été supprimé", $output);
    }

    public function testDeleteUserNotExist(): void
    {
        $this->commandTester->setInputs(['o']);
        $this->commandTester->execute(['userLogin' => 'LoginNewUser']);
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString("L'utilisateur LoginNewUser n'existe pas", $output);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testCommandNoConfirmation(): void
    {
        $this->userCreationService->create(
            'LoginNewUser',
            'a@a.invalid',
            'a',
            'a',
            0,
            'passwordpasswordpassword'
        );
        $this->commandTester->setInputs(['No']);
        $this->commandTester->execute(['userLogin' => 'LoginNewUser']);
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString("L'utilisateur LoginNewUser n'a pas été supprimé", $output);
    }
}
