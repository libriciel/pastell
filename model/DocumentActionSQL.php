<?php

class DocumentActionSQL extends SQL
{
    public function add($id_d, $id_e, $id_u, $action)
    {
        $now = date(Date::DATE_ISO);

        $sql = "INSERT INTO document_action(id_d,date,action,id_e,id_u) VALUES (?,?,?,?,?)";
        $this->query($sql, $id_d, $now, $action, $id_e, $id_u);
        $id_a = $this->lastInsertId();

        $sql = "UPDATE document_entite SET last_action=? , last_action_date=? WHERE id_d=? AND id_e=?";
        $this->query($sql, $action, $now, $id_d, $id_e);

        return $id_a;
    }

    public function getLastActionNotModif($id_d)
    {
        $sql = "SELECT action FROM document_action " .
            " WHERE id_d=? AND action != ? " .
            " ORDER BY date DESC,id_a DESC LIMIT 1 ";
        return $this->queryOne($sql, $id_d, 'modification');
    }

    public function getLastActionInfo($id_d, $id_e, $action = false)
    {
        $sql = "SELECT * FROM document_action " .
               " WHERE id_d=? AND id_e=?";
        $params = [$id_d, $id_e];
        if ($action) {
            $sql .= " AND action=?";
            $params[] = $action;
        }
        $sql .= " ORDER BY date DESC,id_a DESC LIMIT 1";
        return $this->queryOne($sql, $params);
    }

    public function updateDate($id_a)
    {
        $now = date(Date::DATE_ISO);
        $sql = "UPDATE document_action SET date=? WHERE id_a=?";
        $this->query($sql, $now, $id_a);
    }

    public function getCreator($id_d)
    {
        $sql = "SELECT login,nom,prenom,email,utilisateur.id_u,date,id_d FROM document_action " .
            " JOIN utilisateur ON document_action.id_u=utilisateur.id_u " .
            " WHERE id_d = ? AND action = ? ";
        return $this->queryOne($sql, $id_d, 'creation');
    }

    public function removeLastAction(int $id_e, string $id_d): int
    {
        $lastAction = $this->getLastActionInfo($id_d, $id_e);
        $sql = 'DELETE FROM document_action WHERE id_a = ?';
        $this->queryOne($sql, $lastAction['id_a']);
        return $lastAction['id_a'];
    }
}
