<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Workspace;

use Exception;
use Pastell\Command\Workspace\WorkspaceOrphanCheck;
use Pastell\Service\Document\DocumentSize;
use PastellLogger;
use PastellTestCase;
use SQLQuery;
use Symfony\Component\Console\Tester\CommandTester;

class WorkspaceOrphanCheckTest extends PastellTestCase
{
    private const ORPHAN_ID_D = 'abcdef00-1111-2222-3333-444455556666';
    private const ORPHAN_ID_CE = 99999;

    private CommandTester $commandTester;
    private string $workspacePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspacePath = rtrim($this->getObjectInstancier()->getInstance('workspacePath'), '/');
        $command = new WorkspaceOrphanCheck(
            $this->getObjectInstancier()->getInstance(SQLQuery::class),
            $this->getObjectInstancier()->getInstance(PastellLogger::class),
            $this->getObjectInstancier()->getInstance(DocumentSize::class),
            $this->workspacePath
        );
        $this->commandTester = new CommandTester($command);
    }

    private function documentYml(string $id_d): string
    {
        return \sprintf('%s/%s/%s/%s.yml', $this->workspacePath, $id_d[0], $id_d[1], $id_d);
    }

    private function connecteurYml(int $id_ce): string
    {
        return \sprintf('%s/connecteur_%d.yml', $this->workspacePath, $id_ce);
    }

    private function writeFile(string $path): void
    {
        $dir = \dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, 'x');
    }

    private function createOrphanDocument(): void
    {
        $this->writeFile($this->documentYml(self::ORPHAN_ID_D));
    }

    private function createOrphanConnecteur(): void
    {
        $this->writeFile($this->connecteurYml(self::ORPHAN_ID_CE));
    }

    /**
     * @throws Exception
     */
    private function createExistingDocument(): void
    {
        static::getSQLQuery()->query('INSERT INTO document (id_d, type) VALUES (?, ?)', [self::ORPHAN_ID_D, 'test']);
        $this->writeFile($this->documentYml(self::ORPHAN_ID_D));
    }

    private function documentFileExists(): bool
    {
        return file_exists($this->documentYml(self::ORPHAN_ID_D));
    }

    private function connecteurFileExists(): bool
    {
        return file_exists($this->connecteurYml(self::ORPHAN_ID_CE));
    }

    public function testNoOrphan(): void
    {
        static::assertSame(0, $this->commandTester->execute([]));
        static::assertStringContainsString('Aucun fichier orphelin trouvé', $this->commandTester->getDisplay());
    }

    public function testFindsOrphanDocument(): void
    {
        $this->createOrphanDocument();

        static::assertSame(0, $this->commandTester->execute(['--dry-run' => true], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString(self::ORPHAN_ID_D, $output);
        static::assertStringContainsString('document', $output);
    }

    public function testFindsOrphanConnecteur(): void
    {
        $this->createOrphanConnecteur();

        static::assertSame(0, $this->commandTester->execute(['--dry-run' => true], ['interactive' => false]));

        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString((string) self::ORPHAN_ID_CE, $output);
        static::assertStringContainsString('connecteur', $output);
    }

    /**
     * @throws Exception
     */
    public function testKeepsExistingDocument(): void
    {
        $this->createExistingDocument();

        static::assertSame(0, $this->commandTester->execute([]));
        static::assertStringContainsString('Aucun fichier orphelin trouvé', $this->commandTester->getDisplay());
    }

    public function testDryRunKeepsFiles(): void
    {
        $this->createOrphanDocument();

        static::assertSame(0, $this->commandTester->execute(['--dry-run' => true], ['interactive' => false]));

        static::assertStringContainsString('auraient été supprimé', $this->commandTester->getDisplay());
        static::assertTrue($this->documentFileExists());
    }

    public function testForceDeletes(): void
    {
        $this->createOrphanDocument();
        $this->createOrphanConnecteur();

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        static::assertStringContainsString('supprimé', $this->commandTester->getDisplay());
        static::assertFalse($this->documentFileExists());
        static::assertFalse($this->connecteurFileExists());
    }

    public function testDeletionFailureReported(): void
    {
        $this->createOrphanDocument();
        chmod(\dirname($this->documentYml(self::ORPHAN_ID_D)), 0555);

        static::assertSame(1, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        static::assertStringContainsString('en échec', $this->commandTester->getDisplay());
        static::assertTrue($this->documentFileExists());
    }

    public function testConfirmDeletes(): void
    {
        $this->createOrphanDocument();

        $this->commandTester->setInputs(['yes']);
        static::assertSame(0, $this->commandTester->execute([]));

        static::assertFalse($this->documentFileExists());
    }

    public function testDeclineKeeps(): void
    {
        $this->createOrphanDocument();

        $this->commandTester->setInputs(['no']);
        static::assertSame(1, $this->commandTester->execute([]));

        static::assertTrue($this->documentFileExists());
    }

    public function testDeletesRelatedFiles(): void
    {
        $this->createOrphanDocument();
        $relatedFile = \sprintf('%s_fichier_0', $this->documentYml(self::ORPHAN_ID_D));
        $this->writeFile($relatedFile);

        static::assertSame(0, $this->commandTester->execute(['--force' => true], ['interactive' => false]));

        static::assertStringContainsString('Total : 1 élément(s)', $this->commandTester->getDisplay());
        static::assertFileDoesNotExist($relatedFile);
    }

    public function testTypeFilter(): void
    {
        $this->createOrphanDocument();
        $this->createOrphanConnecteur();

        static::assertSame(0, $this->commandTester->execute(
            ['--type' => 'connecteur', '--force' => true],
            ['interactive' => false]
        ));

        static::assertTrue($this->documentFileExists());
        static::assertFalse($this->connecteurFileExists());
    }

    public function testUnknownTypeFails(): void
    {
        static::assertSame(1, $this->commandTester->execute(['--type' => 'inexistant']));
        static::assertStringContainsString('Type inconnu', $this->commandTester->getDisplay());
    }
}
