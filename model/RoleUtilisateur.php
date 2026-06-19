<?php

class RoleUtilisateur extends SQL
{
    public const AUCUN_DROIT = 'aucun droit';

    public const DROIT_EDITION = 'edition';
    public const DROIT_LECTURE = 'lecture';

    /** @var RoleSQL */
    private $roleSQL;

    private $memoryCache;

    private $cache_ttl_in_seconds;

    public function __construct(
        SQLQuery $sqlQuery,
        RoleSQL $roleSQL,
        MemoryCache $memoryCache,
        $cache_ttl_in_seconds
    ) {
        parent::__construct($sqlQuery);
        $this->roleSQL = $roleSQL;
        $this->memoryCache = $memoryCache;
        $this->cache_ttl_in_seconds = $cache_ttl_in_seconds;
    }

    public function getDroit($type_objet, $type_acces)
    {
        return sprintf("%s:%s", $type_objet, $type_acces);
    }

    public function getDroitLecture($type_objet)
    {
        return $this->getDroit($type_objet, self::DROIT_LECTURE);
    }

    public function getDroitEdition($type_objet)
    {
        return $this->getDroit($type_objet, self::DROIT_EDITION);
    }



    public function addRole($id_u, $role, $id_e)
    {
        $sql = "INSERT INTO utilisateur_role(id_u,role,id_e) VALUES (?,?,?)";
        $this->query($sql, $id_u, $role, $id_e);
        if ($role != RoleUtilisateur::AUCUN_DROIT) {
            $sql = "DELETE FROM utilisateur_role WHERE id_u=? AND role=? AND id_e=?";
            $this->query($sql, $id_u, RoleUtilisateur::AUCUN_DROIT, $id_e);
        }
        $this->deleteCache($id_e, $id_u);
        $this->deleteCache('all', $id_u);
    }

    public function hasRole($id_u, $role, $id_e)
    {
        $sql = "SELECT count(*) FROM utilisateur_role WHERE id_u=? AND role=? AND id_e=?";
        return $this->queryOne($sql, $id_u, $role, $id_e);
    }

    public function removeRole($id_u, $role, $id_e)
    {
        $sql = "SELECT count(*) FROM utilisateur_role WHERE id_u=? ";
        $nb_role = $this->queryOne($sql, $id_u);
        if ($nb_role == 1) {
            $sql = "UPDATE utilisateur_role SET role='" . RoleUtilisateur::AUCUN_DROIT . "' WHERE id_u = ? AND role = ? AND id_e = ?";
        } else {
            $sql = "DELETE FROM utilisateur_role WHERE id_u = ? AND role = ? AND id_e = ?";
        }
        $this->query($sql, $id_u, $role, $id_e);
        $this->deleteCache($id_e, $id_u);
    }

    public function deleteCache($id_e, $id_u)
    {
        $this->memoryCache->delete($this->getCacheKey($id_e, $id_u));
        $this->memoryCache->delete($this->getCacheKey('all', $id_u));
    }

    private function getCacheKey($id_e, $id_u)
    {
        return "pastell_role_utilisateur_{$id_u}_{$id_e}";
    }


        // A Utiliser pour la purge d'un utilisateur.
    public function removeAllRole($id_u)
    {
        $sql = "DELETE FROM utilisateur_role WHERE id_u = ?";
        $this->query($sql, $id_u);
        $this->deleteCache(0, $id_u);
        //TODO c'est incomplet, on a pas id_e/id_u
    }

    public function hasDroit($id_u, $droit, $id_e)
    {
        $allDroit = $this->getAllDroitEntite($id_u, $id_e);
        return in_array($droit, $allDroit);
    }


    public function getAllDocumentLecture($id_u, $id_e)
    {
        $liste_type = [];
        $allDroit = $this->getAllDroitEntite($id_u, $id_e);
        foreach ($allDroit as $droit) {
            if (preg_match('/^(.*):lecture$/', $droit, $result)) {
                $liste_type[] = $result[1];
            }
        }
        return $liste_type;
    }

    public function getAllDroitEntite($id_u, $id_e)
    {
        //Avec le cache REDIS en retenant 5 secondes
        $memory_key = $this->getCacheKey($id_e, $id_u);
        $result = $this->memoryCache->fetch($memory_key);
        if ($result) {
            return $result;
        }
        $allDroit[$id_u . "-" . $id_e] = [];
        $sql = "SELECT droit FROM entite_ancetre " .
            " JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e " .
            " JOIN role_droit ON utilisateur_role.role=role_droit.role " .
            " WHERE entite_ancetre.id_e=? AND utilisateur_role.id_u=? ";
        $sql_result = $this->query($sql, $id_e, $id_u);
        foreach ($sql_result as $line) {
            $allDroit[$id_u . "-" . $id_e][] = $line['droit'];
        }

        $data = $allDroit[$id_u . "-" . $id_e];
        $this->memoryCache->store(
            $memory_key,
            $data,
            $this->cache_ttl_in_seconds
        );
        return $data;
    }

    public function getRole($id_u)
    {
        $sql = "SELECT utilisateur_role.*,denomination,siren,type FROM utilisateur_role " .
                " LEFT JOIN entite ON utilisateur_role.id_e=entite.id_e " .
                " WHERE id_u = ?";
        return $this->query($sql, $id_u);
    }

    public function getAllDroit($id_u)
    {
        $memory_key = $this->getCacheKey('all', $id_u);
        $result = $this->memoryCache->fetch($memory_key);
        if ($result) {
            return $result;
        }
        $allDroit[$id_u] = [];
        $sql = "SELECT droit FROM  utilisateur_role " .
            " JOIN role_droit ON utilisateur_role.role=role_droit.role " .
            " WHERE  utilisateur_role.id_u=? ";
        foreach ($this->query($sql, $id_u) as $line) {
            $allDroit[$id_u][] = $line['droit'];
        }
        $this->memoryCache->store(
            $memory_key,
            $allDroit[$id_u],
            $this->cache_ttl_in_seconds
        );
        return $allDroit[$id_u];
    }

    /**
     * Vérifie qu'un utilisateur dispose d'au moins du droit unitaire sur une entité quelconque
     * @param $id_u
     * @param $droit
     * @return bool
     */
    public function hasOneDroit($id_u, $droit)
    {
        $allDroit = $this->getAllDroit($id_u);
        return in_array($droit, $allDroit);
    }

    private function linearizeTab($all)
    {
        $result = [];
        while (count($all) > 0) {
            $result = array_merge($result, $this->linearizeTabRecursif(0, $all, 0));
        }
        return $result;
    }

    private function linearizeTabRecursif($id_e, &$all, $profondeur)
    {
        $result = [];
        if (empty($all[$id_e])) {
            $id_e = array_keys($all)[0];
        }
        foreach ($all[$id_e] as $line) {
            $line['profondeur'] = $profondeur;
            $result[] = $line;
            if (isset($all[$line['id_e']])) {
                $result = array_merge($result, $this->linearizeTabRecursif($line['id_e'], $all, $profondeur + 1));
            }
        }
        unset($all[$id_e]);
        return $result;
    }

    public function getAllEntiteDroit($id_u, $id_e = false)
    {
        $sql = "SELECT  entite.id_e, droit FROM entite_ancetre " .
            " JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e " .
            " JOIN role_droit ON utilisateur_role.role=role_droit.role " .
            " JOIN entite ON entite_ancetre.id_e=entite.id_e " .
            " WHERE utilisateur_role.id_u=? ";

        $data[] = $id_u;
        if ($id_e) {
            $sql .= " AND entite.id_e=? ";
            $data[] = $id_e;
        }

        $sql .= " ORDER BY entite.id_e,droit";
        return $this->query($sql, $data);
    }

    public function getAllEntiteWithFille(int $id_u, string $droit, ?string $active): array
    {
        $sql = <<<EOT
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
EOT;
        $data[] = $id_u;
        $data[] = $droit;
        if ($active !== null) {
            $sql .= ' AND entite.is_active=?';
            $data[] = filter_var($active, FILTER_VALIDATE_BOOL);
        }

        $sql .= ' ORDER BY entite.id_e,droit;';

        return $this->query($sql, $data);
    }

    public function getArbreFille(int $userId, string $permission): array
    {
        $query = <<<SQL
SELECT DISTINCT entite.id_e,entite.denomination,entite.entite_mere
FROM entite_ancetre
    JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e
    JOIN role_droit ON utilisateur_role.role=role_droit.role
    JOIN entite ON entite_ancetre.id_e=entite.id_e
WHERE utilisateur_role.id_u=? AND droit=?
ORDER BY CAST(entite_mere AS UNSIGNED), denomination;
SQL;
        $db_result = $this->query($query, $userId, $permission);
        $result = [];

        foreach ($db_result as $line) {
            $result[$line['entite_mere']][] = [
                'id_e' => $line['id_e'],
                'denomination' => $line['denomination'],
            ];
        }

        return $this->linearizeTab($result);
    }

    public function getArbreFilleWithRacine(int $userId, string $permission): array
    {
        $arbre = $this->getArbreFille($userId, $permission);
        if ($this->hasDroit($userId, $permission, EntiteSQL::ID_E_ENTITE_RACINE)) {
            array_unshift($arbre, [
                'id_e' => EntiteSQL::ID_E_ENTITE_RACINE,
                'denomination' => EntiteSQL::ENTITE_RACINE_DENOMINATION,
                'profondeur' => 0,
            ]);
        }
        return $arbre;
    }

    public function getEntiteWithDenomination($id_u, $droit)
    {
        $sql = "SELECT DISTINCT entite.id_e,denomination,siren,type, is_active " .
                " FROM utilisateur_role " .
                " JOIN role_droit ON utilisateur_role.role=role_droit.role " .
                " LEFT JOIN entite ON utilisateur_role.id_e=entite.id_e WHERE id_u = ?  AND droit=?";
        return $this->query($sql, $id_u, $droit);
    }


    public function getEntite($id_u, $droit)
    {

        $sql = "SELECT  DISTINCT utilisateur_role.id_e " .
                " FROM utilisateur_role " .
                " JOIN role_droit ON utilisateur_role.role=role_droit.role " .
                " LEFT JOIN entite ON utilisateur_role.id_e=entite.id_e " .
                " WHERE id_u = ?  AND droit=? ";
        $result = [];
        foreach ($this->query($sql, $id_u, $droit) as $line) {
            $result[] = $line['id_e'];
        }
        return $result;
    }

    public function getEntiteWithSomeDroit($id_u)
    {
        $sql = "SELECT  DISTINCT utilisateur_role.id_e " .
                " FROM utilisateur_role " .
                " JOIN role_droit ON utilisateur_role.role=role_droit.role " .
                " LEFT JOIN entite ON utilisateur_role.id_e=entite.id_e " .
                " WHERE id_u = ?  ";
        $result = [];
        foreach ($this->query($sql, $id_u) as $line) {
            $result[] = $line['id_e'];
        };
        return $result;
    }

    public function hasManyEntite($id_u, $role)
    {
        if ($this->hasDroit($id_u, $role, 0)) {
            return true;
        }
        $sql = "SELECT count(distinct(id_e)) FROM utilisateur_role WHERE id_u = ?";

        $nb_entite = $this->queryOne($sql, $id_u);
        return ($nb_entite > 1);
    }

    public function getAllUtilisateur($id_e, $role)
    {
        $sql = "SELECT * FROM utilisateur_role " .
                " JOIN utilisateur ON utilisateur_role.id_u = utilisateur.id_u " .
                " WHERE utilisateur_role.id_e=? AND role =?";
        return $this->query($sql, $id_e, $role);
    }

    public function getAllUtilisateurHerite($id_e, $role)
    {
        $sql = "SELECT * FROM entite_ancetre " .
                " JOIN utilisateur_role ON entite_ancetre.id_e=utilisateur_role.id_e " .
                " JOIN utilisateur ON utilisateur_role.id_u = utilisateur.id_u " .
                " WHERE entite_ancetre.id_e_ancetre=? AND role =?"
        ;
        return $this->query($sql, $id_e, $role);
    }

    public function getAllUtilisateurWithDroit($id_e, $droit)
    {
        $sql = "SELECT * FROM entite_ancetre " .
                " JOIN utilisateur_role ON entite_ancetre.id_e_ancetre=utilisateur_role.id_e " .
                " JOIN utilisateur ON utilisateur_role.id_u = utilisateur.id_u " .
                " JOIN role_droit ON role_droit.role = utilisateur_role.role " .
                " WHERE entite_ancetre.id_e=? AND droit =?"
        ;
        return $this->query($sql, $id_e, $droit);
    }

    public function anybodyHasRole($role)
    {
        $sql = "SELECT count(*) FROM utilisateur_role " .
                " WHERE role =?";
        return $this->queryOne($sql, $role);
    }

    public function getAllRoles()
    {
        $sql = "SELECT * FROM role";
        return $this->query($sql);
    }

    public function removeAllRolesEntite($id_u, $id_e)
    {
        $sql = "DELETE FROM utilisateur_role WHERE id_u = ? AND id_e = ?";
        $this->query($sql, $id_u, $id_e);

        $this->addRole($id_u, RoleUtilisateur::AUCUN_DROIT, $id_e);
    }

    public function getAuthorizedRoleToDelegate($id_u)
    {
        $droit_list = $this->getAllDroit($id_u);
        $role_list = $this->roleSQL->getAuthorizedRoleToDelegate($droit_list);
        return $this->roleSQL->getRoleLibelle($role_list);
    }

    public function getChildrenWithPermission(int $id_e_parent, int $id_u): array
    {
        $sql = "SELECT DISTINCT entite.* FROM entite " .
            " JOIN entite_ancetre ON entite.id_e=entite_ancetre.id_e " .
            " JOIN utilisateur_role ON entite_ancetre.id_e_ancetre=utilisateur_role.id_e " .
            " JOIN utilisateur ON utilisateur_role.id_u = utilisateur.id_u " .
            " WHERE entite.entite_mere=? AND utilisateur.id_u=? AND utilisateur_role.role NOT LIKE 'aucun droit'" .
            " ORDER BY denomination";
        return $this->query($sql, $id_e_parent, $id_u);
    }
}
