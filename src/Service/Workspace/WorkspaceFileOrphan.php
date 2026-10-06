<?php

declare(strict_types=1);

namespace Pastell\Service\Workspace;

final class WorkspaceFileOrphan
{
    /**
     * @param list<string> $files yml file and its attached files
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $ymlPath,
        public readonly array $files,
        public readonly int $size,
    ) {
    }
}
