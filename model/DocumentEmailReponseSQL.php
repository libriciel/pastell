<?php

/**
 * Class DocumentEmailReponseSQL
 *
 * Dans un premier temps, il y a une relation document_email 1-1 document_email_reponse
 * (c'est à dire qu'on a qu'une seule réponse par mail sécurisé)
 * Il est a peu prêt certain qu'il y aura une demande pour mettre "plusieurs" réponse, d'ou le choix de ne pas mettre
 * les infos de la réponse directement dans document_email
 */
class DocumentEmailReponseSQL extends SQL
{
    public function getDocumentReponseId($id_de)
    {
        $sql = "SELECT id_d_reponse FROM document_email_reponse WHERE id_de=?";
        return $this->queryOne($sql, $id_de);
    }

    public function addDocumentReponseId($id_de, $id_d_reponse)
    {
        $sql = "INSERT INTO document_email_reponse(id_de, id_d_reponse) VALUES (?,?)";
        $this->query($sql, $id_de, $id_d_reponse);
    }

    public function getInfo($id_de)
    {
        $sql = "SELECT * FROM document_email_reponse WHERE id_de=?";
        return $this->queryOne($sql, $id_de);
    }

    public function validateReponse($id_de)
    {
        $sql = "UPDATE document_email_reponse SET has_reponse=true,date_reponse=?,has_date_reponse=true  WHERE id_de=?";
        $this->query($sql, $this->getNow(), $id_de);
    }

    public function getAllReponse($id_d, $validated = true): array
    {
        $sql = <<<SQL
SELECT document_email_reponse.id_de,document_email_reponse.id_d_reponse, is_lu,
       document_email_reponse.date_reponse,document_email_reponse.has_date_reponse, document.titre
FROM document_email
JOIN document_email_reponse ON document_email.id_de = document_email_reponse.id_de
JOIN document ON document_email_reponse.id_d_reponse=document.id_d
WHERE document_email.id_d=? AND document_email_reponse.has_reponse=?;
SQL;
        $result = [];
        foreach ($this->query($sql, $id_d, $validated) as $line) {
            $result[$line['id_de']] = $line;
        }
        return $result;
    }

    public function getInfoFromIdReponse($id_d_reponse)
    {
        $sql = "SELECT * FROM document_email_reponse WHERE id_d_reponse=?";
        return $this->queryOne($sql, $id_d_reponse);
    }

    public function setLu($id_d_reponse)
    {
        $sql = "UPDATE document_email_reponse SET is_lu=true WHERE id_d_reponse=?";
        $this->query($sql, $id_d_reponse);
    }

    public function getNumberOfAnsweredMail(string $id_d): int
    {
        $sql = <<<EOT
SELECT count(*)
FROM document_email_reponse
INNER JOIN document_email ON document_email_reponse.id_de = document_email.id_de
WHERE id_d= ?;
EOT;

        return $this->queryOne($sql, $id_d);
    }
}
