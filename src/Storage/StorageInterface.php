<?php

declare(strict_types=1);

namespace Pastell\Storage;

interface StorageInterface
{
    public function write(string $id, string $content): string;

    public function read(string $id): string;

    public function delete(string $id): string;
}
