<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class AgentSQL extends SQL
{
    public const NB_MAX = 20;

    private const COLUMN_MATRICULE = 0;
    public const COLUMN_SIREN = 9;

    /**
     * @deprecated 4.1.26 Use AgentImportService::add() instead
     */
    public function add($info, $infoCollectivite = [])
    {

        if ($infoCollectivite) {
            $info[9] = $infoCollectivite['siren'];
        }

        $sql = "DELETE FROM agent WHERE siren=? AND matricule=?";
        $this->query($sql, $info[9], $info[0]);

        $sql = <<<EOT
INSERT INTO agent (matricule,titre,nom_usage,nom_patronymique,prenom,emploi_grade_code,emploi_grade_libelle,
                   collectivite_code,collectivite_libelle ,siren,type_dossier_code ,type_dossier_libelle,
                   train_traitement_code,train_traitement_libelle)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
EOT;
        $this->query($sql, $info);
        return true;
    }

    public function replace(array $agent_data): void
    {
        $sql = <<<SQL
DELETE FROM agent WHERE siren = ? AND matricule = ?
SQL;
        $this->query($sql, $agent_data[self::COLUMN_SIREN], $agent_data[self::COLUMN_MATRICULE]);

        $sql = <<<SQL
INSERT INTO agent (
    matricule, titre, nom_usage, nom_patronymique, prenom, emploi_grade_code, emploi_grade_libelle,
    collectivite_code, collectivite_libelle, siren, type_dossier_code, type_dossier_libelle,
    train_traitement_code, train_traitement_libelle
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
        $this->query($sql, array_values($agent_data));
    }

    public function getBySiren($siren, $offset, $search = '')
    {
        $offset = (int)$offset;
        $limit = self::NB_MAX;
        $sql = <<<SQL
SELECT * FROM agent
JOIN entite ON entite.siren = agent.siren
WHERE agent.siren = ? AND (nom_patronymique LIKE ? OR prenom LIKE ?)
ORDER BY nom_patronymique, prenom
LIMIT $offset, $limit
SQL;
        return $this->query($sql, $siren, "%$search%", "%$search%");
    }

    public function getNbAgent($siren, $search = '')
    {
        $sql = <<<SQL
SELECT count(*) FROM agent WHERE siren = ? AND (nom_patronymique LIKE ? OR prenom LIKE ?)
SQL;
        return $this->queryOne($sql, $siren, "%$search%", "%$search%");
    }

    public function getInfo($id_a, $siren)
    {
        $sql = <<<SQL
SELECT * FROM agent WHERE id_a = ? AND siren = ?
SQL;
        return $this->queryOne($sql, $id_a, $siren);
    }

    public function getNbAllAgent($search)
    {
        $sql = <<<SQL
SELECT count(*) FROM agent
JOIN entite ON entite.siren = agent.siren
JOIN entite_ancetre ON entite.id_e = entite_ancetre.id_e
JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e
JOIN role_droit ON utilisateur_role.role = role_droit.role
WHERE droit = ? AND utilisateur_role.id_u = 1 AND (nom_patronymique LIKE ? OR prenom LIKE ?)
SQL;
        return $this->queryOne($sql, $this->getEntityReadRight(), "%$search%", "%$search%");
    }

    public function getAllAgent($search, $offset)
    {
        $offset = (int)$offset;
        $limit = self::NB_MAX;
        $sql = <<<SQL
SELECT agent.*, entite.id_e, entite.denomination FROM agent
JOIN entite ON entite.siren = agent.siren
JOIN entite_ancetre ON entite.id_e = entite_ancetre.id_e
JOIN utilisateur_role ON entite_ancetre.id_e_ancetre = utilisateur_role.id_e
JOIN role_droit ON utilisateur_role.role = role_droit.role
WHERE droit = ? AND utilisateur_role.id_u = 1 AND (nom_patronymique LIKE ? OR prenom LIKE ?)
ORDER BY nom_patronymique, prenom
LIMIT $offset, $limit
SQL;
        return $this->query($sql, $this->getEntityReadRight(), "%$search%", "%$search%");
    }

    public function getAll($siren)
    {
        $sql = <<<SQL
SELECT agent.* FROM agent
JOIN entite ON entite.siren = agent.siren
WHERE agent.siren = ?
ORDER BY nom_patronymique, prenom
SQL;
        return $this->query($sql, $siren);
    }

    public function clean($siren)
    {
        $sql = <<<SQL
DELETE FROM agent WHERE siren = ?
SQL;
        $this->query($sql, $siren);
    }

    public function cleanAll()
    {
        $sql = <<<SQL
DELETE FROM agent
SQL;
        $this->query($sql);
    }

    private function getEntityReadRight(): string
    {
        return DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE);
    }
}
