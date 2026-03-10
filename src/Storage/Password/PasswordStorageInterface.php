<?php

declare(strict_types=1);

namespace Pastell\Storage\Password;

interface PasswordStorageInterface
{
    public function write(string $id, string $content): string;

    public function read(string $id): string;

    public function delete(string $id): string;
}
