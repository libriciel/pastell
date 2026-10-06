<?php

class JournalSQL extends SQL
{
    public const DEFAULT_LIMIT = 100;

    private function buildListQuery(
        $id_e,
        $type,
        $id_d,
        $id_u,
        $offset,
        $limit,
        $recherche = '',
        $date_debut = false,
        $date_fin = false,
        $tri_croissant = false
    ): array {
        $value = [];
        $sql = <<<SQL
SELECT journal.*, document.titre, entite.denomination, utilisateur.nom, utilisateur.prenom, entite.siren
FROM journal
LEFT JOIN document ON journal.id_d = document.id_d
LEFT JOIN entite ON journal.id_e = entite.id_e
LEFT JOIN utilisateur ON journal.id_u = utilisateur.id_u
WHERE 1 = 1
SQL;

        if ($id_e) {
            $sql .= ' AND journal.id_e = ?';
            $value[] = $id_e;
        }
        if ($type) {
            $sql .= ' AND document_type = ?';
            $value[] = $type;
        }
        if ($id_d) {
            $sql .= ' AND journal.id_d = ?';
            $value[] = $id_d;
        }
        if ($id_u) {
            $sql .= ' AND journal.id_u = ?';
            $value[] = $id_u;
        }
        if ($recherche) {
            $sql .= ' AND journal.message_horodate LIKE ?';
            $value[] = "%$recherche%";
        }
        if ($date_debut) {
            $sql .= ' AND DATE(journal.date) >= ?';
            $value[] = $date_debut;
        }
        if ($date_fin) {
            $sql .= ' AND DATE(journal.date) <= ?';
            $value[] = $date_fin;
        }

        $order_direction = $tri_croissant ? 'ASC' : 'DESC';
        $sql .= ' ORDER BY id_j ' . $order_direction;
        if ($limit != -1) {
            $sql .= " LIMIT $offset, $limit";
        }
        return [$sql, $value];
    }

    public function getAll(
        $id_e = false,
        $type = false,
        $id_d = false,
        $id_u = false,
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $recherche = '',
        $date_debut = false,
        $date_fin = false,
        $tri_croissant = false
    ): array {
        [$sql, $value] = $this->buildListQuery(
            $id_e,
            $type,
            $id_d,
            $id_u,
            $offset,
            $limit,
            $recherche,
            $date_debut,
            $date_fin,
            $tri_croissant
        );
        return $this->query($sql, $value);
    }

    /**
     * @return Generator<array>
     */
    public function streamList(
        $id_e,
        $type,
        $id_d,
        $id_u,
        $recherche = '',
        $date_debut = false,
        $date_fin = false,
        $offset = 0,
        $limit = -1,
        bool $tri_croissant = false
    ): Generator {
        [$sql, $value] = $this->buildListQuery($id_e, $type, $id_d, $id_u, $offset, $limit, $recherche, $date_debut, $date_fin, $tri_croissant);

        yield from $this->queryStream($sql, $value);
    }

    public function countAll($id_e, $type, $id_d, $id_u, $recherche, $date_debut, $date_fin): int
    {
        $where = [];
        $value = [];

        if ($id_e) {
            $where[] = 'id_e = ?';
            $value[] = $id_e;
        }
        if ($type) {
            $where[] = 'document_type = ?';
            $value[] = $type;
        }
        if ($id_d) {
            $where[] = 'journal.id_d = ?';
            $value[] = $id_d;
        }
        if ($id_u) {
            $where[] = 'journal.id_u = ?';
            $value[] = $id_u;
        }
        if ($recherche) {
            $where[] = 'journal.message_horodate LIKE ?';
            $value[] = "%$recherche%";
        }
        if ($date_debut) {
            $where[] = 'DATE(journal.date) >= ?';
            $value[] = $date_debut;
        }
        if ($date_fin) {
            $where[] = 'DATE(journal.date) <= ?';
            $value[] = $date_fin;
        }

        $sql = <<<SQL
SELECT count(*) FROM journal
SQL;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return (int)$this->queryOne($sql, $value);
    }

    public function getInfo($id_j): ?JournalEntry
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

    public function getAllInfo($id_j): array|false
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

    public function countConsultation($id_u, $id_d): int
    {
        $sql = <<<SQL
SELECT count(*) FROM journal WHERE id_u = ? AND id_d = ?
SQL;
        return (int)$this->queryOne($sql, [$id_u, $id_d]);
    }

    public function insert(
        $type,
        $id_e,
        $id_u,
        $id_d,
        $action,
        $message,
        $now,
        $message_horodate,
        $date_horodatage,
        $document_type,
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
                $now,
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

    /**
     * @return string[]
     */
    public function getAttentePreuveIdList(): array
    {
        $sql = <<<SQL
SELECT id_j FROM journal_attente_preuve
SQL;
        return $this->queryOneCol($sql);
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

    /**
     * @return string[]
     */
    public function getIdListOlderThan(string $date, int $limit = 1000): array
    {
        $sql = <<<SQL
SELECT id_j FROM journal WHERE date < ? ORDER BY date LIMIT $limit
SQL;
        return $this->queryOneCol($sql, $date);
    }

    public function existsInHistorique($id_j): bool
    {
        $sql = <<<SQL
SELECT count(*) FROM journal_historique WHERE id_j = ?
SQL;
        return (bool)$this->queryOne($sql, $id_j);
    }

    public function copyToHistorique($id_j): void
    {
        $sql = <<<SQL
INSERT INTO journal_historique SELECT * FROM journal WHERE id_j = ?
SQL;
        $this->query($sql, $id_j);
    }

    public function delete($id_j): void
    {
        $sql = <<<SQL
DELETE FROM journal WHERE id_j = ?
SQL;
        $this->query($sql, $id_j);
    }
}
