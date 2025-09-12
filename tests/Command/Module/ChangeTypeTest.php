<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Module;

use DocumentSQL;
use FluxEntiteSQL;
use Pastell\Command\Module\ChangeType;
use PastellTestCase;
use RoleDroit;
use RoleSQL;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ChangeTypeTest extends PastellTestCase
{
    private CommandTester $tester;
    private DocumentSQL $documentSQL;
    private RoleSQL $roleSQL;
    private FluxEntiteSQL $fluxEntiteSQL;

    protected function setUp(): void
    {
        parent::setUp();

        $command = $this->getObjectInstancier()->getInstance(ChangeType::class);
        $this->tester = new CommandTester($command);
        $this->documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $this->roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $this->fluxEntiteSQL = $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class);
    }

    public function testChangeTypeMovesDocuments(): void
    {
        $id_d = $this->createDocument('test')['id_d'];
        self::assertTrue($this->containsId($this->documentSQL->getAllByType('test'), $id_d));

        $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class);

        $this->tester->setInputs(['o']);
        $status = $this->tester->execute([
            'ancien_type' => 'test',
            'nouveau_type' => 'test-new',
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertFalse($this->containsId($this->documentSQL->getAllByType('test'), $id_d));
        self::assertTrue($this->containsId($this->documentSQL->getAllByType('test-new'), $id_d));
        self::assertStringContainsString('Le type (flux) des documents a été mis à jour.', $this->tester->getDisplay());

        $roleDroit = $this->getObjectInstancier()->getInstance(RoleDroit::class);
        $droit = $this->roleSQL->getDroit($roleDroit->getAllDroit(), 'admin');
        self::assertTrue($droit['test-new:lecture']);
        self::assertTrue($droit['test-new:edition']);
        self::assertCount(1, $this->fluxEntiteSQL->getAssociations('test-new'));
        self::assertCount(0, $this->fluxEntiteSQL->getAssociations('test'));
    }

    public function testReturnsInvalidWhenNoDocumentsOfOldType(): void
    {
        $status = $this->tester->execute([
            'ancien_type' => 'type-inexistant',
            'nouveau_type' => 'nouveau-type',
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString(
            "Il n'y a pas de document de type type-inexistant",
            $this->tester->getDisplay()
        );
    }

    private function containsId(?array $rows, int|string $id): bool
    {
        if (!$rows) {
            return false;
        }
        return array_any($rows, static fn($row) => (string)($row['id_d'] ?? '') === (string)$id);
    }
}
