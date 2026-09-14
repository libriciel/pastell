<?php

namespace Pastell\Tests\Command\Connector;

use ConnecteurEntiteSQL;
use Pastell\Command\Connector\DeleteByType;
use Pastell\Service\Connecteur\ConnecteurDeletionService;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DeleteByTypeTest extends PastellTestCase
{
    /**
     * @var CommandTester
     */
    private $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnector('test', 'Test global', 0);
        $command = new DeleteByType(
            $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class),
            $this->getObjectInstancier()->getInstance(ConnecteurDeletionService::class)
        );
        $this->commandTester = new CommandTester($command);
    }

    private function executeCommand(string $type, string $scope, string $confirm): int
    {
        $this->commandTester->setInputs([$confirm]);
        return $this->commandTester->execute(
            [
                'type' => $type,
                'scope' => $scope,
            ]
        );
    }

    public static function commandArgumentsProvider(): iterable
    {
        yield ['test', 'all', 'yes', 3];
        yield ['test', 'global', 'yes', 1];
        yield ['test', 'entity', 'yes', 2];
    }

    /**
     * @dataProvider commandArgumentsProvider
     */
    public function testCommand(string $type, string $scope, string $confirm, int $numberOfConnectors): void
    {
        $this->assertSame(
            0,
            $this->executeCommand($type, $scope, $confirm)
        );

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('0/' . $numberOfConnectors, $output);
        $this->assertStringContainsString($numberOfConnectors . '/' . $numberOfConnectors, $output);

        $this->executeCommand($type, $scope, $confirm);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('There is no connectors to delete', $output);
    }

    public function testCommandUnknownScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid scope');
        $this->executeCommand('test', 'unknown', 'yes');
    }

    public function testCommandNoConfirmation(): void
    {
        $this->assertSame(
            1,
            $this->executeCommand('test', 'all', 'no')
        );
    }
}
