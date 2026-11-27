<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentSQL;

final class DocumentInfoService
{
    public function __construct(
        private readonly DocumentSQL $documentSQL,
    ) {
    }

    public function getInfo(string $id_d): ?array
    {
        return $this->documentSQL->getInfo($id_d) ?: null;
    }

    public function getType(string $id_d): ?string
    {
        return $this->getInfo($id_d)['type'] ?? null;
    }
}
