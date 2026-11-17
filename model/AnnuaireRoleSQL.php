<?php

class AnnuaireRoleSQL extends SQL
{
    public function getAll(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM annuaire_role WHERE id_e_owner = ? ORDER BY nom ASC
SQL;
        return $this->query($sql, $id_e);
    }

    public function add(string $nom, int $id_e_owner, int $id_e, string $role): int
    {
        $sql = <<<SQL
INSERT INTO annuaire_role (nom, id_e_owner, id_e, role) VALUES (?, ?, ?, ?)
SQL;
        $this->query($sql, $nom, $id_e_owner, $id_e, $role);
        return (int)$this->lastInsertId();
    }

    public function getUtilisateur(int $id_r): array
    {
        $sql = <<<SQL
SELECT entite_ancetre.*, utilisateur_role.*, utilisateur.*
FROM annuaire_role
JOIN entite_ancetre ON entite_ancetre.id_e_ancetre = annuaire_role.id_e
JOIN utilisateur_role ON entite_ancetre.id_e = utilisateur_role.id_e AND utilisateur_role.role = annuaire_role.role
JOIN utilisateur ON utilisateur_role.id_u = utilisateur.id_u
WHERE annuaire_role.id_r = ?
SQL;
        return $this->query($sql, $id_r);
    }

    public function getInfo(int $id_r): array|false
    {
        $sql = <<<SQL
SELECT * FROM annuaire_role WHERE id_r = ?
SQL;
        return $this->queryOne($sql, $id_r);
    }

    public function delete(int $id_r): void
    {
        $sql = <<<SQL
DELETE FROM annuaire_role WHERE id_r = ?
SQL;
        $this->query($sql, $id_r);
    }

    public function getNbByRole(string $role): int
    {
        $sql = <<<SQL
SELECT count(*) FROM annuaire_role WHERE role = ?
SQL;
        return (int)$this->queryOne($sql, $role);
    }

    public function deleteByEntite(int $id_e): void
    {
        $sql = <<<SQL
DELETE FROM annuaire_role WHERE id_e_owner = ? OR id_e = ?
SQL;
        $this->query($sql, $id_e, $id_e);
    }

    public function getList(int $id_e, string $debut): array
    {
        $sql = <<<SQL
SELECT nom FROM annuaire_role WHERE id_e_owner = ? AND nom LIKE ?
SQL;
        return $this->query($sql, $id_e, "$debut%");
    }

    public function getFromNom(int $id_e, string $nom): int|false
    {
        $sql = <<<SQL
SELECT id_r FROM annuaire_role WHERE id_e_owner = ? AND nom = ?
SQL;
        return $this->queryOne($sql, $id_e, $nom);
    }

    public function partage(int $id_r): void
    {
        $sql = <<<SQL
UPDATE annuaire_role SET partage = 1 WHERE id_r = ?
SQL;
        $this->query($sql, $id_r);
    }

    public function unpartage(int $id_r): void
    {
        $sql = <<<SQL
UPDATE annuaire_role SET partage = 0 WHERE id_r = ?
SQL;
        $this->query($sql, $id_r);
    }

    public function getGroupeHerite(array $all_ancetre, string $debut = ''): array
    {
        $result = [];
        foreach ($all_ancetre as $id_e) {
            $sql = <<<SQL
SELECT annuaire_role.*, entite.denomination FROM annuaire_role
LEFT JOIN entite ON annuaire_role.id_e_owner = entite.id_e
WHERE annuaire_role.id_e_owner = ? AND partage = 1
SQL;
            $data = [$id_e];
            if ($debut) {
                $sql .= ' AND nom LIKE ?';
                $data[] = "$debut%";
            }
            $role_list = $this->query($sql, $data);
            if ($role_list) {
                $result = array_merge($result, $role_list);
            }
        }
        return $result;
    }

    /**
     * @deprecated 4.1.26 Use AnnuaireRoleService::getInheritedRoleLabel() instead
     */
    public function getChaineHerited(array $info): string
    {
        if ($info['denomination']) {
            $debut = 'rôle hérité de ' . $info['denomination'];
        } else {
            $debut = 'rôle global';
        }

        return $debut . ': "' . $info['nom'] . '"';
    }

    /**
     * @deprecated 4.1.26 Use AnnuaireRoleService::findInheritedRoleId() instead
     */
    public function getFromNomDenomination(array $all_ancetre, string $chaine): mixed
    {
        foreach ($this->getGroupeHerite($all_ancetre) as $info) {
            if ($chaine === $this->getChaineHerited($info)) {
                return $info['id_r'];
            }
        }
        return false;
    }
}
