<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

final class DocumentFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $name,
    ) {
    }
}
