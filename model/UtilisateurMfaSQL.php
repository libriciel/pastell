<?php

declare(strict_types=1);

class UtilisateurMfaSQL extends SQL
{
    public function getInfo(int $id_u): array
    {
        $sql = <<<SQL
SELECT * FROM utilisateur_mfa WHERE id_u = ?;
SQL;
        return $this->queryOne($sql, $id_u) ?: [];
    }

    public function isEnabled(int $id_u): bool
    {
        $sql = <<<SQL
SELECT is_enabled FROM utilisateur_mfa WHERE id_u = ?;
SQL;
        return (bool)$this->queryOne($sql, $id_u);
    }

    public function enroll(int $id_u, string $secret): void
    {
        $sql = <<<SQL
INSERT INTO utilisateur_mfa(id_u, secret, pending_secret, is_enabled)
VALUES (?, '', ?, 0)
ON DUPLICATE KEY UPDATE pending_secret = VALUES(pending_secret);
SQL;
        $this->query($sql, $id_u, $secret);
    }

    public function confirm(int $id_u, ?int $last_used_counter): void
    {
        $sql = <<<SQL
UPDATE utilisateur_mfa
SET secret = pending_secret, pending_secret = NULL, is_enabled = 1, activated_at = ?, last_used_counter = ?
WHERE id_u = ? AND pending_secret IS NOT NULL;
SQL;
        $this->query($sql, $this->getNow(), $last_used_counter, $id_u);
    }

    public function updateLastUsedCounter(int $id_u, int $counter): bool
    {
        $sql = <<<SQL
UPDATE utilisateur_mfa SET last_used_counter = ?
WHERE id_u = ? AND (last_used_counter IS NULL OR last_used_counter < ?);
SQL;
        return $this->execute($sql, $counter, $id_u, $counter) > 0;
    }

    public function clearPendingSecret(int $id_u): void
    {
        $sql = <<<SQL
UPDATE utilisateur_mfa SET pending_secret = NULL WHERE id_u = ?;
SQL;
        $this->query($sql, $id_u);
    }

    public function delete(int $id_u): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur_mfa WHERE id_u = ?;
SQL;
        $this->query($sql, $id_u);
    }

    public function deleteAllExcept(string $login): void
    {
        $sql = <<<SQL
DELETE utilisateur_mfa FROM utilisateur_mfa
JOIN utilisateur ON utilisateur.id_u = utilisateur_mfa.id_u
WHERE utilisateur.login <> ?;
SQL;
        $this->query($sql, $login);
    }
}
