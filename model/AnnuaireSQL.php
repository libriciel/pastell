<?php

class AnnuaireSQL extends SQL
{
    public const DESCRIPTION = 'description';

    public function getUtilisateur($id_e)
    {
        $sql = "SELECT * FROM annuaire WHERE id_e=? " .
            " ORDER BY description ASC";
        return $this->query($sql, $id_e);
    }

    public function getUtilisateurList($id_e, $offset, $limit, $search, $id_g)
    {
        $sql = "SELECT * FROM annuaire ";
        if ($id_g) {
            $sql .= " JOIN annuaire_groupe_contact ON annuaire_groupe_contact.id_a=annuaire.id_a AND annuaire_groupe_contact.id_g=?";
            $data[] = $id_g;
        }
        $sql .= " WHERE annuaire.id_e=? " ;
        $data[] = $id_e;

        if ($search) {
            $sql .= " AND (description LIKE ? OR email LIKE ? )";
            $data[] = "%$search%";
            $data[] = "%$search%";
        }

        $sql .= " ORDER BY description ASC " .
                " LIMIT $offset,$limit";

        return $this->query($sql, $data);
    }

    public function getNbUtilisateur($id_e, $search, $id_g)
    {
        $sql = "SELECT count(*) FROM annuaire " ;
        if ($id_g) {
            $sql .= " JOIN annuaire_groupe_contact ON annuaire_groupe_contact.id_a=annuaire.id_a AND annuaire_groupe_contact.id_g=?";
            $data[] = $id_g;
        }
        $sql .= " WHERE annuaire.id_e=? " ;
        $data[] = $id_e;
        if ($search) {
            $sql .= " AND (description LIKE ? OR email LIKE ? )";
            $data[] = "%$search%";
            $data[] = "%$search%";
        }


        return $this->queryOne($sql, $data);
    }

    public function getFromEmail($id_e, $email)
    {
        $sql = "SELECT id_a FROM annuaire WHERE id_e=? AND email=? ORDER BY email ASC";
        return $this->queryOne($sql, $id_e, $email);
    }

    public function add($id_e, $description, $email)
    {
        $id_a = $this->getFromEmail($id_e, $email);

        if ($id_a) {
            $sql = "UPDATE annuaire SET description=? WHERE id_e=? AND email= ?";
            $this->query($sql, $description, $id_e, $email);
        } else {
            $sql = "INSERT INTO annuaire (id_e,description,email) VALUES (?,?,?)";
            $this->query($sql, $id_e, $description, $email);
            $id_a = $this->getFromEmail($id_e, $email);
        }
        return $id_a;
    }

    public function delete($id_e, $id_a)
    {
        $sql = "DELETE FROM annuaire WHERE id_e=? AND id_a = ?";
        $this->query($sql, $id_e, $id_a);
    }

    public function getListeMail($id_e, $debut)
    {
        $sql = "SELECT description,email FROM annuaire " .
                " WHERE (email LIKE ? OR description LIKE ?) AND id_e = ? " .
                " ORDER by description,email";
        return $this->query($sql, "$debut%", "$debut%", $id_e);
    }

    public function getInfo($id_a)
    {
        $sql = "SELECT * FROM annuaire WHERE id_a=?";
        return $this->queryOne($sql, $id_a);
    }

    public function edit($id_a, $description, $email)
    {
        $sql = "UPDATE annuaire SET description=?, email=? WHERE id_a=?";
        $this->query($sql, $description, $email, $id_a);
    }

    public function getNbGroupe($id_e): int
    {
        $sql = <<<SQL
SELECT count(*) FROM annuaire_groupe WHERE id_e=?;
SQL;
        return (int)$this->queryOne($sql, $id_e);
    }
}
