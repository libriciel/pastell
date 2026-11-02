<?php

declare(strict_types=1);

class DocumentEntiteSQL extends SQL
{
    public function insert(string $id_d, int $id_e, string $role): void
    {
        $sql = <<<SQL
INSERT INTO document_entite (id_d, id_e, role, last_type)
VALUES (?, ?, ?, (SELECT type FROM document WHERE id_d = ?))
SQL;
        $this->query($sql, $id_d, $id_e, $role, $id_d);
    }

    public function countRole(string $id_d, int $id_e, string $role): int
    {
        $sql = <<<SQL
SELECT count(*) FROM document_entite WHERE id_d = ? AND id_e = ? AND role = ?
SQL;
        return (int) $this->queryOne($sql, $id_d, $id_e, $role);
    }

    public function getRole(int $id_e, string $id_d): ?string
    {
        $sql = <<<SQL
SELECT role FROM document_entite WHERE id_e = ? AND id_d = ? LIMIT 1
SQL;
        $role = $this->queryOne($sql, $id_e, $id_d);
        return $role === false ? null : (string) $role;
    }

    public function getEntiteIdWithRole(string $id_d, string $role): ?int
    {
        $sql = <<<SQL
SELECT id_e FROM document_entite WHERE id_d = ? AND role = ? LIMIT 1
SQL;
        $id_e = $this->queryOne($sql, $id_d, $role);
        return $id_e === false ? null : (int) $id_e;
    }

    public function getEntiteId(string $id_d): ?int
    {
        $sql = <<<SQL
SELECT id_e FROM document_entite WHERE id_d = ? LIMIT 1
SQL;
        $id_e = $this->queryOne($sql, $id_d);
        return $id_e === false ? null : (int) $id_e;
    }

    public function getEntites(string $id_d): array
    {
        $sql = <<<SQL
SELECT * FROM document_entite
JOIN entite ON document_entite.id_e = entite.id_e
WHERE id_d = ?
SQL;
        return $this->query($sql, $id_d);
    }

    public function getDocumentsByType(int $id_e, string $type): array
    {
        $sql = <<<SQL
SELECT * FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE document_entite.id_e = ? AND document.type = ?
ORDER BY document.modification DESC
SQL;
        return $this->query($sql, $id_e, $type);
    }

    public function getDocuments(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE id_e = ?
SQL;
        return $this->query($sql, $id_e);
    }

    public function countDocuments(int $id_e): int
    {
        $sql = <<<SQL
SELECT count(*) FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE id_e = ?
SQL;
        return (int) $this->queryOne($sql, $id_e);
    }

    public function countDocumentsByType(int $id_e, string $type): int
    {
        $sql = <<<SQL
SELECT count(*) FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE id_e = ? AND last_type = ?
SQL;
        return (int) $this->queryOne($sql, $id_e, $type);
    }

    public function countByLastAction(?int $id_e, ?string $type): array
    {
        $sql = <<<SQL
SELECT count(*) AS count, id_e, last_type AS type, last_action FROM document_entite
WHERE (? IS NULL OR id_e = ?) AND (? IS NULL OR last_type = ?)
GROUP BY id_e, last_type, last_action
SQL;
        return $this->query($sql, $id_e, $id_e, $type, $type);
    }

    /**
     * @param array<string, string> $filters
     */
    public function countByIndexFilters(int $id_e, string $type, array $filters): int
    {
        $sql = <<<SQL
SELECT count(DISTINCT document.id_d) FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE document_entite.id_e = ? AND document.type = ?
SQL;
        $params = [$id_e, $type];
        if ($filters === []) {
            $sql .= <<<SQL

AND EXISTS (SELECT 1 FROM document_index WHERE document_index.id_d = document.id_d)
SQL;
        }
        foreach ($filters as $field_name => $field_value) {
            $sql .= <<<SQL

AND EXISTS (
    SELECT 1 FROM document_index
    WHERE document_index.id_d = document.id_d AND field_name = ? AND field_value LIKE ?
)
SQL;
            $params[] = $field_name;
            $params[] = '%' . str_replace('%', '\%', $field_value) . '%';
        }
        return (int) $this->queryOne($sql, $params);
    }

    public function getDocumentsWithLastActionUntil(string $date, ?string $type, ?int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM document_entite
WHERE date(last_action_date) <= ?
AND (? IS NULL OR last_type = ?)
AND (? IS NULL OR id_e = ?)
SQL;
        return $this->query($sql, $date, $type, $type, $id_e, $id_e);
    }

    public function updateLastAction(int $id_e, string $id_d, string $action, string $date): void
    {
        $sql = <<<SQL
UPDATE document_entite SET last_action = ?, last_action_date = ? WHERE id_d = ? AND id_e = ?
SQL;
        $this->query($sql, $action, $date, $id_d, $id_e);
    }
}
