<?php

class ConnecteurEntiteSQL extends SQL
{
    public function getAllForPlateform()
    {
        $sql = <<<SQL
SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite
LEFT JOIN entite ON connecteur_entite.id_e = entite.id_e
ORDER BY connecteur_entite.id_ce
SQL;
        return $this->query($sql);
    }

    public function getAll($id_e)
    {
        $sql = <<<SQL
SELECT * FROM connecteur_entite WHERE id_e = ? ORDER BY connecteur_entite.libelle
SQL;
        return $this->query($sql, $id_e);
    }

    /** @deprecated Since 4.1.6, Use getAllGlobalByIde($id_e) instead */
    public function getAllGlobal()
    {
        $sql = "SELECT * FROM connecteur_entite " .
            " WHERE id_e = 0" .
            " ORDER BY libelle";
        return $this->query($sql);
    }

    /** @deprecated Since 4.1.6, Use getAllLocalByIde($id_e) instead  */
    public function getAllLocal()
    {
        $sql = "SELECT * FROM connecteur_entite " .
            " WHERE id_e != 0" .
            " ORDER BY libelle";
        return $this->query($sql);
    }

    public function getAllGlobalByIde(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM connecteur_entite WHERE global = 1 AND id_e = ? ORDER BY libelle
SQL;
        return $this->query($sql, $id_e);
    }

    public function getAllLocalByIde(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM connecteur_entite WHERE global = 0 AND id_e = ? ORDER BY libelle
SQL;
        return $this->query($sql, $id_e);
    }

    /**
     * @param int<0,1> $global
     */
    public function addConnecteur($id_e, $id_connecteur, $type, $libelle, int $global)
    {
        $sql = <<<SQL
INSERT INTO connecteur_entite (id_e, id_connecteur, type, libelle, global) VALUES (?, ?, ?, ?, ?)
SQL;
        $this->query($sql, $id_e, $id_connecteur, $type, $libelle, $global);
        return $this->lastInsertId();
    }

    public function getInfo($id_ce)
    {
        $sql = <<<SQL
SELECT * FROM connecteur_entite WHERE id_ce = ?
SQL;
        return $this->queryOne($sql, $id_ce);
    }

    public function delete($id_ce)
    {
        $sql = <<<SQL
DELETE FROM connecteur_entite WHERE id_ce = ?
SQL;
        return $this->query($sql, $id_ce);
    }

    /**
     * @deprecated 4.1.26 Use ConnecteurModificationService::editConnecteurLibelle() instead
     */
    public function edit($id_ce, $libelle, $frequence_en_minute = 1, $id_verrou = '')
    {
        if ($frequence_en_minute < 1) {
            $frequence_en_minute = 1;
        }
        $sql = "UPDATE connecteur_entite SET libelle=?, frequence_en_minute=?,id_verrou=? WHERE id_ce=?";
        $this->query($sql, $libelle, $frequence_en_minute, $id_verrou, $id_ce);
    }

    public function update(int $id_ce, string $libelle, int $frequence_en_minute, string $id_verrou): void
    {
        $sql = <<<SQL
UPDATE connecteur_entite SET libelle = ?, frequence_en_minute = ?, id_verrou = ? WHERE id_ce = ?
SQL;
        $this->query($sql, $libelle, $frequence_en_minute, $id_verrou, $id_ce);
    }

    public function getDisponible($id_e, $type, bool $global = false)
    {
        $sql = <<<SQL
SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite
LEFT JOIN entite ON connecteur_entite.id_e = entite.id_e
WHERE connecteur_entite.type = ? AND connecteur_entite.id_e = ? AND connecteur_entite.global = ?
SQL;
        return $this->query($sql, $type, $id_e, $global);
    }

    public function getGlobal($id_connecteur)
    {
        $sql = <<<SQL
SELECT id_ce FROM connecteur_entite WHERE id_connecteur = ? AND global = 1
SQL;
        return $this->queryOne($sql, $id_connecteur);
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
    public function getDisponibleUsed($id_e, $type)
    {
        $sql = "SELECT DISTINCT connecteur_entite.* "
            . "FROM connecteur_entite "
            . "INNER JOIN flux_entite ON connecteur_entite.id_ce = flux_entite.id_ce "
            . "WHERE connecteur_entite.type=? "
            . "AND connecteur_entite.id_e = ?";
        return $this->query($sql, $type, $id_e);
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
    public function getDisponibleUsedLocal($type)
    {
        $sql = "SELECT DISTINCT connecteur_entite.* "
            . "FROM connecteur_entite "
            . "INNER JOIN flux_entite ON connecteur_entite.id_ce = flux_entite.id_ce "
            . "WHERE connecteur_entite.type=? "
            . "AND connecteur_entite.id_e != 0";
        return $this->query($sql, $type);
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
    public function getOne($id_connecteur)
    {
        $sql = "SELECT id_ce FROM connecteur_entite WHERE  id_connecteur = ?";
        return $this->queryOne($sql, $id_connecteur);
    }

    public function getAllById($id_connecteur)
    {
        $sql = <<<SQL
SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite
LEFT JOIN entite ON connecteur_entite.id_e = entite.id_e
WHERE id_connecteur = ?
ORDER BY connecteur_entite.libelle, connecteur_entite.id_ce
SQL;
        return $this->query($sql, $id_connecteur);
    }

    public function getAllEntiteConnectorById($id_connecteur, int $offset = 0, int $limit = 0)
    {
        $sql = <<<SQL
SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite
LEFT JOIN entite ON connecteur_entite.id_e = entite.id_e
WHERE connecteur_entite.id_connecteur = ? AND connecteur_entite.id_e != 0
SQL;
        if ($limit) {
            $sql .= " LIMIT $offset, $limit";
        }
        return $this->query($sql, $id_connecteur);
    }

    public function getCountAllEntiteConnectorById($id_connecteur)
    {
        $sql = <<<SQL
SELECT count(*) FROM connecteur_entite WHERE id_connecteur = ? AND connecteur_entite.id_e != 0
SQL;
        return $this->queryOne($sql, $id_connecteur);
    }

    public function getByType($id_e, $type)
    {
        $sql = <<<SQL
SELECT * FROM connecteur_entite WHERE id_e = ? AND type = ? ORDER BY libelle DESC
SQL;
        return $this->query($sql, $id_e, $type);
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
    public function getAllId()
    {
        $sql = "SELECT distinct id_connecteur FROM connecteur_entite WHERE id_e <>0";
        return  $this->query($sql);
    }

    public function getAllUsedByScope($global = false)
    {
        $sql = <<<SQL
SELECT DISTINCT id_connecteur FROM connecteur_entite WHERE global = ?
SQL;
        return $this->queryOneCol($sql, $global ? 1 : 0);
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
    public function listNotUsed($id_e)
    {
        $sql = "SELECT ce.* FROM connecteur_entite ce";
        $sql .= " LEFT JOIN flux_entite fe ON ce.id_ce = fe.id_ce";
        $sql .= " WHERE fe.id_ce IS NULL";
        if ($id_e) {
            $sql .= " AND ce.id_e IN (SELECT id_e FROM entite_ancetre ea WHERE ea.id_e_ancetre = ?)";
            return $this->query($sql, $id_e);
        } else {
            return $this->query($sql);
        }
    }

    public function isConnectorInEntityScope(int $connectorId, int $entityId): bool
    {
        $sql = <<<SQL
SELECT id_ce
FROM connecteur_entite
WHERE id_ce = ?
  AND (
    id_e = 0
    OR id_e IN (SELECT ea.id_e_ancetre FROM entite_ancetre ea WHERE ea.id_e = ?)
  )
SQL;
        return (bool)$this->queryOne($sql, $connectorId, $entityId);
    }

    public function getAllByConnecteurId($id_connecteur, $global = false)
    {
        if ($global) {
            $sql = <<<SQL
SELECT connecteur_entite.* FROM connecteur_entite WHERE id_connecteur = ? AND global = 1
SQL;
        } else {
            $sql = <<<SQL
SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite
JOIN entite ON connecteur_entite.id_e = entite.id_e
WHERE id_connecteur = ? AND global = 0
SQL;
        }
        return $this->query($sql, $id_connecteur);
    }
}
