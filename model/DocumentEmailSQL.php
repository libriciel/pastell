<?php

declare(strict_types=1);

class DocumentEmailSQL extends SQL
{
    public const ID_DE = 'id_de';

    public function insert(string $id_d, string $email, string $key, string $type_destinataire): void
    {
        $sql = <<<SQL
INSERT INTO document_email(id_d, email, `key`, date_envoie, type_destinataire) VALUES (?, ?, ?, now(), ?)
SQL;
        $this->query($sql, $id_d, $email, $key, $type_destinataire);
    }

    public function getKey(string $id_d, string $email): ?string
    {
        $sql = <<<SQL
SELECT `key` FROM document_email WHERE id_d = ? AND email = ?
SQL;
        return $this->queryOne($sql, $id_d, $email) ?: null;
    }

    public function getAllByDocument(string $id_d): array
    {
        $sql = <<<SQL
SELECT * FROM document_email WHERE id_d = ?
SQL;
        return $this->query($sql, $id_d);
    }

    public function getAllEmail(string $id_d): array
    {
        $sql = <<<SQL
SELECT email FROM document_email WHERE id_d = ? ORDER BY email
SQL;
        return $this->queryOneCol($sql, $id_d);
    }

    public function getAllRecipientIds(string $id_d): array
    {
        $sql = <<<SQL
SELECT id_de FROM document_email WHERE id_d = ? ORDER BY id_de
SQL;
        return $this->queryOneCol($sql, $id_d);
    }

    public function getByKey(string $key): ?array
    {
        $sql = <<<SQL
SELECT * FROM document_email WHERE `key` = ?
SQL;
        return $this->queryOne($sql, $key) ?: null;
    }

    public function getById(int $id_de): ?array
    {
        $sql = <<<SQL
SELECT * FROM document_email WHERE id_de = ?
SQL;
        return $this->queryOne($sql, $id_de) ?: null;
    }

    public function setError(int $id_de, string $body): void
    {
        $sql = <<<SQL
UPDATE document_email SET has_error = 1, last_error = ? WHERE id_de = ?
SQL;
        $this->query($sql, $body, $id_de);
    }

    public function isAllInError(string $id_d): bool
    {
        $sql = <<<SQL
SELECT count(*) = sum(has_error) FROM document_email WHERE id_d = ?
SQL;
        return (bool) $this->queryOne($sql, $id_d);
    }

    public function setRead(string $key): void
    {
        $sql = <<<SQL
UPDATE document_email SET lu = 1, date_lecture = now() WHERE `key` = ?
SQL;
        $this->query($sql, $key);
    }

    public function isAllRead(string $id_d): bool
    {
        $sql = <<<SQL
SELECT count(*) = sum(lu) FROM document_email WHERE id_d = ?
SQL;
        return (bool) $this->queryOne($sql, $id_d);
    }

    public function countRead(string $id_d): int
    {
        $sql = <<<SQL
SELECT count(*) FROM document_email WHERE id_d = ? AND lu = 1
SQL;
        return (int) $this->queryOne($sql, $id_d);
    }

    public function incrementResend(int $id_de): void
    {
        $sql = <<<SQL
UPDATE document_email SET date_renvoi = now(), nb_renvoi = nb_renvoi + 1 WHERE id_de = ?
SQL;
        $this->query($sql, $id_de);
    }

    public function setReponse(int $id_de, string $reponse): void
    {
        $sql = <<<SQL
UPDATE document_email SET reponse = ? WHERE id_de = ?
SQL;
        $this->query($sql, $reponse, $id_de);
    }

    public function setNotReceived(string $id_d): void
    {
        $sql = <<<SQL
UPDATE document_email SET non_recu = 1 WHERE id_d = ? AND lu = 0
SQL;
        $this->query($sql, $id_d);
    }
}
