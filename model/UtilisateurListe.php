<?php

class UtilisateurListe extends SQL
{
    public const NB_UTILISATEUR_DISPLAY = 50;

    public function getUtilisateurByLogin($login)
    {
        $sql = "SELECT id_u FROM utilisateur WHERE login = ?";
        return $this->queryOne($sql, $login);
    }

    public function getByLoginOrEmail($login, $email)
    {
        $sql = "SELECT id_u FROM utilisateur WHERE (login = ? OR email=?) AND mail_verifie=1";
        return $this->queryOne($sql, $login, $email);
    }

    public function getByVerifPassword(string $mail_verif_password, int $token_ttl_in_second = 1800)
    {
        if ($mail_verif_password === '') {
            return false;
        }
        $now = date("c");
        $sql = "SELECT id_u FROM utilisateur " .
            "WHERE mail_verif_password = ?  AND mail_verifie=1 AND TIMESTAMPDIFF(SECOND,password_reset_requested_at,?) < ?";
        return $this->queryOne($sql, $mail_verif_password, $now, $token_ttl_in_second);
    }

    public function getNbUtilisateur($id_e, $with_fille = false, $role = false, $search = false)
    {

        $sql = "SELECT count(DISTINCT utilisateur.id_u)" .
                " FROM utilisateur " .
                " JOIN utilisateur_role ON utilisateur.id_u=utilisateur_role.id_u ";

        if ($with_fille) {
                $sql .= " JOIN entite_ancetre ON utilisateur_role.id_e=entite_ancetre.id_e " .
                " WHERE entite_ancetre.id_e_ancetre=?";
                $data = [$id_e];
        } else {
            $sql .= "WHERE utilisateur_role.id_e=?";
            $data = [$id_e];
        }
        if ($role) {
            $sql .= "AND utilisateur_role.role = ?";
            $data[] = $role;
        }
        if ($search) {
            $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR login LIKE ?)";
            $data[]  = "%$search%";
            $data[]  = "%$search%";
            $data[]  = "%$search%";
        }

        return $this->queryOne($sql, $data);
    }

    public function getAllUtilisateur($id_e, $with_fille = false, $role = false, $search = false, $offset = 0): array
    {
        $sql = 'SELECT utilisateur.id_u,nom,prenom,login,utilisateur_role.role,' .
                ' email,utilisateur.id_e,entite.denomination, utilisateur.is_enabled ' .
                ' FROM utilisateur ' .
                ' LEFT JOIN entite ON entite.id_e=utilisateur.id_e ' .
                ' JOIN utilisateur_role ON utilisateur.id_u=utilisateur_role.id_u ';

        if ($with_fille) {
                $sql .= ' JOIN entite_ancetre ON utilisateur_role.id_e=entite_ancetre.id_e ' .
                    ' WHERE entite_ancetre.id_e_ancetre=?';
                $data = [$id_e];
        } else {
            $sql .= 'WHERE utilisateur_role.id_e=?';
            $data = [$id_e];
        }
        if ($role) {
            $sql .= 'AND utilisateur_role.role = ?';
            $data[] = $role;
        }
        $sql .= ' GROUP BY utilisateur.id_u ';
        if ($search) {
            $sql .= ' HAVING nom LIKE ? OR prenom LIKE ? OR login LIKE ?';
            $data[]  = "%$search%";
            $data[]  = "%$search%";
            $data[]  = "%$search%";
        }
        $sql .= ' ORDER BY nom,prenom ';
        if ($offset != -1) {
            $sql .= " LIMIT $offset," . self::NB_UTILISATEUR_DISPLAY;
        }

        $all = $this->query($sql, $data);

        $sql = 'SELECT utilisateur_role.*,entite.denomination, entite.entite_mere, role.libelle ' .
                ' FROM utilisateur_role ' .
                ' LEFT JOIN entite on utilisateur_role.id_e=entite.id_e ' .
                ' LEFT JOIN role ON utilisateur_role.role = role.role' .
                ' WHERE id_u=? ';
        foreach ($all as $i => $utilisateur) {
            $all[$i]['all_role'] = $this->query($sql, $utilisateur['id_u']);
        }
        return $all;
    }

        // AJout de cette méthode pour l'API qui liste les utilisateurs
    public function getAllUtilisateurSimple(?int $id_e = null, bool $with_fille = false): array
    {
        $sql = <<<SQL
SELECT utilisateur.* FROM utilisateur
SQL;
        if (isset($id_e)) {
            if ($with_fille) {
                $sql .= <<<SQL
 JOIN entite_ancetre ON utilisateur.id_e = entite_ancetre.id_e
 WHERE entite_ancetre.id_e_ancetre = ?;
SQL;
            } else {
                $sql .= <<<SQL
 WHERE id_e = ?;
SQL;
            }
            return $this->query($sql, $id_e);
        }
        return $this->query($sql);
    }

    public function getNbUtilisateurWithEntiteDeBase($id_e)
    {
        $sql = "SELECT count(*) FROM utilisateur WHERE id_e=?";
        return $this->queryOne($sql, $id_e);
    }
}
