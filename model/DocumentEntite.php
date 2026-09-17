<?php

class DocumentEntite extends SQL
{
    public function getDocument($id_e, $type)
    {
        $sql = "SELECT * FROM document_entite " .
                " JOIN document ON document_entite.id_d = document.id_d" .
                " WHERE document_entite.id_e = ? AND document.type=? " .
                " ORDER BY document.modification DESC";
        return $this->query($sql, $id_e, $type);
    }

    public function addRole($id_d, $id_e, $role)
    {
        if ($this->hasRole($id_d, $id_e, $role)) {
            return;
        }
        $type = $this->queryOne("SELECT type FROM document WHERE id_d=?", $id_d);

        $sql = "INSERT INTO document_entite (id_d,id_e,role,last_type) VALUES (?,?,?,?)";
        $this->query($sql, $id_d, $id_e, $role, $type);
    }

    public function hasRole($id_d, $id_e, $role)
    {
        $sql = "SELECT count(*) FROM document_entite WHERE id_d=? AND id_e=? AND role= ?";
        return $this->queryOne($sql, $id_d, $id_e, $role);
    }

    public function getEntite($id_d)
    {
        $sql = "SELECT * FROM document_entite JOIN entite ON document_entite.id_e = entite.id_e WHERE id_d=?";
        return $this->query($sql, $id_d);
    }

    public function getRole($id_e, $id_d)
    {
        $sql = "SELECT role FROM document_entite WHERE id_e=? AND id_d=? LIMIT 1";
        return  $this->queryOne($sql, $id_e, $id_d);
    }

    public function getEntiteWithRole($id_d, $role)
    {
        $sql = "SELECT id_e FROM document_entite WHERE id_d=? AND role=? LIMIT 1";
        return  $this->queryOne($sql, $id_d, $role);
    }

    public function getFromAction($type, $action)
    {
        $sql = "SELECT * FROM document_entite " .
                " JOIN document ON document_entite.id_d = document.id_d " .
                " WHERE type = ? AND document_entite.last_action=?";
        return $this->query($sql, $type, $action);
    }

    public function getAll($id_e)
    {
        $sql = "SELECT * FROM document_entite " .
                " JOIN document ON document_entite.id_d=document.id_d " .
                " WHERE id_e=?";
        return $this->query($sql, $id_e);
    }

    public function getNbAll($id_e, string $type = '')
    {
        $sql = 'SELECT count(*) FROM document_entite ' .
            ' JOIN document ON document_entite.id_d=document.id_d ' .
            ' WHERE id_e=?';
        $params = [$id_e];

        if ($type !== '') {
            $sql .= ' AND last_type = ?';
            $params[] = $type;
        }
        return $this->queryOne($sql, $params);
    }

    public function getAllByFluxAction($flux, $action_from)
    {
        $sql = "SELECT * FROM document_entite " .
            " JOIN document ON document_entite.id_d=document.id_d " .
            " JOIN entite ON document_entite.id_e = entite.id_e " .
            " WHERE document.type=? AND last_action=?";
        return $this->query($sql, $flux, $action_from);
    }


    public function getAllLocal()
    {
        $sql = "SELECT * FROM document_entite " .
            " JOIN document ON document_entite.id_d=document.id_d " .
            " WHERE id_e != 0";
        return $this->query($sql);
    }

    public function fixAction($flux, $action_from, $action_to)
    {
        $sql = "UPDATE document_entite " .
                " JOIN document ON document_entite.id_d=document.id_d " .
                " SET last_action=?" .
                " WHERE document.type=? AND last_action=?";
        $this->query($sql, $action_to, $flux, $action_from);

        $sql = "UPDATE document_action " .
                " JOIN document ON document_action.id_d=document.id_d " .
                " SET action=?" .
                " WHERE document.type=? AND action=?";
        $this->query($sql, $action_to, $flux, $action_from);
    }

    public function getCountAction($id_e, $type)
    {
        $sql = "SELECT count(*) as count,id_e,last_type as type,last_action FROM document_entite ";

        $data = [];
        $where_clause = [];
        if ($id_e) {
            $where_clause[] = " id_e = ?";
            $data[] = $id_e;
        }
        if ($type) {
            $where_clause[] = " last_type = ? ";
            $data[] = $type;
        }
        if ($where_clause) {
            $sql .= " WHERE " . implode(" AND ", $where_clause);
        }
        $sql .= " GROUP BY id_e,last_type,last_action;";
        return $this->query($sql, $data);
    }


    public function fixLastType()
    {
        $sql = "UPDATE document_entite JOIN document ON document_entite.id_d=document.id_d SET document_entite.last_type=document.type";
        $this->query($sql);
    }

    public function getDocumentLastActionOlderThanInDays($nb_days, $type = null, $id_e = null): array
    {
        $date = date("Y-m-d", strtotime($nb_days ? "-$nb_days days" : 'now'));
        $sql = "SELECT * FROM document_entite " .
            " WHERE  date(document_entite.last_action_date)<=? ";
        $data = [$date];
        if ($type) {
            $sql .= " AND document_entite.last_type=? ";
            $data[] = $type;
        }
        if ($id_e) {
            $sql .= " AND document_entite.id_e=? ";
            $data[] = $id_e;
        }
        return $this->query($sql, $data);
    }

    public function changeAction(int $id_e, string $id_d, string $action, string $date): void
    {
        $sql = 'UPDATE document_entite SET last_action=? , last_action_date=? WHERE id_d=? AND id_e=?';
        $this->query($sql, $action, $date, $id_d, $id_e);
    }
}
