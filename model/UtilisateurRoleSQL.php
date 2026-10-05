<?php

class UtilisateurRoleSQL extends SQL
{
    public function insertRole($id_u, $role, $id_e): void
    {
        $sql = <<<SQL
INSERT INTO utilisateur_role(id_u,role,id_e) VALUES (?,?,?)
SQL;
        $this->query($sql, $id_u, $role, $id_e);
    }

    public function deleteRole($id_u, $role, $id_e): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur_role WHERE id_u=? AND role=? AND id_e=?
SQL;
        $this->query($sql, $id_u, $role, $id_e);
    }

    public function replaceRole($id_u, $role, $id_e, $newRole): void
    {
        $sql = <<<SQL
UPDATE utilisateur_role SET role=? WHERE id_u=? AND role=? AND id_e=?
SQL;
        $this->query($sql, $newRole, $id_u, $role, $id_e);
    }

    public function countRoles($id_u): int
    {
        $sql = <<<SQL
SELECT count(*) FROM utilisateur_role WHERE id_u=?
SQL;
        return (int) $this->queryOne($sql, $id_u);
    }

    public function hasRole($id_u, $role, $id_e): int
    {
        $sql = <<<SQL
SELECT count(*) FROM utilisateur_role WHERE id_u=? AND role=? AND id_e=?
SQL;
        return (int) $this->queryOne($sql, $id_u, $role, $id_e);
    }

    public function deleteAllRoles($id_u): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur_role WHERE id_u = ?
SQL;
        $this->query($sql, $id_u);
    }

    public function deleteRolesForEntite($id_u, $id_e): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur_role WHERE id_u = ? AND id_e = ?
SQL;
        $this->query($sql, $id_u, $id_e);
    }

    public function getAllDroitEntite($id_u, $id_e): array
    {
        $sql = <<<SQL
SELECT droit FROM entite_ancetre
JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e
JOIN role_droit ON utilisateur_role.role=role_droit.role
WHERE entite_ancetre.id_e=? AND utilisateur_role.id_u=?
SQL;
        $result = [];
        foreach ($this->query($sql, $id_e, $id_u) as $line) {
            $result[] = $line['droit'];
        }
        return $result;
    }

    public function getAllDroit($id_u): array
    {
        $sql = <<<SQL
SELECT droit FROM utilisateur_role
JOIN role_droit ON utilisateur_role.role=role_droit.role
WHERE utilisateur_role.id_u=?
SQL;
        $result = [];
        foreach ($this->query($sql, $id_u) as $line) {
            $result[] = $line['droit'];
        }
        return $result;
    }

    public function getRole($id_u): array
    {
        $sql = <<<SQL
SELECT utilisateur_role.*,denomination,siren,type FROM utilisateur_role
LEFT JOIN entite ON utilisateur_role.id_e=entite.id_e
WHERE id_u = ?
SQL;
        return $this->query($sql, $id_u);
    }

    public function getAllEntiteDroit($id_u, $id_e = false): array
    {
        $sql = <<<SQL
SELECT entite.id_e, droit FROM entite_ancetre
JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e
JOIN role_droit ON utilisateur_role.role=role_droit.role
JOIN entite ON entite_ancetre.id_e=entite.id_e
WHERE utilisateur_role.id_u=?
SQL;
        $data[] = $id_u;
        if ($id_e) {
            $sql .= ' AND entite.id_e=?';
            $data[] = $id_e;
        }
        $sql .= ' ORDER BY entite.id_e,droit';
        return $this->query($sql, $data);
    }

    public function getAllEntiteWithFille(int $id_u, string $droit, ?string $active): array
    {
        $sql = <<<SQL
SELECT DISTINCT entite.id_e,
                entite.denomination,
                entite.siren,
                entite.type,
                entite.centre_de_gestion,
                entite.entite_mere,
                entite.is_active
FROM entite_ancetre
JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e
JOIN role_droit ON utilisateur_role.role=role_droit.role
JOIN entite ON entite_ancetre.id_e=entite.id_e
WHERE utilisateur_role.id_u=? AND droit=?
SQL;
        $data[] = $id_u;
        $data[] = $droit;
        if ($active !== null) {
            $sql .= ' AND entite.is_active=?';
            $data[] = filter_var($active, FILTER_VALIDATE_BOOL);
        }
        $sql .= ' ORDER BY entite.id_e,droit;';
        return $this->query($sql, $data);
    }

    public function getArbreFille(int $id_u, string $droit): array
    {
        $sql = <<<SQL
SELECT DISTINCT entite.id_e,entite.denomination,entite.entite_mere
FROM entite_ancetre
    JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e
    JOIN role_droit ON utilisateur_role.role=role_droit.role
    JOIN entite ON entite_ancetre.id_e=entite.id_e
WHERE utilisateur_role.id_u=? AND droit=?
ORDER BY CAST(entite_mere AS UNSIGNED), denomination;
SQL;
        return $this->query($sql, $id_u, $droit);
    }

    public function getEntiteWithDenomination($id_u, $droit): array
    {
        $sql = <<<SQL
SELECT DISTINCT entite.id_e,denomination,siren,type, is_active
FROM utilisateur_role
JOIN role_droit ON utilisateur_role.role=role_droit.role
LEFT JOIN entite ON utilisateur_role.id_e=entite.id_e WHERE id_u = ?  AND droit=?
SQL;
        return $this->query($sql, $id_u, $droit);
    }

    public function getEntite($id_u, $droit): array
    {
        $sql = <<<SQL
SELECT DISTINCT utilisateur_role.id_e
FROM utilisateur_role
JOIN role_droit ON utilisateur_role.role=role_droit.role
LEFT JOIN entite ON utilisateur_role.id_e=entite.id_e
WHERE id_u = ?  AND droit=?
SQL;
        $result = [];
        foreach ($this->query($sql, $id_u, $droit) as $line) {
            $result[] = $line['id_e'];
        }
        return $result;
    }

    public function getEntiteWithAnyDroit($id_u): array
    {
        $sql = <<<SQL
SELECT DISTINCT utilisateur_role.id_e
FROM utilisateur_role
JOIN role_droit ON utilisateur_role.role=role_droit.role
LEFT JOIN entite ON utilisateur_role.id_e=entite.id_e
WHERE id_u = ?
SQL;
        $result = [];
        foreach ($this->query($sql, $id_u) as $line) {
            $result[] = $line['id_e'];
        }
        return $result;
    }

    public function anybodyHasRole($role): int
    {
        $sql = <<<SQL
SELECT count(*) FROM utilisateur_role WHERE role =?
SQL;
        return (int) $this->queryOne($sql, $role);
    }

    public function getChildrenWithAnyDroit(int $id_e_parent, int $id_u, string $excludedRole): array
    {
        $sql = <<<SQL
SELECT DISTINCT entite.* FROM entite
JOIN entite_ancetre ON entite.id_e=entite_ancetre.id_e
JOIN utilisateur_role ON entite_ancetre.id_e_ancetre=utilisateur_role.id_e
JOIN utilisateur ON utilisateur_role.id_u = utilisateur.id_u
WHERE entite.entite_mere=? AND utilisateur.id_u=? AND utilisateur_role.role <> ?
ORDER BY denomination
SQL;
        return $this->query($sql, $id_e_parent, $id_u, $excludedRole);
    }
}
