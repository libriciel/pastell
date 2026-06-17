<?php

class AnnuaireGroupeSQL extends SQL
{
    public const NB_MAX = 20;

    public function getInfo(int $id_e, int $id_g): array|false
    {
        $sql = <<<SQL
SELECT * FROM annuaire_groupe WHERE id_e=? AND id_g=?
SQL;
        return $this->queryOne($sql, [$id_e, $id_g]);
    }

    public function getGroupe(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM annuaire_groupe WHERE id_e=? ORDER BY nom ASC
SQL;
        return $this->query($sql, $id_e);
    }

    public function getFromNom(int $id_e, string $nom): int|false
    {
        $sql = <<<SQL
SELECT id_g FROM annuaire_groupe WHERE id_e=? AND nom=?
SQL;
        return $this->queryOne($sql, $id_e, $nom);
    }

    public function getInfoById(int $id_g): array|false
    {
        $sql = <<<SQL
SELECT * FROM annuaire_groupe WHERE id_g=?
SQL;
        return $this->queryOne($sql, $id_g);
    }

    public function add(int $id_e, string $nom): int
    {
        $sql = <<<SQL
INSERT INTO annuaire_groupe (id_e,nom) VALUES (?,?)
SQL;
        $this->query($sql, $id_e, $nom);
        return (int) $this->lastInsertId();
    }

    public function getNbUtilisateur(int $id_g): int
    {
        $sql = <<<SQL
SELECT count(*) FROM annuaire_groupe_contact WHERE id_g=?
SQL;
        return (int) $this->queryOne($sql, $id_g);
    }

    public function getAllUtilisateur(int $id_g): array
    {
        $sql = <<<SQL
SELECT * FROM annuaire_groupe_contact
JOIN annuaire ON annuaire_groupe_contact.id_a=annuaire.id_a
WHERE id_g=?
SQL;
        return $this->query($sql, $id_g);
    }

    public function getUtilisateur(int $id_g, int $offset = 0, int $limit = 0): array
    {
        if ($limit === 0) {
            $limit = self::NB_MAX;
        }
        $sql = <<<SQL
SELECT * FROM annuaire_groupe_contact
JOIN annuaire ON annuaire_groupe_contact.id_a=annuaire.id_a
WHERE id_g=? ORDER BY annuaire.description ASC LIMIT $offset,$limit
SQL;
        return $this->query($sql, $id_g);
    }

    public function delete(int $id_e, int $id_g): void
    {
        $sql = <<<SQL
DELETE FROM annuaire_groupe_contact WHERE id_g=?
SQL;
        $this->query($sql, $id_g);
        $sql = <<<SQL
DELETE FROM annuaire_groupe WHERE id_e=? AND id_g=?
SQL;
        $this->query($sql, $id_e, $id_g);
    }

    public function isInGroupe(int $id_g, int $id_a): int
    {
        $sql = <<<SQL
SELECT count(*) FROM annuaire_groupe_contact WHERE id_g=? AND id_a=?
SQL;
        return (int) $this->queryOne($sql, $id_g, $id_a);
    }

    public function addToGroupe(int $id_g, int $id_a): void
    {
        if ($this->isInGroupe($id_g, $id_a)) {
            return;
        }
        $sql = <<<SQL
INSERT INTO annuaire_groupe_contact (id_g,id_a) VALUES (?,?)
SQL;
        $this->query($sql, $id_g, $id_a);
    }

    public function deleteFromAllGroupes(int $id_a): void
    {
        $sql = <<<SQL
DELETE FROM annuaire_groupe_contact WHERE id_a=?
SQL;
        $this->query($sql, $id_a);
    }

    public function deleteFromGroupe(int $id_g, array $id_aList): void
    {
        foreach ($id_aList as $id_a) {
            $sql = <<<SQL
DELETE FROM annuaire_groupe_contact WHERE id_g=? AND id_a=?
SQL;
            $this->query($sql, $id_g, $id_a);
        }
    }

    public function getListGroupe(int $id_e, string $debut): array
    {
        $sql = <<<SQL
SELECT nom FROM annuaire_groupe WHERE id_e=? AND nom LIKE ?
SQL;
        return $this->query($sql, $id_e, "$debut%");
    }

    public function tooglePartage(int $id_g): void
    {
        $sql = <<<SQL
UPDATE annuaire_groupe SET partage = 1 - partage WHERE id_g=?
SQL;
        $this->query($sql, $id_g);
    }

    public function getGroupeHerite(array $all_ancetre, string $debut = ''): array
    {
        $result = [];
        foreach ($all_ancetre as $id_e) {
            $sql = <<<SQL
SELECT annuaire_groupe.*,entite.denomination FROM annuaire_groupe
LEFT JOIN entite ON annuaire_groupe.id_e = entite.id_e
WHERE annuaire_groupe.id_e=? AND partage=1
SQL;
            $data = [$id_e];
            if ($debut) {
                $sql .= ' AND nom LIKE ?';
                $data[] = "$debut%";
            }
            $all_g = $this->query($sql, $data);
            if ($all_g) {
                $result = array_merge($result, $all_g);
            }
        }
        return $result;
    }

    public function getChaineHerited(array $info): string
    {
        if ($info['denomination']) {
            $debut = 'groupe hérité de ' . $info['denomination'];
        } else {
            $debut = 'groupe global';
        }

        return $debut . ': "' . $info['nom'] . '"';
    }

    public function getFromNomDenomination(array $all_ancetre, string $chaine): mixed
    {
        foreach ($this->getGroupeHerite($all_ancetre) as $info) {
            if ($chaine === $this->getChaineHerited($info)) {
                return $info['id_g'];
            }
        }
        return false;
    }

    public function hasAGroupe(int $id_a): int
    {
        $sql = <<<SQL
SELECT count(*) FROM annuaire_groupe_contact WHERE id_a=?
SQL;
        return (int) $this->queryOne($sql, $id_a);
    }

    public function deleteAllGroupFromContact(int $id_a): void
    {
        $sql = <<<SQL
DELETE FROM annuaire_groupe_contact WHERE id_a=?
SQL;
        $this->query($sql, $id_a);
    }

    public function getGroupeFromUtilisateur(int $id_a): array
    {
        $sql = <<<SQL
SELECT * FROM annuaire_groupe_contact
JOIN annuaire_groupe ON annuaire_groupe_contact.id_g=annuaire_groupe.id_g
WHERE id_a=?
ORDER BY nom
SQL;
        return $this->query($sql, $id_a);
    }

    public function getGroupeWithHasUtilisateur(int $id_e, int $id_a): array
    {
        $sql = <<<SQL
SELECT annuaire_groupe.*,annuaire_groupe_contact.id_a FROM annuaire_groupe
LEFT JOIN annuaire_groupe_contact
ON annuaire_groupe_contact.id_g=annuaire_groupe.id_g
AND annuaire_groupe_contact.id_a=?
WHERE id_e=?
ORDER BY nom ASC
SQL;
        return $this->query($sql, $id_a, $id_e);
    }
}
