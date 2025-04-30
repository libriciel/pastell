<?php

declare(strict_types=1);

use Pastell\Exception\ConfigurationNotFoundException;

class ConfigurationSQL extends SQL
{
    public const NB_WORKERS = 'nb_workers';
    public function setConfiguration(string $config_key, string $config_value): void
    {
        $sql = <<<SQL
INSERT INTO configuration (`config_key`, `config_value`)
VALUES (?, ?)
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`)
SQL;
        $this->query($sql, [$config_key, $config_value]);
    }

    public function getConfiguration(string $config_key): ?string
    {
        $sql = <<<SQL
SELECT config_value FROM configuration WHERE config_key = ?;
SQL;
        $result = $this->queryOne($sql, [$config_key]);
        if (!$result) {
            throw new ConfigurationNotFoundException($config_key);
        }
        return $this->queryOne($sql, [$config_key]) ?: null;
    }

    public function hasConfiguration(string $key): bool
    {
        $sql = <<<SQL
SELECT COUNT(*) FROM configuration WHERE config_key = ?;
SQL;
        $result = $this->queryOne($sql, [$key]);
        return $result > 0;
    }
}
