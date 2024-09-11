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

    /**
     * @throws VaultIdNotFoundException
     */
    public function read(string $id): string
    {
        if (isset(self::$memory[$id])) {
            return self::$memory[$id];
        }
        throw new VaultIdNotFoundException();
    }

    /**
     * @throws VaultIdNotFoundException
     */
    public function delete(string $id): string
    {
        $this->read($id);
        unset(self::$memory[$id]);
        return "$id détruit";
    }
}
