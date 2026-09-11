<?php

declare(strict_types=1);

class UtilisateurMfaRecoveryCodeSQL extends SQL
{
    /**
     * @param string[] $codeHashes
     */
    public function replaceAll(int $id_u, array $codeHashes): void
    {
        $this->deleteAll($id_u);
        $sql = <<<SQL
INSERT INTO utilisateur_mfa_recovery_code(id_u, code_hash, used_at) VALUES (?, ?, NULL);
SQL;
        foreach ($codeHashes as $codeHash) {
            $this->query($sql, $id_u, $codeHash);
        }
    }

    public function deleteAll(int $id_u): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur_mfa_recovery_code WHERE id_u = ?;
SQL;
        $this->query($sql, $id_u);
    }

    public function consume(int $id_u, string $codeHash): bool
    {
        $sql = <<<SQL
UPDATE utilisateur_mfa_recovery_code SET used_at = ? WHERE id_u = ? AND code_hash = ? AND used_at IS NULL;
SQL;
        return $this->execute($sql, $this->getNow(), $id_u, $codeHash) > 0;
    }

    public function countRemaining(int $id_u): int
    {
        $sql = <<<SQL
SELECT COUNT(*) FROM utilisateur_mfa_recovery_code WHERE id_u = ? AND used_at IS NULL;
SQL;
        return (int)$this->queryOne($sql, $id_u);
    }
}
