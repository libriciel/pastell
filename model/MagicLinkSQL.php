<?php

declare(strict_types=1);

final class MagicLinkSQL extends SQL
{
    private const string TOKEN_HASH_ALGORITHM = 'sha256';

    public function create(
        string $id,
        int $id_u,
        string $token,
        string $code,
        string $motif,
        int $createdBy,
        string $expiresAt,
        string $titulaireNom,
        string $titulairePrenom,
        string $titulaireEmail
    ): string {
        $query = <<<SQL
INSERT INTO magic_link(id, id_u, token, code, motif, created_by, created_at, expires_at,
                       titulaire_nom, titulaire_prenom, titulaire_email)
VALUES(?,?,?,?,?,?,?,?,?,?,?);
SQL;

        $this->query(
            $query,
            $id,
            $id_u,
            $this->getHashedToken($token),
            $code,
            $motif,
            $createdBy,
            $this->getNow(),
            $expiresAt,
            $titulaireNom,
            $titulairePrenom,
            $titulaireEmail
        );

        return $id;
    }

    public function getActive(): array
    {
        $query = <<<SQL
SELECT magic_link.id, magic_link.id_u, magic_link.motif, magic_link.created_by,
       magic_link.created_at, magic_link.expires_at, creator.login AS created_by_login,
       magic_link.titulaire_prenom, magic_link.titulaire_nom, magic_link.titulaire_email,
       magic_link.code
FROM magic_link
LEFT JOIN utilisateur creator ON magic_link.created_by = creator.id_u
WHERE magic_link.revoked_at IS NULL AND magic_link.expires_at > ?
ORDER BY magic_link.expires_at ASC;
SQL;

        return $this->query($query, $this->getNow());
    }

    public function getHistory(string $search = ''): array
    {
        $query = <<<SQL
SELECT magic_link.id, magic_link.id_u, magic_link.motif, magic_link.created_by,
       magic_link.created_at, magic_link.expires_at, magic_link.revoked_at,
       creator.login AS created_by_login,
       magic_link.titulaire_prenom, magic_link.titulaire_nom, magic_link.titulaire_email
FROM magic_link
LEFT JOIN utilisateur creator ON magic_link.created_by = creator.id_u
WHERE (magic_link.revoked_at IS NOT NULL OR magic_link.expires_at <= ?)
SQL;
        $data = [$this->getNow()];

        if ($search !== '') {
            $query .= ' AND (magic_link.titulaire_nom LIKE ? OR magic_link.titulaire_prenom LIKE ?'
                . ' OR magic_link.titulaire_email LIKE ? OR magic_link.motif LIKE ?)';
            $like = "%$search%";
            $data[] = $like;
            $data[] = $like;
            $data[] = $like;
            $data[] = $like;
        }

        $query .= ' ORDER BY magic_link.created_at DESC;';

        return $this->query($query, $data);
    }

    public function getToCleanUp(): array
    {
        $query = <<<SQL
SELECT id, id_u, titulaire_nom, titulaire_prenom, titulaire_email
FROM magic_link
WHERE user_deleted_at IS NULL
  AND (revoked_at IS NOT NULL OR expires_at <= ?);
SQL;

        return $this->query($query, $this->getNow());
    }

    public function getActiveByUserId(int $id_u): ?array
    {
        $query = <<<SQL
SELECT id, id_u, titulaire_nom, titulaire_prenom, titulaire_email
FROM magic_link
WHERE id_u = ? AND revoked_at IS NULL AND user_deleted_at IS NULL;
SQL;

        $result = $this->queryOne($query, $id_u);
        return $result === false ? null : $result;
    }

    public function updateTitulaire(string $id, string $nom, string $prenom, string $email): void
    {
        $query = <<<SQL
UPDATE magic_link
SET titulaire_nom = ?, titulaire_prenom = ?, titulaire_email = ?
WHERE id = ?;
SQL;
        $this->query($query, $nom, $prenom, $email, $id);
    }

    public function deleteClosedBefore(string $date): int
    {
        $condition = <<<SQL
user_deleted_at IS NOT NULL
  AND (
    (revoked_at IS NOT NULL AND revoked_at <= ?)
    OR (revoked_at IS NULL AND expires_at <= ?)
  )
SQL;

        $rows = $this->query("SELECT id FROM magic_link WHERE $condition;", $date, $date);
        if ($rows === []) {
            return 0;
        }

        $this->query("DELETE FROM magic_link WHERE $condition;", $date, $date);

        return count($rows);
    }

    public function updateToken(string $id, string $token): void
    {
        $query = 'UPDATE magic_link SET token = ? WHERE id = ?;';
        $this->query($query, $this->getHashedToken($token), $id);
    }

    public function revoke(string $id): void
    {
        $query = 'UPDATE magic_link SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL;';
        $this->query($query, $this->getNow(), $id);
    }

    public function delete(string $id): void
    {
        $this->query('DELETE FROM magic_link WHERE id = ?;', $id);
    }

    public function markUserDeleted(string $id): void
    {
        $query = 'UPDATE magic_link SET user_deleted_at = ? WHERE id = ? AND user_deleted_at IS NULL;';
        $this->query($query, $this->getNow(), $id);
    }

    public function getFromToken(string $token): ?array
    {
        $query = <<<SQL
SELECT id, id_u, code, code_attempts, motif, created_by, created_at, expires_at, revoked_at
FROM magic_link
WHERE token = ?;
SQL;

        $output = $this->queryOne($query, $this->getHashedToken($token));
        if ($output === false) {
            return null;
        }
        return $output;
    }

    public function incrementCodeAttempts(string $id): void
    {
        $this->query('UPDATE magic_link SET code_attempts = code_attempts + 1 WHERE id = ?;', $id);
    }

    private function getHashedToken(string $token): string
    {
        return hash(self::TOKEN_HASH_ALGORITHM, $token);
    }
}
