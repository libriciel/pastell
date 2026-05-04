<?php

class UtilisateurNewEmailSQL extends SQL
{
    private $passwordGenerator;

    public function __construct(SQLQuery $sqlQuery, PasswordGenerator $passwordGenerator)
    {
        parent::__construct($sqlQuery);
        $this->passwordGenerator = $passwordGenerator;
    }

    public function add($id_u, $email)
    {
        $this->delete($id_u);
        $password = $this->passwordGenerator->getPassword();
        $sql = "INSERT INTO utilisateur_new_email(id_u,email,password,date) VALUES (?,?,?,?)";
        $this->query($sql, $id_u, $email, $password, $this->getNow());
        return $password;
    }

    public function confirm($password)
    {
        $date = date("Y-m-d H:i:s", strtotime("-1 day"));
        $sql = "SELECT * FROM utilisateur_new_email WHERE password=? AND date > ?";
        return $this->queryOne($sql, $password, $date);
    }

    public function delete($id_u): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur_new_email WHERE id_u=?;
SQL;
        $this->query($sql, $id_u);
    }
}
