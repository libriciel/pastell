<?php

use Pastell\Utilities\Certificate;

class UtilisateurSQL extends SQL
{
    public function setNomPrenom($id_u, $nom, $prenom)
    {
        $sql = "UPDATE utilisateur SET nom = ? , prenom = ? WHERE id_u = ?";
        $this->query($sql, [$nom,$prenom,$id_u]);
    }

    public function getInfo($id_u)
    {
        $sql = "SELECT * FROM utilisateur WHERE id_u = ?";
        return $this->queryOne($sql, $id_u);
    }

    public function exists(int $id_u): bool
    {
        return (bool) $this->getInfo($id_u);
    }

    public function validMail($id_u, $password)
    {
        $sql = "SELECT id_u FROM utilisateur " .
                " WHERE id_u =? AND mail_verif_password= ? ";
        $result = $this->queryOne($sql, $id_u, $password);
        if (! $result) {
            return false;
        }
        $this->validMailAuto($id_u);
        return true;
    }

    public function validMailAuto($id_u)
    {
        $sql = "UPDATE utilisateur SET mail_verifie=1 WHERE id_u=?";
        $this->query($sql, $id_u);
    }

    public function verifPassword($id_u, $password)
    {
        $info = $this->getInfo($id_u);
        return password_verify($password, $info['password']);
    }

    public function desinscription($id_u)
    {
        $sql = "DELETE FROM utilisateur WHERE id_u=?";
        $this->query($sql, $id_u);
    }

    public function setPassword($id_u, $password)
    {
        $sql = "UPDATE utilisateur SET password = ? WHERE id_u = ?";
        $this->query($sql, password_hash($password, PASSWORD_DEFAULT), $id_u);
    }

    public function setEmail($id_u, $email)
    {
        $sql = "UPDATE utilisateur SET email = ? WHERE id_u = ?";
        $this->query($sql, $email, $id_u);
    }

    public function setLogin($id_u, $login)
    {
        $sql = "UPDATE utilisateur SET login = ? WHERE id_u = ?";
        $this->query($sql, $login, $id_u);
    }

    public function setColBase($id_u, $id_e)
    {
        $sql = "UPDATE utilisateur SET id_e = ? WHERE id_u = ?";
        $this->query($sql, $id_e, $id_u);
    }

    public function removeCertificat($id_u)
    {
        $this->updateCertificat($id_u, "", "");
    }

    public function setCertificat($id_u, Certificate $certificat)
    {

        if (! $certificat->isValid()) {
            return false;
        }

        $certificatContent = $certificat->getContent();
        $certificatVerifNumber = $certificat->getMD5();

        $this->updateCertificat($id_u, $certificatContent, $certificatVerifNumber);
        return true;
    }

    private function updateCertificat($id_u, $content, $verif_number)
    {
        $sql = "UPDATE utilisateur SET certificat = ?, certificat_verif_number=? WHERE id_u = ?";
        $this->query($sql, $content, $verif_number, $id_u);
    }

    public function reinitPassword($id_u, $mailVerifPassword)
    {
        $now = date("c");
        $sql = "UPDATE utilisateur SET mail_verif_password=?, password_reset_requested_at=? WHERE id_u=?";
        $this->query($sql, $mailVerifPassword, $now, $id_u);
    }

    public function getIdFromLogin($login)
    {
        $sql = "SELECT id_u FROM utilisateur WHERE login = ?";
        return $this->queryOne($sql, $login);
    }

    public function create($login, $password, $email, $password_validation)
    {
        $sql = "INSERT INTO utilisateur(login,email,mail_verif_password,date_inscription) " .
                " VALUES (?,?,?,now())";
        $this->query($sql, $login, $email, $password_validation);
        $id_u =  $this->getIdFromLogin($login);
        $this->setPassword($id_u, $password);
        return $id_u;
    }

    public function getInfoByLogin($login)
    {
        $sql = "SELECT * FROM utilisateur WHERE login = ?";
        return $this->queryOne($sql, $login);
    }

    public function getUserFromData($data)
    {
        if (! empty($data['id_u'])) {
            $infoUtilisateurExistant = $this->getInfo($data['id_u']);
            if (!$infoUtilisateurExistant) {
                throw new NotFoundException("L'identifiant de l'utilisateur n'existe pas : {id_u={$data['id_u']}}");
            }
            return $infoUtilisateurExistant;
        }
        if (isset($data['login'])) {
            $infoUtilisateurExistant = $this->getInfoByLogin($data['login']);
            if (!$infoUtilisateurExistant) {
                throw new NotFoundException("Le login de l'utilisateur n'existe pas : {login={$data['login']}}");
            }
            return $infoUtilisateurExistant;
        }
        throw new Exception("Aucun paramètre permettant la recherche de l'utilisateur n'a été renseigné");
    }

    public function enable(int $id_u): void
    {
        $sql = 'UPDATE utilisateur SET is_enabled = ? WHERE id_u = ?';
        $this->query($sql, true, $id_u);
    }

    public function disable(int $id_u): void
    {
        $sql = 'UPDATE utilisateur SET is_enabled = ? WHERE id_u = ?';
        $this->query($sql, false, $id_u);
    }

    public function isEnabled(int $id_u): bool
    {
        $sql = 'SELECT is_enabled FROM utilisateur WHERE id_u = ?';
        return $this->queryOne($sql, $id_u);
    }

    public function setIsAPI(int $id_u, bool $is_api): void
    {
        $sql = 'UPDATE utilisateur SET is_api = ? WHERE id_u = ?';
        $this->query($sql, $is_api, $id_u);
    }

    public function getAllByCertificat(string $verif_number, int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $sql = <<<SQL
SELECT * FROM utilisateur WHERE certificat_verif_number = ?
ORDER BY nom,prenom,login
LIMIT $offset,$limit
SQL;
        return $this->query($sql, $verif_number);
    }

    public function countByCertificat(string $verif_number): int
    {
        $sql = <<<SQL
SELECT count(*) FROM utilisateur WHERE certificat_verif_number = ?
SQL;
        return (int) $this->queryOne($sql, $verif_number);
    }

    public function getIdByLoginOrEmail(string $loginOrEmail): ?int
    {
        $sql = <<<SQL
SELECT id_u FROM utilisateur WHERE (login = ? OR email = ?) AND mail_verifie = 1
SQL;
        $id_u = $this->queryOne($sql, $loginOrEmail, $loginOrEmail);
        return $id_u === false ? null : (int) $id_u;
    }

    public function getIdByVerifPassword(string $mail_verif_password, int $token_ttl_in_second = 1800): ?int
    {
        if ($mail_verif_password === '') {
            return null;
        }
        $sql = <<<SQL
SELECT id_u FROM utilisateur
WHERE mail_verif_password = ? AND mail_verifie = 1
AND TIMESTAMPDIFF(SECOND, password_reset_requested_at, ?) < ?
SQL;
        $id_u = $this->queryOne($sql, $mail_verif_password, date('c'), $token_ttl_in_second);
        return $id_u === false ? null : (int) $id_u;
    }

    public function getAllByBaseEntite(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM utilisateur WHERE id_e = ?
SQL;
        return $this->query($sql, $id_e);
    }

    public function countByEntiteDeBase(int $id_e): int
    {
        $sql = <<<SQL
SELECT count(*) FROM utilisateur WHERE id_e = ?
SQL;
        return (int) $this->queryOne($sql, $id_e);
    }

    public function countByEntite(int $id_e, bool $with_fille = false, string $role = '', string $search = ''): int
    {
        [$join, $where, $data] = $this->getEntiteFilter($id_e, $with_fille, $role);
        if ($search) {
            $where .= ' AND (nom LIKE ? OR prenom LIKE ? OR login LIKE ?)';
            array_push($data, "%$search%", "%$search%", "%$search%");
        }
        $sql = <<<SQL
SELECT count(DISTINCT utilisateur.id_u) FROM utilisateur
JOIN utilisateur_role ON utilisateur.id_u = utilisateur_role.id_u
$join
WHERE $where
SQL;
        return (int) $this->queryOne($sql, $data);
    }

    public function getAllByEntite(
        int $id_e,
        bool $with_fille = false,
        string $role = '',
        string $search = '',
        int $offset = 0,
        ?int $limit = null,
    ): array {
        [$join, $where, $data] = $this->getEntiteFilter($id_e, $with_fille, $role);
        $having = '';
        if ($search) {
            $having = 'HAVING nom LIKE ? OR prenom LIKE ? OR login LIKE ?';
            array_push($data, "%$search%", "%$search%", "%$search%");
        }
        $offset = max(0, $offset);
        $limitClause = $limit === null ? '' : "LIMIT $offset,$limit";
        $sql = <<<SQL
SELECT utilisateur.id_u,nom,prenom,login,email,utilisateur.id_e,
    entite.denomination,utilisateur.is_enabled
FROM utilisateur
LEFT JOIN entite ON entite.id_e = utilisateur.id_e
JOIN utilisateur_role ON utilisateur.id_u = utilisateur_role.id_u
$join
WHERE $where
GROUP BY utilisateur.id_u
$having
ORDER BY nom,prenom
$limitClause
SQL;
        return $this->query($sql, $data);
    }

    private function getEntiteFilter(int $id_e, bool $with_fille, string $role): array
    {
        $join = '';
        $where = 'utilisateur_role.id_e = ?';
        if ($with_fille) {
            $join = 'JOIN entite_ancetre ON utilisateur_role.id_e = entite_ancetre.id_e';
            $where = 'entite_ancetre.id_e_ancetre = ?';
        }
        $data = [$id_e];
        if ($role) {
            $where .= ' AND utilisateur_role.role = ?';
            $data[] = $role;
        }
        return [$join, $where, $data];
    }
}
