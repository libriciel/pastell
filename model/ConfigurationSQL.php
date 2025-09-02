<?php

declare(strict_types=1);

use Pastell\Exception\ConfigurationNotFoundException;

class ConfigurationSQL extends SQL
{
    public const int NULL_ID_E = -1;

    public const string ADMIN_EMAIL = 'ADMIN_EMAIL';
    public function setConfiguration(string $config_key, string $config_value, int $id_e): void
    {
        $sql = <<<SQL
INSERT INTO configuration (`config_key`, `config_value`, `id_e`)
VALUES (?, ?, ?)
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`)
SQL;
        $this->query($sql, [$config_key, $config_value, $id_e]);
    }

    public function getConfiguration(string $config_key, int $id_e): string
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

    public function hasConfiguration(string $key, int $id_e): bool
    {
        $sql = <<<SQL
SELECT COUNT(*) FROM configuration WHERE config_key = ? AND id_e = ? LIMIT 1;
SQL;
        $result = $this->queryOne($sql, [$key, $id_e]);
        return $result > 0;
    }

    public function setAdminEmails(array $emails): void
    {
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid email format: $email");
            }
        }
        $email_config = implode(',', $emails);
        $this->setConfiguration(self::ADMIN_EMAIL, $email_config, self::NULL_ID_E);
    }

    public function getAdminEmails(): array
    {
        try {
            $admin_email = $this->getConfiguration(self::ADMIN_EMAIL, self::NULL_ID_E);
            return array_map('trim', explode(',', $admin_email));
        } catch (ConfigurationNotFoundException) {
            return [];
        }
    }
}
