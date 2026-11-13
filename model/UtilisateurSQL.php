<?php

use Pastell\Utilities\Certificate;

class UtilisateurSQL extends SQL
{
    public function setNomPrenom(int $id_u, string $nom, string $prenom): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET nom = ?, prenom = ? WHERE id_u = ?
SQL;
        $this->query($sql, $nom, $prenom, $id_u);
    }

    /**
     * Untyped parameter: legacy callers may pass null or a non-numeric string coming from the request.
     */
    public function getInfo($id_u): array|false
    {
        $sql = <<<SQL
SELECT * FROM utilisateur WHERE id_u = ?
SQL;
        return $this->queryOne($sql, $id_u);
    }

    public function exists(int $id_u): bool
    {
        return (bool)$this->getInfo($id_u);
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
    public function validMail(int $id_u, string $password): bool
    {
        $sql = <<<SQL
SELECT id_u FROM utilisateur WHERE id_u = ? AND mail_verif_password = ?
SQL;
        if (!$this->queryOne($sql, $id_u, $password)) {
            return false;
        }
        $this->validMailAuto($id_u);
        return true;
    }

    public function validMailAuto(int $id_u): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET mail_verifie = 1 WHERE id_u = ?
SQL;
        $this->query($sql, $id_u);
    }

    /**
     * @deprecated 4.1.26 Use UserPasswordService::verifyPassword() instead
     */
    public function verifPassword(int $id_u, string $password): bool
    {
        $user_info = $this->getInfo($id_u);
        return password_verify($password, $user_info['password']);
    }

    public function desinscription(int $id_u): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur WHERE id_u = ?
SQL;
        $this->query($sql, $id_u);
    }

    /**
     * @deprecated 4.1.26 Use UserPasswordService::setPassword() instead
     */
    public function setPassword(int $id_u, string $password): void
    {
        $this->updatePasswordHash($id_u, password_hash($password, PASSWORD_DEFAULT));
    }

    public function setEmail(int $id_u, string $email): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET email = ? WHERE id_u = ?
SQL;
        $this->query($sql, $email, $id_u);
    }

    public function setLogin(int $id_u, string $login): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET login = ? WHERE id_u = ?
SQL;
        $this->query($sql, $login, $id_u);
    }

    public function setColBase(int $id_u, int $id_e): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET id_e = ? WHERE id_u = ?
SQL;
        $this->query($sql, $id_e, $id_u);
    }

    public function removeCertificat(int $id_u): void
    {
        $this->updateCertificat($id_u, '', '');
    }

    /**
     * @deprecated 4.1.26 Use UserCertificateService::setCertificate() instead
     */
    public function setCertificat(int $id_u, Certificate $certificate): bool
    {
        if (!$certificate->isValid()) {
            return false;
        }
        $this->updateCertificat($id_u, $certificate->getContent(), $certificate->getMD5());
        return true;
    }

    public function updateCertificat(int $id_u, string $content, string $verif_number): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET certificat = ?, certificat_verif_number = ? WHERE id_u = ?
SQL;
        $this->query($sql, $content, $verif_number, $id_u);
    }

    public function reinitPassword(int $id_u, string $mail_verif_password): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET mail_verif_password = ?, password_reset_requested_at = ? WHERE id_u = ?
SQL;
        $this->query($sql, $mail_verif_password, date('c'), $id_u);
    }

    public function getIdFromLogin(string $login): int|false
    {
        $sql = <<<SQL
SELECT id_u FROM utilisateur WHERE login = ?
SQL;
        return $this->queryOne($sql, $login);
    }

    /**
     * @deprecated 4.1.26 Use UserCreationService::createAccount() instead
     */
    public function create(string $login, string $password, string $email, string $password_validation): int|false
    {
        $sql = <<<SQL
INSERT INTO utilisateur(login, email, mail_verif_password, date_inscription) VALUES (?, ?, ?, now())
SQL;
        $this->query($sql, $login, $email, $password_validation);
        $id_u = $this->getIdFromLogin($login);
        $this->setPassword($id_u, $password);
        return $id_u;
    }

    public function getInfoByLogin(string $login): array|false
    {
        $sql = <<<SQL
SELECT * FROM utilisateur WHERE login = ?
SQL;
        return $this->queryOne($sql, $login);
    }

    /**
     * @deprecated 4.1.26 Use UserInfoService::getUserFromData() instead
     * @throws NotFoundException
     * @throws Exception
     */
    public function getUserFromData(array $data): array
    {
        if (!empty($data['id_u'])) {
            $user_info = $this->getInfo($data['id_u']);
            if (!$user_info) {
                throw new NotFoundException("L'identifiant de l'utilisateur n'existe pas : {id_u={$data['id_u']}}");
            }
            return $user_info;
        }
        if (isset($data['login'])) {
            $user_info = $this->getInfoByLogin($data['login']);
            if (!$user_info) {
                throw new NotFoundException("Le login de l'utilisateur n'existe pas : {login={$data['login']}}");
            }
            return $user_info;
        }
        throw new Exception("Aucun paramètre permettant la recherche de l'utilisateur n'a été renseigné");
    }

    public function enable(int $id_u): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET is_enabled = ? WHERE id_u = ?
SQL;
        $this->query($sql, true, $id_u);
    }

    public function disable(int $id_u): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET is_enabled = ? WHERE id_u = ?
SQL;
        $this->query($sql, false, $id_u);
    }

    public function isEnabled(int $id_u): bool
    {
        $sql = <<<SQL
SELECT is_enabled FROM utilisateur WHERE id_u = ?
SQL;
        return $this->queryOne($sql, $id_u);
    }

    public function setIsAPI(int $id_u, bool $is_api): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET is_api = ? WHERE id_u = ?
SQL;
        $this->query($sql, $is_api, $id_u);
    }

    public function getAllByCertificat(string $verif_number, int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $sql = <<<SQL
SELECT * FROM utilisateur WHERE certificat_verif_number = ?
ORDER BY nom, prenom, login
LIMIT $offset, $limit
SQL;
        return $this->query($sql, $verif_number);
    }

    public function countByCertificat(string $verif_number): int
    {
        $sql = <<<SQL
SELECT count(*) FROM utilisateur WHERE certificat_verif_number = ?
SQL;
        return (int)$this->queryOne($sql, $verif_number);
    }

    public function getIdByLoginOrEmail(string $login_or_email): ?int
    {
        $sql = <<<SQL
SELECT id_u FROM utilisateur WHERE (login = ? OR email = ?) AND mail_verifie = 1
SQL;
        $id_u = $this->queryOne($sql, $login_or_email, $login_or_email);
        return $id_u === false ? null : (int)$id_u;
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
        return $id_u === false ? null : (int)$id_u;
    }

    public function getAllByBaseEntite(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM utilisateur WHERE id_e = ?
SQL;
        return $this->query($sql, $id_e);
    }

    public function countByBaseEntite(int $id_e): int
    {
        $sql = <<<SQL
SELECT count(*) FROM utilisateur WHERE id_e = ?
SQL;
        return (int)$this->queryOne($sql, $id_e);
    }

    public function countByEntite(int $id_e, bool $with_fille = false, string $role = '', string $search = ''): int
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
        return (int)$this->queryOne($sql, $data);
    }

    public function getAllByEntite(
        int $id_e,
        bool $with_fille = false,
        string $role = '',
        string $search = '',
        int $offset = 0,
        ?int $limit = null,
    ): array {
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
        if ($search) {
            $where .= ' AND (nom LIKE ? OR prenom LIKE ? OR login LIKE ?)';
            array_push($data, "%$search%", "%$search%", "%$search%");
        }
        $offset = max(0, $offset);
        $limit_clause = $limit === null ? '' : "LIMIT $offset, $limit";
        $sql = <<<SQL
SELECT utilisateur.id_u, nom, prenom, login, email, utilisateur.id_e,
    entite.denomination, utilisateur.is_enabled
FROM utilisateur
LEFT JOIN entite ON entite.id_e = utilisateur.id_e
JOIN utilisateur_role ON utilisateur.id_u = utilisateur_role.id_u
$join
WHERE $where
GROUP BY utilisateur.id_u
ORDER BY nom, prenom
$limit_clause
SQL;
        return $this->query($sql, $data);
    }

    public function updatePasswordHash(int $id_u, string $password_hash): void
    {
        $sql = <<<SQL
UPDATE utilisateur SET password = ? WHERE id_u = ?
SQL;
        $this->query($sql, $password_hash, $id_u);
    }

    public function insert(string $login, string $email, string $mail_verif_password): int
    {
        $sql = <<<SQL
INSERT INTO utilisateur(login, email, mail_verif_password, date_inscription) VALUES (?, ?, ?, now())
SQL;
        $this->query($sql, $login, $email, $mail_verif_password);
        return (int)$this->lastInsertId();
    }
}
