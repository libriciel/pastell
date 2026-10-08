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
        if ($codeHashes === []) {
            return;
        }
        $values = implode(', ', array_fill(0, count($codeHashes), '(?, ?, NULL)'));
        $sql = "INSERT INTO utilisateur_mfa_recovery_code(id_u, code_hash, used_at) VALUES $values;";
        $params = [];
        foreach ($codeHashes as $codeHash) {
            $params[] = $id_u;
            $params[] = $codeHash;
        }
        $this->query($sql, $params);
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
