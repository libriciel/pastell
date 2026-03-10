<?php

declare(strict_types=1);

namespace Pastell\Storage;

class StorageInterfaceFake implements StorageInterface
{
    public static array $memory = [];

    public function write(string $id, string $content): string
    {
        self::$memory[$id] = $content;
        return $id;
    }

    public function read(string $id): string
    {
        if (isset(self::$memory[$id])) {
            return self::$memory[$id];
        }
        return 'Objet inexistant';
    }
}
