<?php

declare(strict_types=1);

/**
 * Separate table rather than a column on `entite`, as the root entity (id_e = 0) has no row there.
 */
class EntiteMfaObligationSQL extends SQL
{
    private const int LEVEL_SELF = 0;
    private const int LEVEL_PARENT = 1;

    public function enable(int $id_e): void
    {
        $sql = <<<SQL
INSERT IGNORE INTO entite_mfa_obligation(id_e) VALUES (?);
SQL;
        $this->query($sql, $id_e);
    }

    public function disable(int $id_e): void
    {
        $sql = <<<SQL
DELETE FROM entite_mfa_obligation WHERE id_e = ?;
SQL;
        $this->query($sql, $id_e);
    }

    public function isDirectlySet(int $id_e): bool
    {
        $sql = <<<SQL
SELECT COUNT(*) FROM entite_mfa_obligation WHERE id_e = ?;
SQL;
        return (bool)$this->queryOne($sql, $id_e);
    }

    public function getEnforcingAncestor(int $id_e): ?int
    {
        return $this->findEnforcingAncestor($id_e, self::LEVEL_SELF);
    }

    public function getEnforcingProperAncestor(int $id_e): ?int
    {
        return $this->findEnforcingAncestor($id_e, self::LEVEL_PARENT);
    }

    private function findEnforcingAncestor(int $id_e, int $min_level): ?int
    {
        $sql = <<<SQL
SELECT entite_ancetre.id_e_ancetre
FROM entite_ancetre
JOIN entite_mfa_obligation ON entite_mfa_obligation.id_e = entite_ancetre.id_e_ancetre
WHERE entite_ancetre.id_e = ? AND entite_ancetre.niveau >= ?
ORDER BY entite_ancetre.niveau
LIMIT 1;
SQL;
        $result = $this->queryOne($sql, $id_e, $min_level);
        return $result === false ? null : (int)$result;
    }
}
