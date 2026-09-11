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

    public function isEnrolmentRequired(int $id_u): bool
    {
        $sql = <<<SQL
SELECT enrolment_required FROM utilisateur_mfa WHERE id_u = ?;
SQL;
        return (bool)$this->queryOne($sql, $id_u);
    }

    public function enroll(int $id_u, string $secret): void
    {
        $enrolmentRequired = $this->isEnrolmentRequired($id_u) ? 1 : 0;
        $this->delete($id_u);
        $sql = <<<SQL
INSERT INTO utilisateur_mfa(id_u, secret, is_enabled, enrolment_required, created_at)
VALUES (?, ?, 0, ?, ?)
ON DUPLICATE KEY UPDATE secret = VALUES(secret), is_enabled = 0, created_at = VALUES(created_at);
SQL;
        $this->query($sql, $id_u, $secret, $enrolmentRequired, $this->getNow());
    }

    public function confirm(int $id_u): void
    {
        $sql = <<<SQL
UPDATE utilisateur_mfa SET is_enabled = 1, enrolment_required = 0, created_at = ? WHERE id_u = ?;
SQL;
        $this->query($sql, $this->getNow(), $id_u);
    }

    public function requireReenrolment(int $id_u): void
    {
        $sql = <<<SQL
REPLACE INTO utilisateur_mfa(id_u, secret, is_enabled, enrolment_required, created_at) VALUES (?, '', 0, 1, ?);
SQL;
        $this->query($sql, $id_u, $this->getNow());
    }

    public function delete(int $id_u): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur_mfa WHERE id_u = ?;
SQL;
        $this->query($sql, $id_u);
    }
}
