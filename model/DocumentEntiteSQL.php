<?php

declare(strict_types=1);

use Pastell\Service\Document\DocumentSearchCriteria;

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

    public function getLastAction(int $id_e, string $id_d): ?string
    {
        $sql = <<<SQL
SELECT last_action FROM document_entite WHERE id_e = ? AND id_d = ?
SQL;
        $last_action = $this->queryOne($sql, $id_e, $id_d);
        return $last_action === false || $last_action === null ? null : (string) $last_action;
    }

    public function getLastActionDate(int $id_e, string $id_d): ?string
    {
        $sql = <<<SQL
SELECT last_action_date FROM document_entite WHERE id_e = ? AND id_d = ?
SQL;
        $last_action_date = $this->queryOne($sql, $id_e, $id_d);
        return $last_action_date === false || $last_action_date === null ? null : (string) $last_action_date;
    }

    public function getInfo(int $id_e, string $id_d): ?array
    {
        $sql = <<<SQL
SELECT *, document_entite.last_action AS last_action, document_entite.last_action_date AS last_action_date
FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE document_entite.id_e = ? AND document.id_d = ?
SQL;
        $document_info = $this->queryOne($sql, $id_e, $id_d);
        return $document_info === false ? null : $document_info;
    }

    public function getDocumentsByLastAction(int $id_e, string $type, string $last_action): array
    {
        $sql = <<<SQL
SELECT * FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE document_entite.id_e = ? AND document.type = ? AND document_entite.last_action = ?
SQL;
        return $this->query($sql, $id_e, $type, $last_action);
    }

    public function getDocumentsByLastActionUntil(int $id_e, string $type, string $last_action, string $date): array
    {
        $sql = <<<SQL
SELECT * FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE document_entite.id_e = ? AND document.type = ? AND document_entite.last_action = ?
AND date(document_entite.last_action_date) <= ?
SQL;
        return $this->query($sql, $id_e, $type, $last_action, $date);
    }

    public function getDocumentsThroughActionUntil(int $id_e, string $type, string $action, string $date): array
    {
        $sql = <<<SQL
SELECT * FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
JOIN document_action ON document_action.id_d = document.id_d AND document_action.id_e = document_entite.id_e
WHERE document_entite.id_e = ? AND document.type = ? AND document_action.action = ?
AND date(document_action.date) <= ?
SQL;
        return $this->query($sql, $id_e, $type, $action, $date);
    }

    public function countDocumentsWithLastActionAfter(int $id_e, string $type, string $date): int
    {
        $sql = <<<SQL
SELECT count(*) FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
WHERE document_entite.id_e = ? AND document.type = ? AND document_entite.last_action_date > ?
SQL;
        return (int) $this->queryOne($sql, $id_e, $type, $date);
    }

    public function countBySearch(DocumentSearchCriteria $criteria): int
    {
        $params = [];
        $sql = 'SELECT count(*)' . $this->getSearchFromClause($criteria, $params) . $this->getSearchWhereClause($criteria, $params);
        return (int) $this->queryOne($sql, $params);
    }

    public function search(
        DocumentSearchCriteria $criteria,
        string $sort,
        string $direction,
        int $offset,
        int $limit,
    ): array {
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';
        $params = [];
        $sql = <<<SQL
SELECT *, document.id_d AS id_d, document_entite.id_e AS id_e, document.type AS type, document_entite.last_action AS last_action,
    document_entite.last_action_date AS last_action_date, entite.denomination AS entite_base
SQL;
        $sql .= $this->getSearchFromClause($criteria, $params);
        if (array_key_exists($sort, $criteria->index_values)) {
            $sql .= <<<SQL

LEFT JOIN document_index ON document.id_d = document_index.id_d AND document_index.field_name = ?
SQL;
            $params[] = $sort;
            $order_by = "document_index.field_value $direction";
        } else {
            $order_by = match ($sort) {
                'entite' => "entite.denomination $direction",
                'titre', 'title' => "document.titre $direction, document.id_d $direction",
                default => "document_entite.last_action_date $direction",
            };
        }
        $sql .= $this->getSearchWhereClause($criteria, $params);
        $sql .= <<<SQL

ORDER BY $order_by LIMIT $offset, $limit
SQL;
        return $this->query($sql, $params);
    }

    private function getSearchFromClause(DocumentSearchCriteria $criteria, array &$params): string
    {
        $sql = <<<SQL

FROM document_entite
JOIN document ON document_entite.id_d = document.id_d
JOIN entite ON document_entite.id_e = entite.id_e
SQL;
        if ($criteria->transit_action) {
            $sql .= <<<SQL

JOIN document_action ON document_action.id_d = document.id_d AND document_action.id_e = entite.id_e
SQL;
        }
        $i = 0;
        foreach ($criteria->index_values as $field_name => $field_value) {
            if ($field_value) {
                $sql .= <<<SQL

JOIN document_index AS document_index_$i ON document.id_d = document_index_$i.id_d
    AND document_index_$i.field_name = ? AND document_index_$i.field_value LIKE ?
SQL;
                $params[] = $field_name;
                $params[] = "%$field_value%";
            }
            $i++;
        }
        return $sql;
    }

    private function getSearchWhereClause(DocumentSearchCriteria $criteria, array &$params): string
    {
        $allowed_types = $criteria->allowed_types ?: [''];
        $sql = "\nWHERE document.type IN (" . $this->getPlaceholders($allowed_types) . ')';
        array_push($params, ...array_values($allowed_types));

        if ($criteria->id_e) {
            $sql .= "\nAND document_entite.id_e = ?";
            $params[] = $criteria->id_e;
        }
        if ($criteria->type) {
            $sql .= "\nAND document.type = ?";
            $params[] = $criteria->type;
        }
        if ($criteria->search) {
            $sql .= "\nAND document.titre LIKE ?";
            $params[] = "%{$criteria->search}%";
        }
        if ($criteria->last_actions) {
            $sql .= "\nAND document_entite.last_action IN (" . $this->getPlaceholders($criteria->last_actions) . ')';
            array_push($params, ...array_values($criteria->last_actions));
        }
        if ($criteria->last_action_begin) {
            $sql .= "\nAND document_entite.last_action_date > ?";
            $params[] = $criteria->last_action_begin;
        }
        if ($criteria->last_action_end) {
            $sql .= "\nAND document_entite.last_action_date < ?";
            $params[] = $criteria->last_action_end;
        }
        if ($criteria->transit_action) {
            $sql .= "\nAND document_action.action = ?";
            $params[] = $criteria->transit_action;
            if ($criteria->transit_begin) {
                $sql .= "\nAND document_action.date > ?";
                $params[] = $criteria->transit_begin;
            }
            if ($criteria->transit_end) {
                $sql .= "\nAND document_action.date < ?";
                $params[] = $criteria->transit_end;
            }
        }
        if ($criteria->not_transit_action) {
            $sql .= <<<SQL

AND document_entite.id_d NOT IN (
    SELECT document_action.id_d FROM document_action WHERE document_action.action = ?
)
SQL;
            $params[] = $criteria->not_transit_action;
        }
        return $sql;
    }
}
