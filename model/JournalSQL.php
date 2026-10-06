<?php

class JournalSQL extends SQL
{
    public const DEFAULT_LIMIT = 100;

    public function getAll(
        int $id_e = 0,
        string $type = '',
        string $id_d = '',
        int $id_u = 0,
        int $offset = 0,
        int $limit = self::DEFAULT_LIMIT,
        string $recherche = '',
        string $date_debut = '',
        string $date_fin = '',
        bool $tri_croissant = false
    ): array {
        $filters = $this->getFilters($id_e, $type, $id_d, $id_u, $recherche, $date_debut, $date_fin);
        return $this->query(
            $this->getListQuery($filters, $offset, $limit, $tri_croissant),
            array_values($filters)
        );
    }

    public function streamList(
        int $id_e,
        string $type,
        string $id_d,
        int $id_u,
        string $recherche = '',
        string $date_debut = '',
        string $date_fin = '',
        int $offset = 0,
        int $limit = -1,
        bool $tri_croissant = false,
        bool $buffered = false
    ): Generator {
        $filters = $this->getFilters($id_e, $type, $id_d, $id_u, $recherche, $date_debut, $date_fin);
        yield from $this->queryStream(
            $this->getListQuery($filters, $offset, $limit, $tri_croissant),
            array_values($filters),
            $buffered
        );
    }

    public function countAll(
        int $id_e,
        string $type,
        string $id_d,
        int $id_u,
        string $recherche,
        string $date_debut,
        string $date_fin
    ): int {
        $filters = $this->getFilters($id_e, $type, $id_d, $id_u, $recherche, $date_debut, $date_fin);
        $where = $this->getWhereClause($filters);
        $sql = <<<SQL
SELECT count(*) FROM journal $where
SQL;
        return (int)$this->queryOne($sql, array_values($filters));
    }

    private function getFilters(
        int $id_e,
        string $type,
        string $id_d,
        int $id_u,
        string $recherche,
        string $date_debut,
        string $date_fin
    ): array {
        return array_filter([
            'journal.id_e = ?' => $id_e,
            'journal.document_type = ?' => $type,
            'journal.id_d = ?' => $id_d,
            'journal.id_u = ?' => $id_u,
            'journal.message_horodate LIKE ?' => $recherche ? '%' . $recherche . '%' : '',
            'DATE(journal.date) >= ?' => $date_debut,
            'DATE(journal.date) <= ?' => $date_fin,
        ]);
    }

    private function getWhereClause(array $filters): string
    {
        return $filters ? 'WHERE ' . implode(' AND ', array_keys($filters)) : '';
    }

    private function getListQuery(array $filters, int $offset, int $limit, bool $tri_croissant): string
    {
        $where = $this->getWhereClause($filters);
        $order_direction = $tri_croissant ? 'ASC' : 'DESC';
        $sql = <<<SQL
SELECT journal.*, document.titre, entite.denomination, utilisateur.nom, utilisateur.prenom, entite.siren
FROM journal
LEFT JOIN document ON journal.id_d = document.id_d
LEFT JOIN entite ON journal.id_e = entite.id_e
LEFT JOIN utilisateur ON journal.id_u = utilisateur.id_u
$where
ORDER BY id_j $order_direction
SQL;
        if ($limit !== -1) {
            $sql .= " LIMIT $offset, $limit";
        }
        return $sql;
    }

    public function getInfo(int $id_j): ?JournalEntry
    {
        $sql = <<<SQL
SELECT * FROM journal WHERE id_j = ?
SQL;
        $info = $this->queryOne($sql, $id_j);
        if (!$info) {
            return null;
        }
        return $this->mapToJournalEntry($info);
    }

    private function mapToJournalEntry(array $info): JournalEntry
    {
        return new JournalEntry(
            (int)$info['id_j'],
            (int)$info['type'],
            (int)$info['id_e'],
            (int)$info['id_u'],
            $info['id_d'],
            $info['action'],
            $info['message'],
            $info['date'],
            $info['preuve'],
            $info['date_horodatage'],
            $info['message_horodate'],
            $info['document_type']
        );
    }

    public function getAllInfo(int $id_j): array|false
    {
        $sql = <<<SQL
SELECT journal.*, document.titre, entite.denomination, utilisateur.nom, utilisateur.prenom
FROM journal
LEFT JOIN document ON journal.id_d = document.id_d
LEFT JOIN entite ON journal.id_e = entite.id_e
LEFT JOIN utilisateur ON journal.id_u = utilisateur.id_u
WHERE id_j = ?
SQL;
        return $this->queryOne($sql, $id_j);
    }

    public function countConsultation(int $id_u, string $id_d): int
    {
        $sql = <<<SQL
SELECT count(*) FROM journal WHERE id_u = ? AND id_d = ?
SQL;
        return (int)$this->queryOne($sql, [$id_u, $id_d]);
    }

    public function insert(
        int $type,
        int $id_e,
        int $id_u,
        string $id_d,
        string $action,
        string $message,
        string $date,
        string $message_horodate,
        string $date_horodatage,
        string $document_type,
        ?string $preuve
    ): int {
        $sql = <<<SQL
INSERT INTO journal (type, id_e, id_u, id_d, action, message, date, message_horodate, date_horodatage, document_type, preuve)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
        $this->query(
            $sql,
            [
                $type,
                $id_e,
                $id_u,
                $id_d,
                $action,
                $message,
                $date,
                $message_horodate,
                $date_horodatage,
                $document_type,
                $preuve ?? ''
            ]
        );
        return (int)$this->lastInsertId();
    }

    public function insertAttentePreuve(int $id_j): void
    {
        $sql = <<<SQL
INSERT INTO journal_attente_preuve (id_j) VALUES (?)
SQL;
        $this->query($sql, [$id_j]);
    }

    public function getAttentePreuveIdList(): array
    {
        $sql = <<<SQL
SELECT id_j FROM journal_attente_preuve
SQL;
        return array_map('intval', $this->queryOneCol($sql));
    }

    public function updateHorodatage(int $id_j, string $date_horodatage): void
    {
        $sql = <<<SQL
UPDATE journal SET date_horodatage = ? WHERE id_j = ?
SQL;
        $this->query($sql, [$date_horodatage, $id_j]);
    }

    public function updateProof(int $id_j, string $date_horodatage, string $preuve): void
    {
        $sql = <<<SQL
UPDATE journal SET preuve = ?, date_horodatage = ? WHERE id_j = ?
SQL;
        $this->query($sql, [$preuve, $date_horodatage, $id_j]);
    }

    public function deleteAttentePreuve(int $id_j): void
    {
        $sql = <<<SQL
DELETE FROM journal_attente_preuve WHERE id_j = ?
SQL;
        $this->query($sql, [$id_j]);
    }

    public function getIdListWithProof(): array
    {
        $sql = <<<SQL
SELECT id_j FROM journal WHERE preuve != ''
SQL;
        return array_map('intval', $this->queryOneCol($sql));
    }

    public function getProof(int $id_j): string
    {
        $sql = <<<SQL
SELECT preuve FROM journal WHERE id_j = ?
SQL;
        return (string)$this->queryOne($sql, [$id_j]);
    }

    public function clearProof(int $id_j): void
    {
        $sql = <<<SQL
UPDATE journal SET preuve = '' WHERE id_j = ?
SQL;
        $this->query($sql, [$id_j]);
    }

    public function getCount(): int
    {
        $sql = <<<SQL
SELECT count(*) FROM journal
SQL;
        return (int)$this->queryOne($sql);
    }

    public function getHistoriqueCount(): int
    {
        $sql = <<<SQL
SELECT count(*) FROM journal_historique
SQL;
        return (int)$this->queryOne($sql);
    }

    public function getFirstDate(): string|false
    {
        $sql = <<<SQL
SELECT date FROM journal ORDER BY id_j LIMIT 1
SQL;
        return $this->queryOne($sql);
    }

    public function getIdListOlderThan(string $date, int $limit = 1000): array
    {
        $sql = <<<SQL
SELECT id_j FROM journal WHERE date < ? ORDER BY date LIMIT $limit
SQL;
        return array_map('intval', $this->queryOneCol($sql, $date));
    }

    public function existsInHistorique(int $id_j): bool
    {
        $sql = <<<SQL
SELECT count(*) FROM journal_historique WHERE id_j = ?
SQL;
        return (bool)$this->queryOne($sql, $id_j);
    }

    public function copyToHistorique(int $id_j): void
    {
        $sql = <<<SQL
INSERT INTO journal_historique SELECT * FROM journal WHERE id_j = ?
SQL;
        $this->query($sql, $id_j);
    }

    public function delete(int $id_j): void
    {
        $sql = <<<SQL
DELETE FROM journal WHERE id_j = ?
SQL;
        $this->query($sql, $id_j);
    }
}
