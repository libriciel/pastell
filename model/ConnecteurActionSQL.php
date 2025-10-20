<?php

class ConnecteurActionSQL extends SQL
{
    public const DEFAULT_LIMIT = 20;

    public function add(int $id_e, int $id_u, int $id_ce, string $type_dossier, string $action, string $empreinte_sha256, string $message): int
    {
        $sql = "INSERT INTO connecteur_action(id_e, id_u, id_ce, type_dossier, action, date, empreinte_sha256, message) VALUES (?,?,?,?,?,?,?,?)";
        $this->query($sql, $id_e, $id_u, $id_ce, $type_dossier, $action, $this->getNow(), $empreinte_sha256, $message);
        return $this->lastInsertId();
    }

    public function delete(int $id_ce): void
    {
        $sql = "DELETE FROM connecteur_action WHERE id_ce=?";
        $this->query($sql, $id_ce);
    }

    public function getByIdCe(int $id_ce, int $offset = 0, int $limit = self::DEFAULT_LIMIT): array
    {
        $sql = "SELECT connecteur_action.id_u AS id_u, " .
            " connecteur_action.id_e AS id_e, " .
            " connecteur_action.type_dossier AS type_dossier, " .
            " connecteur_action.action AS action, " .
            " connecteur_action.date AS date, " .
            " connecteur_action.empreinte_sha256 AS empreinte_sha256, " .
            " connecteur_action.message AS message, " .
            " utilisateur.nom AS nom, " .
            " utilisateur.prenom AS prenom, " .
            " entite.denomination AS denomination" .
            " FROM connecteur_action " .
            " LEFT JOIN utilisateur ON connecteur_action.id_u = utilisateur.id_u " .
            " LEFT JOIN entite ON connecteur_action.id_e = entite.id_e " .
            " WHERE id_ce= ? " .
            " ORDER BY date DESC,id_a DESC";
        $sql .= " LIMIT $offset,$limit";
        return $this->query($sql, $id_ce);
    }

    public function countByIdCe(int $id_ce): int
    {
        $sql = "SELECT count(*) " .
            " FROM connecteur_action " .
            " WHERE id_ce= ? ";
        return $this->queryOne($sql, $id_ce);
    }
}
