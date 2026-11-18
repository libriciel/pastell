<?php

class RoleSQL extends SQL
{
    public function getInfo($role)
    {
        $sql = <<<SQL
SELECT * FROM role WHERE role = ?
SQL;
        return $this->queryOne($sql, $role);
    }

    public function getAllRole()
    {
        $sql = <<<SQL
SELECT * FROM role ORDER BY libelle
SQL;
        return $this->query($sql);
    }

    public function exists(string $role): bool
    {
        $sql = <<<SQL
SELECT count(*) FROM role WHERE role = ?
SQL;
        return $this->queryOne($sql, $role) > 0;
    }

    public function insert(string $role, string $libelle): void
    {
        $sql = <<<SQL
INSERT INTO role (role, libelle) VALUES (?, ?)
SQL;
        $this->query($sql, $role, $libelle);
    }

    public function updateLibelle(string $role, string $libelle): void
    {
        $sql = <<<SQL
UPDATE role SET libelle = ? WHERE role = ?
SQL;
        $this->query($sql, $libelle, $role);
    }

    /**
     * @deprecated 4.1.26 Use RoleService::saveRole() instead
     */
    public function edit($role, $libelle)
    {
        $sql = "SELECT count(*) FROM role WHERE role=?";
        if ($this->queryOne($sql, $role)) {
            $sql = "UPDATE role SET libelle = ? WHERE role = ? ";
            $this->query($sql, $libelle, $role);
        } else {
            $sql = "INSERT INTO role (role,libelle) VALUES (?,?)";
            $this->query($sql, $role, $libelle);
        }
    }

    /**
     * @deprecated 4.1.26 Use RoleService::getDroitMap() instead
     */
    public function getDroit(array $allDroit, $role)
    {
        $result = array_fill_keys($allDroit, false);
        $sql = "SELECT * FROM role_droit WHERE role=? ORDER BY role_droit.droit";
        foreach ($this->query($sql, $role) as $line) {
            $result[$line['droit']] = true;
        }
        return $result;
    }

    public function getDroitList(string $role): array
    {
        $sql = <<<SQL
SELECT droit FROM role_droit WHERE role = ? ORDER BY droit
SQL;
        return $this->queryOneCol($sql, $role);
    }

    public function getAllRoleDroit(): array
    {
        $sql = <<<SQL
SELECT role, droit FROM role_droit
SQL;
        return $this->query($sql);
    }

    /**
     * @deprecated 4.1.26 Use RoleService::updateDroit() instead
     */
    public function updateDroit($role, array $lesDroits)
    {
        $sql = "DELETE FROM role_droit WHERE role=?";
        $this->query($sql, $role);
        foreach ($lesDroits as $droit) {
            $this->insertDroit($role, $droit);
        }
    }

    public function hasDroit(string $role, string $droit): bool
    {
        $sql = <<<SQL
SELECT count(*) FROM role_droit WHERE role = ? AND droit = ?
SQL;
        return $this->queryOne($sql, $role, $droit) > 0;
    }

    public function insertDroit(string $role, string $droit): void
    {
        $sql = <<<SQL
INSERT INTO role_droit (role, droit) VALUES (?, ?)
SQL;
        $this->query($sql, $role, $droit);
    }

    public function deleteAllDroit(string $role): void
    {
        $sql = <<<SQL
DELETE FROM role_droit WHERE role = ?
SQL;
        $this->query($sql, $role);
    }

    /**
     * @deprecated 4.1.26 Use RoleService::addDroit() instead
     */
    public function addDroit($role, $droit)
    {
        $sql = "SELECT count(*) FROM role_droit WHERE role=? AND droit=?";
        if ($this->queryOne($sql, $role, $droit)) {
            return;
        }
        $this->insertDroit($role, $droit);
    }

    public function delete($role)
    {
        $sql = <<<SQL
DELETE FROM role_droit WHERE role = ?
SQL;
        $this->query($sql, $role);
        $sql = <<<SQL
DELETE FROM role WHERE role = ?
SQL;
        $this->query($sql, $role);
    }

    /**
     * @deprecated 4.1.26 Use RoleDelegationService::getDelegableRoles() instead
     */
    public function getAuthorizedRoleToDelegate(array $droit_list)
    {
        $sql = "SELECT role FROM role";
        $role_list = array_flip($this->queryOneCol($sql));
        $sql = "SELECT * FROM role_droit";
        foreach ($this->query($sql) as $role_droit) {
            if (! in_array($role_droit['droit'], $droit_list)) {
                unset($role_list[$role_droit['role']]);
            }
        }
        return array_values(array_filter(array_flip($role_list)));
    }

    /**
     * @deprecated 4.1.26 Use RoleDelegationService::getDelegableRoles() instead
     */
    public function getRoleLibelle(array $role_id)
    {
        $result = [];
        foreach ($this->getAllRole() as $role) {
            if (in_array($role['role'], $role_id)) {
                $result[] = ['role' => $role['role'],'libelle' => $role['libelle']];
            }
        };
        return $result;
    }

    public function getRoleByDroit($droit)
    {
        $sql = <<<SQL
SELECT role FROM role_droit WHERE droit = ? ORDER BY role
SQL;
        return $this->queryOneCol($sql, $droit);
    }
}
