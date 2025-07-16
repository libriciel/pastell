<?php

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
}
