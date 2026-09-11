<?php

class EntiteMfaObligationSQL extends SQL
{
    public function enable(int $id_e, ?int $id_u_created_by = null): void
    {
        $sql = <<<SQL
REPLACE INTO entite_mfa_obligation(id_e, created_at, id_u_created_by) VALUES (?, ?, ?);
SQL;
        $this->query($sql, $id_e, $this->getNow(), $id_u_created_by);
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

    public function appliesTo(int $id_e): bool
    {
        return $this->getEnforcingAncestor($id_e) !== null;
    }

    public function getEnforcingAncestor(int $id_e): ?int
    {
        $sql = <<<SQL
SELECT entite_ancetre.id_e_ancetre
FROM entite_ancetre
JOIN entite_mfa_obligation ON entite_mfa_obligation.id_e = entite_ancetre.id_e_ancetre
WHERE entite_ancetre.id_e = ?
ORDER BY entite_ancetre.niveau
LIMIT 1;
SQL;
        $result = $this->queryOne($sql, $id_e);
        return $result === false ? null : (int)$result;
    }

    public function getEnforcingProperAncestor(int $id_e): ?int
    {
        $sql = <<<SQL
SELECT entite_ancetre.id_e_ancetre
FROM entite_ancetre
JOIN entite_mfa_obligation ON entite_mfa_obligation.id_e = entite_ancetre.id_e_ancetre
WHERE entite_ancetre.id_e = ? AND entite_ancetre.niveau > 0
ORDER BY entite_ancetre.niveau
LIMIT 1;
SQL;
        $result = $this->queryOne($sql, $id_e);
        return $result === false ? null : (int)$result;
    }
}
