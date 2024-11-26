<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\User;

use AdminControler;
use Pastell\Command\User\RefreshRoleAdmin;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshRoleAdminTest extends PastellTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $command = new RefreshRoleAdmin(
            $this->getObjectInstancier()->getInstance(AdminControler::class),
        );
        $this->commandTester = new CommandTester($command);
    }

    public function testRefreshRoleAdmin(): void
    {
        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString(
            'Mise à jour du role admin et fixe des droits sur les entités avec succés',
            $output
        );
    }
}
