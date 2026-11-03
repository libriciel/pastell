<?php

declare(strict_types=1);

final class UserTokenSQL extends SQL
{
    public function create(int $id_u, string $name, string $token_hash, ?string $expiration_date = null): void
    {
        $sql = <<<SQL
INSERT INTO users_token (id_u, name, token, created_at, expired_at)
VALUES (?, ?, ?, ?, ?)
SQL;
        $this->query($sql, $id_u, $name, $token_hash, \date(Date::DATE_ISO), $expiration_date);
    }

    public function updateToken(int $id, string $token_hash): void
    {
        $sql = <<<SQL
UPDATE users_token SET token = ? WHERE id = ?
SQL;
        $this->query($sql, $token_hash, $id);
    }

    public function getTokensOfUser(int $id_u): array
    {
        $sql = <<<SQL
SELECT id, id_u, name, created_at, expired_at FROM users_token WHERE id_u = ?
SQL;
        return $this->query($sql, $id_u);
    }

    public function getUser(int $id): ?array
    {
        $sql = <<<SQL
SELECT utilisateur.id_u, utilisateur.login
FROM users_token
JOIN utilisateur ON users_token.id_u = utilisateur.id_u
WHERE users_token.id = ?
SQL;
        return $this->queryOne($sql, $id) ?: null;
    }

    public function getUserFromToken(string $token_hash): ?array
    {
        $sql = <<<SQL
SELECT utilisateur.id_u, utilisateur.login, users_token.expired_at
FROM users_token
JOIN utilisateur ON users_token.id_u = utilisateur.id_u
WHERE users_token.token = ?
SQL;
        return $this->queryOne($sql, $token_hash) ?: null;
    }

    public function delete(int $id): void
    {
        $sql = <<<SQL
DELETE FROM users_token WHERE id = ?
SQL;
        $this->query($sql, $id);
    }

    public function deleteAllForUser(int $id_u): void
    {
        $sql = <<<SQL
DELETE FROM users_token WHERE id_u = ?
SQL;
        $this->query($sql, $id_u);
    }

    public function getInfo(int $id): ?array
    {
        $sql = <<<SQL
SELECT * FROM users_token WHERE id = ?
SQL;
        return $this->queryOne($sql, $id) ?: null;
    }

    public function getInfoByToken(string $token_hash): ?array
    {
        $sql = <<<SQL
SELECT * FROM users_token WHERE token = ?
SQL;
        return $this->queryOne($sql, $token_hash) ?: null;
    }
}
