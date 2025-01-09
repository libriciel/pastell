<?php

class ConnecteurEntiteSQL extends SQL
{
    public function getAllForPlateform()
    {
        $sql = "SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite " .
            " LEFT JOIN entite ON connecteur_entite.id_e=entite.id_e ORDER BY connecteur_entite.id_ce";
        return $this->query($sql);
    }


    public function getAll($id_e)
    {
        $sql = "SELECT * FROM connecteur_entite " .
            " WHERE id_e = ? " .
            " ORDER BY connecteur_entite.libelle";
        return $this->query($sql, $id_e);
    }

    /** @deprecated Since 4.1.5, Use getAllGlobalByIde($id_e) instead */
    public function getAllGlobal()
    {
        $sql = "SELECT * FROM connecteur_entite " .
            " WHERE id_e = 0" .
            " ORDER BY libelle";
        return $this->query($sql);
    }

    /** @deprecated Since 4.1.5, Use getAllLocalByIde($id_e) instead  */
    public function getAllLocal()
    {
        $sql = "SELECT * FROM connecteur_entite " .
            " WHERE id_e != 0" .
            " ORDER BY libelle";
        return $this->query($sql);
    }

    public function getAllGlobalByIde(int $id_e): array
    {
        $sql = "SELECT * FROM connecteur_entite " .
            " WHERE global = 1" .
            " AND id_e = ? " .
            " ORDER BY libelle";
        return $this->query($sql, $id_e);
    }

    public function getAllLocalByIde(int $id_e): array
    {
        $sql = "SELECT * FROM connecteur_entite " .
            " WHERE global = 0" .
            " AND id_e = ? " .
            " ORDER BY libelle";
        return $this->query($sql, $id_e);
    }

    /**
     * @param int<0,1> $global
     */
    public function addConnecteur($id_e, $id_connecteur, $type, $libelle, int $global)
    {
        $sql = "INSERT INTO connecteur_entite (id_e,id_connecteur,type,libelle,global) VALUES (?,?,?,?,?)";
        $this->query($sql, $id_e, $id_connecteur, $type, $libelle, $global);
        return $this->lastInsertId();
    }

    public function getInfo($id_ce)
    {
        $sql = "SELECT * FROM connecteur_entite WHERE id_ce = ?";
        return $this->queryOne($sql, $id_ce);
    }

    public function delete($id_ce)
    {
        $sql = "DELETE FROM connecteur_entite WHERE id_ce=?";
        return $this->query($sql, $id_ce);
    }

    public function edit($id_ce, $libelle, $frequence_en_minute = 1, $id_verrou = '')
    {
        if ($frequence_en_minute < 1) {
            $frequence_en_minute = 1;
        }
        $sql = "UPDATE connecteur_entite SET libelle=?, frequence_en_minute=?,id_verrou=? WHERE id_ce=?";
        $this->query($sql, $libelle, $frequence_en_minute, $id_verrou, $id_ce);
    }

    public function getDisponible($id_e, $type)
    {
        $sql = "SELECT connecteur_entite.*,entite.denomination " .
                " FROM connecteur_entite " .
                " LEFT JOIN entite ON connecteur_entite.id_e=entite.id_e " .
                " WHERE connecteur_entite.type=? " .
                " AND connecteur_entite.id_e = ?";
        return $this->query($sql, $type, $id_e);
    }

    public function getGlobal($id_connecteur)
    {
        $sql = "SELECT id_ce FROM connecteur_entite WHERE id_connecteur = ? AND global = 1";
        return $this->queryOne($sql, $id_connecteur);
    }

    public function getDisponibleUsed($id_e, $type)
    {
        $sql = "SELECT DISTINCT connecteur_entite.* "
            . "FROM connecteur_entite "
            . "INNER JOIN flux_entite ON connecteur_entite.id_ce = flux_entite.id_ce "
            . "WHERE connecteur_entite.type=? "
            . "AND connecteur_entite.id_e = ?";
        return $this->query($sql, $type, $id_e);
    }

    public function getDisponibleUsedLocal($type)
    {
        $sql = "SELECT DISTINCT connecteur_entite.* "
            . "FROM connecteur_entite "
            . "INNER JOIN flux_entite ON connecteur_entite.id_ce = flux_entite.id_ce "
            . "WHERE connecteur_entite.type=? "
            . "AND connecteur_entite.id_e != 0";
        return $this->query($sql, $type);
    }

    public function getOne($id_connecteur)
    {
        $sql = "SELECT id_ce FROM connecteur_entite WHERE  id_connecteur = ?";
        return $this->queryOne($sql, $id_connecteur);
    }

    public function getAllById($id_connecteur)
    {
        $sql = "SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite " .
                " LEFT JOIN entite ON connecteur_entite.id_e=entite.id_e " .
                " WHERE id_connecteur = ? " .
                " ORDER BY connecteur_entite.libelle,connecteur_entite.id_ce ";
        return $this->query($sql, $id_connecteur);
    }

    public function getAllEntiteConnectorById($id_connecteur, int $offset = 0, int $limit = 0)
    {
        $sql = "SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite " .
            " LEFT JOIN entite ON connecteur_entite.id_e=entite.id_e " .
            " WHERE connecteur_entite.id_connecteur = ? AND connecteur_entite.id_e != 0";
        if ($limit) {
            $sql .= " LIMIT $offset,$limit";
        }
        return $this->query($sql, $id_connecteur);
    }

    public function getCountAllEntiteConnectorById($id_connecteur)
    {
        $sql = "SELECT count(*) FROM connecteur_entite " .
            " WHERE id_connecteur = ? AND connecteur_entite.id_e !=0";
        return $this->queryOne($sql, $id_connecteur);
    }


    public function getByType($id_e, $type)
    {
        $sql = "SELECT * FROM connecteur_entite WHERE id_e=? AND type= ? ORDER BY libelle DESC";
        return $this->query($sql, $id_e, $type);
    }

    public function getAllId()
    {
        $sql = "SELECT distinct id_connecteur FROM connecteur_entite WHERE id_e <>0";
        return  $this->query($sql);
    }

    public function getAllUsedByScope($global = false)
    {
        if ($global) {
            $sql = "SELECT distinct id_connecteur FROM connecteur_entite " .
                " WHERE global = 1";
        } else {
            $sql = "SELECT distinct id_connecteur FROM connecteur_entite " .
                " WHERE global = 0";
        }
        return  $this->queryOneCol($sql);
    }

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

    public function getAllByConnecteurId($id_connecteur, $global = false)
    {
        if ($global) {
            $sql = "SELECT connecteur_entite.* FROM connecteur_entite " .
                " WHERE id_connecteur = ? AND global = 1";
        } else {
            $sql = "SELECT connecteur_entite.*, entite.denomination FROM connecteur_entite " .
                " JOIN entite ON connecteur_entite.id_e=entite.id_e " .
                " WHERE id_connecteur = ? AND global = 0";
        }
        return $this->query($sql, $id_connecteur);
    }
}
