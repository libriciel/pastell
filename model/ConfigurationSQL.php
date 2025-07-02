<?php

declare(strict_types=1);

use Pastell\Exception\ConfigurationNotFoundException;

class ConfigurationSQL extends SQL
{
    public function setConfiguration(string $config_key, string $config_value, int $id_e = 0): void
    {
        $sql = <<<SQL
INSERT INTO configuration (`config_key`, `config_value`, `id_e`)
VALUES (?, ?, ?)
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`)
SQL;
        $this->query($sql, [$config_key, $config_value, $id_e]);
    }

    public function getConfiguration(string $config_key, int $id_e = 0): string
    {
        $sql = <<<SQL
SELECT config_value FROM configuration WHERE config_key = ? AND id_e = ? LIMIT 1;
SQL;
        $result = $this->queryOne($sql, [$config_key, $id_e]);
        if ($result === false) {
            throw new ConfigurationNotFoundException($config_key);
        }
        return $result;
    }

    public function hasConfiguration(string $key, int $id_e = 0): bool
    {
        $sql = <<<SQL
SELECT COUNT(*) FROM configuration WHERE config_key = ? AND id_e = ? LIMIT 1;
SQL;
        $result = $this->queryOne($sql, [$key, $id_e]);
        return $result > 0;
    }
}
