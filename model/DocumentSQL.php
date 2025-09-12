<?php

use Pastell\Utilities\Identifier\IdentifierGeneratorInterface;

class DocumentSQL extends SQL
{
    public const MAX_ESSAI = 5;

    private static $cache;

    public function __construct(
        SQLQuery $sqlQuery,
        private readonly IdentifierGeneratorInterface $identifier,
    ) {
        parent::__construct($sqlQuery);
    }

    /**
     * @return string
     * @throws UnrecoverableException
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

    public function save($id_d, $type)
    {
        $now = date(Date::DATE_ISO);

        $sql = "INSERT INTO document(id_d,type,creation,modification) VALUES (?,?,?,?)";
        $this->query($sql, $id_d, $type, $now, $now);
    }

    public function setTitre($id_d, $titre)
    {
        $sql = "UPDATE document SET titre = ?,modification=now() WHERE id_d = ?";
        $this->query($sql, $titre, $id_d);
        unset(self::$cache[$id_d]);
    }

    public function getInfo($id_d)
    {
        if (empty(self::$cache[$id_d])) {
            $sql = "SELECT * FROM document WHERE id_d = ? ";
            self::$cache[$id_d] = $this->queryOne($sql, $id_d);
        }
        return self::$cache[$id_d];
    }

    public function getIdFromTitre($titre, $type)
    {
        $sql = "SELECT id_d FROM document WHERE titre=? AND type=?";
        return $this->queryOne($sql, $titre, $type);
    }

    public function getIdFromEntiteAndTitre($id_e, $titre, $type)
    {
        $sql = "SELECT document.id_d FROM document " .
            " JOIN document_entite ON document.id_d=document_entite.id_d " .
            " WHERE id_e=? AND titre=? AND type=?";
        return $this->queryOne($sql, $id_e, $titre, $type);
    }

    public function delete($id_d)
    {
        $sql = "DELETE FROM document WHERE id_d=?";
        $this->query($sql, $id_d);

        $sql = "DELETE dae.* FROM document_action_entite dae";
        $sql .= " INNER JOIN document_action da ON dae.id_a = da.id_a";
        $sql .= " WHERE da.id_d = ?";
        $this->query($sql, $id_d);


        $sql = "DELETE FROM document_action WHERE id_d=?";
        $this->query($sql, $id_d);
        $sql = "DELETE FROM document_entite WHERE id_d=?";
        $this->query($sql, $id_d);

        $sql = "DELETE FROM document_index WHERE id_d=?";
        $this->query($sql, $id_d);

        $sql = 'DELETE document_email_reponse FROM document_email_reponse' .
            ' JOIN document_email ON document_email.id_de = document_email_reponse.id_de ' .
            ' WHERE document_email.id_d=? ';
        $this->query($sql, $id_d);

        $sql = "DELETE FROM document_email WHERE id_d=?";
        $this->query($sql, $id_d);
    }

    public function getAllByType($type)
    {
        $sql = "SELECT id_d,titre FROM document WHERE type=? ORDER BY creation";
        return $this->query($sql, $type);
    }

    public function getAllIdByType($type)
    {
        $sql = "SELECT document.id_d, document_entite.id_e FROM document " .
            " JOIN document_entite ON document.id_d=document_entite.id_d " .
            " WHERE document.type=? ORDER BY document.creation";
        return $this->query($sql, $type);
    }

    public function getAllType()
    {
        $sql = "SELECT distinct type FROM document";
        return $this->queryOneCol($sql);
    }

    public function isTypePresent($type)
    {
        $sql = "SELECT * FROM document WHERE type=? LIMIT 1";
        return $this->queryOne($sql, $type);
    }

    public function getEntiteWhichUsedDocument($document_type): array
    {
        $sql = "SELECT document_entite.id_e,entite.denomination,count(*) as nb_documents FROM document_entite " .
            " JOIN entite ON entite.id_e=document_entite.id_e " .
            " WHERE last_type=? AND last_action NOT IN ('termine','fatal-error') " .
            " GROUP BY document_entite.id_e,entite.denomination";
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
        $sql = <<<EOT
SELECT document.*, de.last_action
FROM document
INNER JOIN (
    SELECT id_d, last_action
    FROM document_entite
    WHERE id_e = ?
    AND last_type = ?
) de ON de.id_d = document.id_d
WHERE document.creation BETWEEN ? AND ?;
EOT;
        return $this->query($sql, $entityId, $type, $startDate, $endDate);
    }
}
