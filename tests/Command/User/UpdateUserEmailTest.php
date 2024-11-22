<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\User;

use ConflictException;
use Pastell\Command\User\UpdateUserEmail;
use Pastell\Service\Utilisateur\UserCreationService;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use UnrecoverableException;
use UtilisateurSQL;

class UpdateUserEmailTest extends PastellTestCase
{
    private CommandTester $commandTester;
    private UserCreationService $userCreationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userCreationService = $this->getObjectInstancier()->getInstance(UserCreationService::class);
        $command = new UpdateUserEmail(
            $this->getObjectInstancier()->getInstance(UtilisateurSQL::class),
        );
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testUpdateUserEmail(): void
    {
        $this->userCreationService->create(
            'LoginNewUser',
            'a@a.fr',
            'a',
            'a',
            0,
            'passwordpasswordpassword'
        );
        $this->commandTester->execute([
            'userLogin' => 'LoginNewUser',
            'userNewEmail' => 'example@libriciel.net',
            ]);
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString(
            "L'Email de l'utilisateur LoginNewUser a été modifié : example@libriciel.net",
            $output
        );
    }
}
