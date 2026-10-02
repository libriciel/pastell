<?php

declare(strict_types=1);

namespace Pastell\Tests\Updater\Major6\Minor0;

use Pastell\Updater\Major6\Minor0\RemoveExtensionsGraphe;
use PastellTestCase;

class RemoveExtensionsGrapheTest extends PastellTestCase
{
    private string $workspacePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspacePath = sys_get_temp_dir() . '/' . uniqid('workspace_', true);
        mkdir($this->workspacePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->workspacePath . '/extensions_graphe.dot');
        @unlink($this->workspacePath . '/extensions_graphe.jpg');
        @rmdir($this->workspacePath);
        parent::tearDown();
    }

    public function testUpdateRemovesFiles(): void
    {
        touch($this->workspacePath . '/extensions_graphe.dot');
        touch($this->workspacePath . '/extensions_graphe.jpg');

        new RemoveExtensionsGraphe($this->workspacePath)->update();

        static::assertFileDoesNotExist($this->workspacePath . '/extensions_graphe.dot');
        static::assertFileDoesNotExist($this->workspacePath . '/extensions_graphe.jpg');
    }

    public function testUpdateIsIdempotent(): void
    {
        new RemoveExtensionsGraphe($this->workspacePath)->update();
        new RemoveExtensionsGraphe($this->workspacePath)->update();

        static::assertFileDoesNotExist($this->workspacePath . '/extensions_graphe.dot');
        static::assertFileDoesNotExist($this->workspacePath . '/extensions_graphe.jpg');
    }
}
