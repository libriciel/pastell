<?php

class TypeDossierActionSQL extends SQL
{
    public const DEFAULT_LIMIT = 20;

    public function add(int $id_u, int $id_t, string $action, string $empreinte_sha256, string $message, string $export_json): int
    {
        $sql = "INSERT INTO type_dossier_action(id_u, id_t, action, date, empreinte_sha256, message, export_json) VALUES (?,?,?,?,?,?,?)";
        $this->query($sql, $id_u, $id_t, $action, $this->getNow(), $empreinte_sha256, $message, $export_json);
        return $this->lastInsertId();
    }

    public function getById(int $id_t, int $offset = 0, int $limit = self::DEFAULT_LIMIT): array
    {
        $sql = "SELECT type_dossier_action.id_u AS id_u, " .
            " type_dossier_action.action AS action, " .
            " type_dossier_action.date AS date, " .
            " type_dossier_action.empreinte_sha256 AS empreinte_sha256, " .
            " type_dossier_action.message AS message, " .
            " type_dossier_action.export_json AS export_json, " .
            " utilisateur.nom AS nom, " .
            " utilisateur.prenom AS prenom" .
            " FROM type_dossier_action " .
            " LEFT JOIN utilisateur ON type_dossier_action.id_u = utilisateur.id_u " .
            " WHERE id_t= ? " .
            " ORDER BY date DESC,id_a DESC";
        $sql .= " LIMIT $offset,$limit";
        return $this->query($sql, $id_t);
    }

    public function countById(int $id_t): int
    {
        $sql = "SELECT count(*) " .
            " FROM type_dossier_action " .
            " WHERE id_t= ? ";
        return $this->queryOne($sql, $id_t);
    }
}
