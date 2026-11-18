<?php

class UtilisateurNewEmailSQL extends SQL
{
    private $passwordGenerator;

    public function __construct(SQLQuery $sqlQuery, PasswordGenerator $passwordGenerator)
    {
        parent::__construct($sqlQuery);
        $this->passwordGenerator = $passwordGenerator;
    }

    /**
     * @deprecated 4.1.26 Use UserEmailChangeService::createRequest() instead
     */
    public function add($id_u, $email)
    {
        $this->delete($id_u);
        $password = $this->passwordGenerator->getPassword();
        $sql = "INSERT INTO utilisateur_new_email(id_u,email,password,date) VALUES (?,?,?,now())";
        $this->query($sql, $id_u, $email, $password);
        return $password;
    }

    /**
     * @deprecated 4.1.26 Use UserEmailChangeService::getValidRequest() instead
     */
    public function confirm($password)
    {
        $date = date("Y-m-d H:i:s", strtotime("-1 day"));
        $sql = "SELECT * FROM utilisateur_new_email WHERE password=? AND date > ?";
        return $this->queryOne($sql, $password, $date);
    }

    public function delete($id_u): void
    {
        $sql = <<<SQL
DELETE FROM utilisateur_new_email WHERE id_u = ?
SQL;
        $this->query($sql, $id_u);
    }

    public function insert(int $id_u, string $email, string $password, string $date): void
    {
        $sql = <<<SQL
INSERT INTO utilisateur_new_email(id_u, email, password, date) VALUES (?, ?, ?, ?)
SQL;
        $this->query($sql, $id_u, $email, $password, $date);
    }

    public function getByPasswordSince(string $password, string $min_date): ?array
    {
        $sql = <<<SQL
SELECT * FROM utilisateur_new_email WHERE password = ? AND date > ?
SQL;
        return $this->queryOne($sql, $password, $min_date) ?: null;
    }
}
