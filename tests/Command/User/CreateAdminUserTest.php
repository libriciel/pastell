<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\User;

use Pastell\Command\User\CreateAdminUser;
use Pastell\Service\TokenGenerator;
use Pastell\Service\Utilisateur\UserCreationService;
use PastellTestCase;
use RoleUtilisateur;
use Symfony\Component\Console\Tester\CommandTester;
use UtilisateurSQL;

class CreateAdminUserTest extends PastellTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $command = new CreateAdminUser(
            $this->getObjectInstancier()->getInstance(UserCreationService::class),
            $this->getObjectInstancier()->getInstance(UtilisateurSQL::class),
            $this->getObjectInstancier()->getInstance(TokenGenerator::class),
            $this->getObjectInstancier()->getInstance(RoleUtilisateur::class),
        );
        $this->commandTester = new CommandTester($command);
    }

    public function testCreateAdminUserOK(): void
    {
        $this->commandTester->execute([
            'userLogin' => 'LoginNewUser',
            'userEmail' => 'example@libriciel.invalid',
        ]);
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString("Création de l'utilisateur LoginNewUser avec mot de passe :", $output);
    }

    public function testCreateAdminUserExist(): void
    {
        $this->commandTester->execute([
            'userLogin' => 'admin',
            'userEmail' => 'example@libriciel.invalid',
        ]);
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString("L'utilisateur admin existe déjà", $output);
    }

    public function testCreateAdminUserWithWrongEmail(): void
    {
        $this->commandTester->execute([
            'userLogin' => 'LoginNewUser2',
            'userEmail' => 'example',
        ]);
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString(
            "Erreur lors de la création de l'utilisateur LoginNewUser2 :  Votre adresse email ne semble pas valide",
            $output
        );
    }
}
