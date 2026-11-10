<?php

declare(strict_types=1);

final class DocumentActionEntiteSQL extends SQL
{
    public function add(int $id_a, int $id_e, int $id_j): void
    {
        $sql = <<<SQL
INSERT INTO document_action_entite (id_a, id_e, id_j) VALUES (?, ?, ?)
SQL;
        $this->query($sql, $id_a, $id_e, $id_j);
    }

    public function remove(int $id_a): void
    {
        $sql = <<<SQL
DELETE FROM document_action_entite WHERE id_a = ?
SQL;
        $this->query($sql, $id_a);
    }

    public function getLastAction(int $id_e, string $id_d): ?string
    {
        $sql = <<<SQL
SELECT action FROM document_action_entite
JOIN document_action ON document_action_entite.id_a = document_action.id_a
WHERE document_action_entite.id_e = ? AND id_d = ?
ORDER BY document_action.id_a DESC LIMIT 1
SQL;
        $action = $this->queryOne($sql, $id_e, $id_d);
        return $action === false ? null : (string) $action;
    }

    public function getLastActionByDate(int $id_e, string $id_d): ?string
    {
        $sql = <<<SQL
SELECT action FROM document_action_entite
JOIN document_action ON document_action_entite.id_a = document_action.id_a
WHERE document_action_entite.id_e = ? AND id_d = ?
ORDER BY document_action.date DESC, document_action.id_a DESC LIMIT 1
SQL;
        $action = $this->queryOne($sql, $id_e, $id_d);
        return $action === false ? null : (string) $action;
    }

    public function getLastActionNotModif(int $id_e, string $id_d): ?string
    {
        $sql = <<<SQL
SELECT action FROM document_action_entite
JOIN document_action ON document_action_entite.id_a = document_action.id_a
WHERE document_action_entite.id_e = ? AND id_d = ? AND action != ?
ORDER BY date DESC, document_action.id_a DESC LIMIT 1
SQL;
        $action = $this->queryOne($sql, $id_e, $id_d, Action::MODIFICATION);
        return $action === false ? null : (string) $action;
    }

    public function getLastActionInfo(int $id_e, string $id_d): ?array
    {
        $sql = <<<SQL
SELECT document_action.action, journal.message, document_action.date
FROM document_action_entite
JOIN document_action ON document_action_entite.id_a = document_action.id_a
LEFT JOIN journal ON journal.id_j = document_action_entite.id_j
JOIN entite ON document_action.id_e = entite.id_e
WHERE document_action_entite.id_e = ? AND document_action.id_d = ?
ORDER BY document_action.id_a DESC LIMIT 1
SQL;
        $action_info = $this->queryOne($sql, $id_e, $id_d);
        return $action_info === false ? null : $action_info;
    }

    public function getActions(int $id_e, string $id_d): array
    {
        $sql = <<<SQL
SELECT document_action.action, document_action.date, document_action_entite.id_e, document_action.id_u,
    denomination, nom, prenom, document_action_entite.id_j
FROM document_action_entite
JOIN document_action ON document_action_entite.id_a = document_action.id_a
LEFT JOIN utilisateur ON document_action.id_u = utilisateur.id_u
JOIN entite ON document_action.id_e = entite.id_e
WHERE document_action_entite.id_e = ? AND document_action.id_d = ?
ORDER BY document_action_entite.id_a
SQL;
        return $this->query($sql, $id_e, $id_d);
    }

    public function getUserFromAction(int $id_e, string $id_d, string $action): ?array
    {
        $sql = <<<SQL
SELECT * FROM document_action_entite
JOIN document_action ON document_action_entite.id_a = document_action.id_a
LEFT JOIN utilisateur ON document_action.id_u = utilisateur.id_u
JOIN entite ON document_action.id_e = entite.id_e
WHERE document_action_entite.id_e = ? AND id_d = ? AND document_action.action = ?
LIMIT 1
SQL;
        $user_info = $this->queryOne($sql, $id_e, $id_d, $action);
        return $user_info === false ? null : $user_info;
    }

    public function getOtherEntites(int $id_e, string $id_d): array
    {
        $sql = <<<SQL
SELECT DISTINCT document_action.id_e, entite.denomination
FROM document_action
JOIN document_action_entite ON document_action.id_a = document_action_entite.id_a
JOIN entite ON entite.id_e = document_action.id_e
WHERE id_d = ? AND document_action_entite.id_e = ? AND entite.id_e != ?
SQL;
        return $this->query($sql, $id_d, $id_e, $id_e);
    }
}
