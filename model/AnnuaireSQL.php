<?php

class AnnuaireSQL extends SQL
{
    public const DESCRIPTION = 'description';

    public function getUtilisateur($id_e): array
    {
        $sql = <<<SQL
            SELECT * FROM annuaire WHERE id_e=?
            ORDER BY description ASC
            SQL;
        return $this->query($sql, $id_e);
    }

    public function getUtilisateurList($id_e, $offset, $limit, $search, $id_g): array
    {
        $data = [];
        $sql = 'SELECT * FROM annuaire '
            . $this->buildFilterClause($id_e, $search, $id_g, $data)
            . " ORDER BY description ASC LIMIT $offset,$limit";

        return $this->query($sql, $data);
    }

    public function getFilteredUtilisateur($id_e, $search, $id_g): array
    {
        $data = [];
        $sql = 'SELECT annuaire.* FROM annuaire '
            . $this->buildFilterClause($id_e, $search, $id_g, $data)
            . ' ORDER BY description ASC';

        return $this->query($sql, $data);
    }

    public function getNbUtilisateur($id_e, $search, $id_g)
    {
        $data = [];
        $sql = 'SELECT count(*) FROM annuaire '
            . $this->buildFilterClause($id_e, $search, $id_g, $data);

        return $this->queryOne($sql, $data);
    }

    private function buildFilterClause($id_e, $search, $id_g, array &$data): string
    {
        $sql = '';
        if ($id_g) {
            $sql .= ' JOIN annuaire_groupe_contact ON annuaire_groupe_contact.id_a=annuaire.id_a AND annuaire_groupe_contact.id_g=?';
            $data[] = $id_g;
        }
        $sql .= ' WHERE annuaire.id_e=? ';
        $data[] = $id_e;

        if ($search) {
            $sql .= ' AND (description LIKE ? OR email LIKE ? )';
            $data[] = "%$search%";
            $data[] = "%$search%";
        }

        return $sql;
    }

    public function getFromEmail($id_e, $email)
    {
        $sql = <<<SQL
SELECT id_a FROM annuaire WHERE id_e=? AND email=? ORDER BY email ASC
SQL;
        return $this->queryOne($sql, $id_e, $email);
    }

    public function add(int $id_e, string $description, string $email): int
    {
        $sql = <<<SQL
INSERT INTO annuaire (id_e,description,email) VALUES (?,?,?)
SQL;
        $this->query($sql, $id_e, $description, $email);
        return (int)$this->lastInsertId();
    }

    public function delete($id_e, $id_a): void
    {
        $sql = <<<SQL
DELETE FROM annuaire WHERE id_e=? AND id_a = ?
SQL;
        $this->query($sql, $id_e, $id_a);
    }

    public function getListeMail($id_e, $debut): array
    {
        $sql = <<<SQL
SELECT description,email FROM annuaire
WHERE (email LIKE ? OR description LIKE ?) AND id_e = ?
ORDER BY description,email
SQL;
        return $this->query($sql, "$debut%", "$debut%", $id_e);
    }

    public function getInfo($id_a): array|false
    {
        $sql = <<<SQL
SELECT * FROM annuaire WHERE id_a=?
SQL;
        return $this->queryOne($sql, $id_a);
    }

    public function edit($id_a, $description, $email): void
    {
        $sql = <<<SQL
UPDATE annuaire SET description=?, email=? WHERE id_a=?
SQL;
        $this->query($sql, $description, $email, $id_a);
    }

    public function getNbGroupe($id_e): int
    {
        $sql = <<<SQL
SELECT count(*) FROM annuaire_groupe WHERE id_e=?
SQL;
        return (int)$this->queryOne($sql, $id_e);
    }
}
