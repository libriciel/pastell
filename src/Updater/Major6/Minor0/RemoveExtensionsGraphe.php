<?php

declare(strict_types=1);

namespace Pastell\Updater\Major6\Minor0;

use Pastell\Updater\Version;
use PastellLogger;

final class RemoveExtensionsGraphe implements Version
{
    private const array OBSOLETE_FILES = [
        'extensions_graphe.dot',
        'extensions_graphe.jpg',
    ];

    public function __construct(
        private readonly string $workspacePath,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    public function update(): void
    {
        $this->logger?->info('Start');

        foreach (self::OBSOLETE_FILES as $file) {
            $path = $this->workspacePath . '/' . $file;
            if (!file_exists($path)) {
                continue;
            }
            if (unlink($path)) {
                $this->logger?->info(\sprintf('Removed obsolete file `%s`', $path));
            } else {
                $this->logger?->warning(\sprintf('Unable to remove obsolete file `%s`', $path));
            }
        }
    }
}
