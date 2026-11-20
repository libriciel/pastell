<?php

class ConnecteurFrequenceSQL extends SQL
{
    public function create(
        string $type_connecteur,
        string $famille_connecteur,
        string $id_connecteur,
        int $id_ce,
        string $action_type,
        string $type_document,
        string $action,
        string $expression,
        string $id_verrou,
    ): int {
        $sql = <<<SQL
INSERT INTO connecteur_frequence
    (type_connecteur, famille_connecteur, id_connecteur, id_ce, action_type, type_document, action, expression, id_verrou)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
        $this->query(
            $sql,
            $type_connecteur,
            $famille_connecteur,
            $id_connecteur,
            $id_ce,
            $action_type,
            $type_document,
            $action,
            $expression,
            $id_verrou
        );
        return (int)$this->lastInsertId();
    }

    public function update(
        int $id_cf,
        string $type_connecteur,
        string $famille_connecteur,
        string $id_connecteur,
        int $id_ce,
        string $action_type,
        string $type_document,
        string $action,
        string $expression,
        string $id_verrou,
    ): void {
        $sql = <<<SQL
UPDATE connecteur_frequence
SET type_connecteur = ?, famille_connecteur = ?, id_connecteur = ?, id_ce = ?, action_type = ?,
    type_document = ?, action = ?, expression = ?, id_verrou = ?
WHERE id_cf = ?
SQL;
        $this->query(
            $sql,
            $type_connecteur,
            $famille_connecteur,
            $id_connecteur,
            $id_ce,
            $action_type,
            $type_document,
            $action,
            $expression,
            $id_verrou,
            $id_cf
        );
    }

    public function getCandidateList(
        string $type_connecteur,
        string $famille_connecteur,
        string $id_connecteur,
        int $id_ce,
        string $action_type,
        string $type_document,
        string $action,
    ): array {
        $sql = <<<SQL
SELECT connecteur_frequence.*, connecteur_entite.libelle, entite.denomination,
    type_connecteur = ? AS type_connecteur_match,
    famille_connecteur = ? AS famille_connecteur_match,
    connecteur_frequence.id_connecteur = ? AS id_connecteur_match,
    connecteur_frequence.id_ce = ? AS id_ce_match,
    action_type = ? AS action_type_match,
    type_document = ? AS type_document_match,
    action = ? AS action_match,
    type_connecteur = '' AS type_connecteur_blank,
    famille_connecteur = '' AS famille_connecteur_blank,
    connecteur_frequence.id_connecteur = '' AS id_connecteur_blank,
    connecteur_frequence.id_ce = 0 AS id_ce_blank,
    action_type = '' AS action_type_blank,
    type_document = '' AS type_document_blank,
    action = '' AS action_blank
FROM connecteur_frequence
LEFT JOIN connecteur_entite ON connecteur_frequence.id_ce = connecteur_entite.id_ce
LEFT JOIN entite ON entite.id_e = connecteur_entite.id_e
WHERE (type_connecteur = ? OR type_connecteur = '')
    AND (famille_connecteur = ? OR famille_connecteur = '')
    AND (connecteur_frequence.id_connecteur = ? OR connecteur_frequence.id_connecteur = '')
    AND (connecteur_frequence.id_ce = ? OR connecteur_frequence.id_ce = 0)
ORDER BY id_cf
SQL;
        return $this->query(
            $sql,
            $type_connecteur,
            $famille_connecteur,
            $id_connecteur,
            $id_ce,
            $action_type,
            $type_document,
            $action,
            $type_connecteur,
            $famille_connecteur,
            $id_connecteur,
            $id_ce,
        );
    }

    public function getInfo($id_cf)
    {
        $sql = "SELECT connecteur_frequence.*,connecteur_entite.libelle, entite.denomination FROM connecteur_frequence " .
                " LEFT JOIN connecteur_entite ON connecteur_frequence.id_ce=connecteur_entite.id_ce" .
                " LEFT JOIN entite ON entite.id_e=connecteur_entite.id_e" .
                " WHERE id_cf = ?";
        return $this->queryOne($sql, $id_cf);
    }

    public function getAllArray()
    {
        $sql = "SELECT connecteur_frequence.*,connecteur_entite.libelle, entite.denomination FROM connecteur_frequence " .
            " LEFT JOIN connecteur_entite ON connecteur_frequence.id_ce=connecteur_entite.id_ce" .
            " LEFT JOIN entite ON entite.id_e=connecteur_entite.id_e" .
            " ORDER BY type_connecteur,famille_connecteur,connecteur_frequence.id_connecteur,connecteur_frequence.id_ce,action_type,type_document,action";
        return $this->query($sql);
    }

    /**
     * @return string[]
     */
    public function getDistinctVerrou(): array
    {
        $sql = "SELECT DISTINCT id_verrou FROM connecteur_frequence WHERE id_verrou != '' ORDER BY id_verrou";
        return array_column($this->query($sql), 'id_verrou');
    }

    public function delete($id_cf)
    {
        $sql = "DELETE FROM connecteur_frequence WHERE id_cf =?";
        $this->query($sql, $id_cf);
    }

    public function deleteByIdCe(int $id_ce): void
    {
        $sql = <<<SQL
DELETE FROM connecteur_frequence WHERE id_ce = ?;
SQL;
        $this->query($sql, $id_ce);
    }

    public function deleteAll()
    {
        $sql = "DELETE FROM connecteur_frequence";
        $this->query($sql);
    }
}
