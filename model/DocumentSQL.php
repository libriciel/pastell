<?php

use Pastell\Utilities\Identifier\IdentifierGeneratorInterface;

class DocumentSQL extends SQL
{
    /**
     * @deprecated 4.1.26 Use DocumentIdService::MAX_ATTEMPTS instead
     */
    public const MAX_ESSAI = 5;

    private static array $cache = [];

    public function __construct(
        SQLQuery $sqlQuery,
        private readonly IdentifierGeneratorInterface $identifier,
    ) {
        parent::__construct($sqlQuery);
    }

    /**
     * @throws UnrecoverableException
     * @deprecated 4.1.26 Use DocumentIdService::generate() instead
     */
    public function getNewId(): string
    {
        for ($i = 0; $i < self::MAX_ESSAI; $i++) {
            $id_d = $this->identifier->generate();
            $sql = "SELECT count(*) FROM document WHERE id_d=?";
            $nb = $this->queryOne($sql, $id_d);

            if ($nb == 0) {
                return $id_d;
            }
        }
        throw new UnrecoverableException("Impossible de trouver un numéro de transaction");
    }

    public function exists(string $id_d): bool
    {
        $sql = <<<SQL
SELECT count(*) FROM document WHERE id_d = ?
SQL;
        return (int)$this->queryOne($sql, $id_d) > 0;
    }

    public function save($id_d, $type)
    {
        $now = date(Date::DATE_ISO);
        $sql = <<<SQL
INSERT INTO document (id_d, type, creation, modification) VALUES (?, ?, ?, ?)
SQL;
        $this->query($sql, $id_d, $type, $now, $now);
    }

    public function setTitre($id_d, $titre)
    {
        $sql = <<<SQL
UPDATE document SET titre = ?, modification = now() WHERE id_d = ?
SQL;
        $this->query($sql, $titre, $id_d);
        unset(self::$cache[$id_d]);
    }

    public function getInfo($id_d)
    {
        if (empty(self::$cache[$id_d])) {
            $sql = <<<SQL
SELECT * FROM document WHERE id_d = ?
SQL;
            self::$cache[$id_d] = $this->queryOne($sql, $id_d);
        }
        return self::$cache[$id_d];
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
    public function getIdFromTitre($titre, $type)
    {
        $sql = "SELECT id_d FROM document WHERE titre=? AND type=?";
        return $this->queryOne($sql, $titre, $type);
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
    public function getIdFromEntiteAndTitre($id_e, $titre, $type)
    {
        $sql = "SELECT document.id_d FROM document " .
            " JOIN document_entite ON document.id_d=document_entite.id_d " .
            " WHERE id_e=? AND titre=? AND type=?";
        return $this->queryOne($sql, $id_e, $titre, $type);
    }

    public function delete($id_d)
    {
        $sql = <<<SQL
DELETE FROM document WHERE id_d = ?
SQL;
        $this->query($sql, $id_d);

        $sql = <<<SQL
DELETE dae.* FROM document_action_entite dae
INNER JOIN document_action da ON dae.id_a = da.id_a
WHERE da.id_d = ?
SQL;
        $this->query($sql, $id_d);

        $sql = <<<SQL
DELETE FROM document_action WHERE id_d = ?
SQL;
        $this->query($sql, $id_d);

        $sql = <<<SQL
DELETE FROM document_entite WHERE id_d = ?
SQL;
        $this->query($sql, $id_d);

        $sql = <<<SQL
DELETE FROM document_index WHERE id_d = ?
SQL;
        $this->query($sql, $id_d);

        $sql = <<<SQL
DELETE document_email_reponse FROM document_email_reponse
JOIN document_email ON document_email.id_de = document_email_reponse.id_de
WHERE document_email.id_d = ?
SQL;
        $this->query($sql, $id_d);

        $sql = <<<SQL
DELETE FROM document_email WHERE id_d = ?
SQL;
        $this->query($sql, $id_d);
    }

    public function getAllByType($type)
    {
        $sql = <<<SQL
SELECT id_d, titre FROM document WHERE type = ? ORDER BY creation
SQL;
        return $this->query($sql, $type);
    }

    public function getAllIdByType($type)
    {
        $sql = <<<SQL
SELECT document.id_d, document_entite.id_e FROM document
JOIN document_entite ON document.id_d = document_entite.id_d
WHERE document.type = ?
ORDER BY document.creation
SQL;
        return $this->query($sql, $type);
    }

    public function fixModule($old_flux_name, $new_flux_name)
    {
        self::clearCache();
        $sql = <<<SQL
UPDATE document SET type = ? WHERE type = ?
SQL;
        return $this->query($sql, $new_flux_name, $old_flux_name);
    }

    public function getAllType()
    {
        $sql = <<<SQL
SELECT DISTINCT type FROM document
SQL;
        return $this->queryOneCol($sql);
    }

    public function isTypePresent($type)
    {
        $sql = <<<SQL
SELECT * FROM document WHERE type = ? LIMIT 1
SQL;
        return $this->queryOne($sql, $type);
    }

    public function getEntiteWhichUsedDocument($document_type): array
    {
        $sql = <<<SQL
SELECT document_entite.id_e, entite.denomination, count(*) AS nb_documents FROM document_entite
JOIN entite ON entite.id_e = document_entite.id_e
WHERE last_type = ? AND last_action NOT IN ('termine', 'fatal-error')
GROUP BY document_entite.id_e, entite.denomination
SQL;
        return $this->query($sql, $document_type);
    }

    public static function clearCache()
    {
        self::$cache = [];
    }

    public function getDocumentsLastActionByTypeEntityAndCreationDate(
        int $entityId,
        string $type,
        string $startDate,
        $endDate
    ): array {
        $sql = <<<SQL
SELECT document.*, de.last_action
FROM document
INNER JOIN (
    SELECT id_d, last_action
    FROM document_entite
    WHERE id_e = ?
    AND last_type = ?
) de ON de.id_d = document.id_d
WHERE document.creation BETWEEN ? AND ?
SQL;
        return $this->query($sql, $entityId, $type, $startDate, $endDate);
    }
}
