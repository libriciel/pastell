<?php

use Pastell\Service\Entite\RootEntity;

class FluxEntiteSQL extends SQL
{
    public const FLUX_GLOBAL_NAME = 'global';

    private function getFluxName($id_e, $flux)
    {
        if ($id_e === RootEntity::ID_E) {
            $flux = self::FLUX_GLOBAL_NAME;
        }
        return $flux;
    }

    public function getConnecteur($id_e, $flux, $connecteur_type, $num_same_type = 0)
    {
        $flux = $this->getFluxName($id_e, $flux);
        $sql = <<<SQL
SELECT flux_entite.*, connecteur_entite.*, entite.denomination FROM flux_entite
JOIN connecteur_entite ON flux_entite.id_ce = connecteur_entite.id_ce
LEFT JOIN entite ON connecteur_entite.id_e = entite.id_e
WHERE flux_entite.id_e = ? AND flux = ? AND flux_entite.type = ? AND flux_entite.num_same_type = ?
SQL;
        return $this->queryOne($sql, $id_e, $flux, $connecteur_type, $num_same_type);
    }

    public function getConnecteurId($id_e, $flux, $connecteur_type, $num_same_type = 0)
    {
        $flux = $this->getFluxName($id_e, $flux);
        $sql = <<<SQL
SELECT flux_entite.id_ce FROM flux_entite
JOIN connecteur_entite ON flux_entite.id_ce = connecteur_entite.id_ce
WHERE flux_entite.id_e = ? AND flux = ? AND flux_entite.type = ? AND flux_entite.num_same_type = ?
SQL;
        return $this->queryOne($sql, $id_e, $flux, $connecteur_type, $num_same_type);
    }

    public function getConnecteurById($id_fe)
    {
        $sql = <<<SQL
SELECT * FROM flux_entite WHERE id_fe = ?
SQL;
        return $this->queryOne($sql, $id_fe);
    }

    public function getAllWithSameType($id_e)
    {
        $sql = <<<SQL
SELECT flux_entite.*, connecteur_entite.*, entite.denomination FROM flux_entite
JOIN connecteur_entite ON flux_entite.id_ce = connecteur_entite.id_ce
LEFT JOIN entite ON connecteur_entite.id_e = entite.id_e
WHERE flux_entite.id_e = ?
SQL;
        $result = [];
        foreach ($this->query($sql, $id_e) as $line) {
            $result[$line['flux']][$line['type']][$line['num_same_type']] = $line;
        }
        return $result;
    }

    /**
     * @param $id_e
     * @return array
     * @deprecated 3.0 use getAllWithSameType() instead
     */
    public function getAll($id_e)
    {
        $sql = "SELECT flux_entite.*,connecteur_entite.*,entite.denomination FROM flux_entite" .
                " JOIN connecteur_entite ON flux_entite.id_ce=connecteur_entite.id_ce " .
                " LEFT JOIN entite ON connecteur_entite.id_e=entite.id_e " .
                " WHERE flux_entite.id_e=?";
        $result = [];
        foreach ($this->query($sql, $id_e) as $line) {
            $result[$line['flux']][$line['type']] = $line;
        }
        return $result;
    }

    public function getAllFluxEntite($id_e, $flux = null, $type = null)
    {
        $sql = 'SELECT * FROM flux_entite WHERE id_e = ?';
        $data = [$id_e];
        if ($flux) {
            $sql .= ' AND flux = ?';
            $data[] = $flux;
        }
        if ($type) {
            $sql .= ' AND type = ?';
            $data[] = $type;
        }
        $sql .= ' ORDER BY id_fe';
        return $this->query($sql, $data);
    }

    public function addConnecteur($id_e, $flux, $type, $id_ce, $num_same_type = 0)
    {
        $flux = $this->getFluxName($id_e, $flux);
        /* @deprecated - en V1.3.9 (#1346) c'est ConnecteurAssociationService::addConnecteurAssociation qui se charge de faire deleteConnecteurAssociation avant INSERT */
        $this->deleteConnecteur($id_e, $flux, $type, $num_same_type); // À supprimer
        $sql = <<<SQL
INSERT INTO flux_entite (id_e, flux, type, id_ce, num_same_type) VALUES (?, ?, ?, ?, ?)
SQL;
        $this->query($sql, $id_e, $flux, $type, $id_ce, $num_same_type);
        return $this->lastInsertId();
    }

    public function deleteConnecteur($id_e, $flux, $type, $num_same_type = 0)
    {
        $flux = $this->getFluxName($id_e, $flux);
        $sql = <<<SQL
DELETE FROM flux_entite WHERE id_e = ? AND type = ? AND flux = ? AND num_same_type = ?
SQL;
        $this->query($sql, $id_e, $type, $flux, $num_same_type);
    }

    public function removeConnecteur($id_fe)
    {
        $sql = <<<SQL
DELETE FROM flux_entite WHERE id_fe = ?
SQL;
        $this->query($sql, $id_fe);
    }

    public function getFluxByConnecteur($id_ce)
    {
        $sql = <<<SQL
SELECT flux FROM flux_entite
JOIN connecteur_entite ON flux_entite.id_ce = connecteur_entite.id_ce
WHERE connecteur_entite.id_ce = ?
SQL;
        return $this->queryOneCol($sql, $id_ce);
    }

    /**
     * @deprecated 4.1.26 Use ConnecteurAssociationService::getUniqueAssociatedFlux() instead
     */
    public function getUsedByConnecteurIfUnique($id_ce, $id_e): ?string
    {
        $all_used = $this->getUsedByConnecteur($id_ce, null, $id_e);

        if (count($all_used) === 1) {
            return $all_used[0]['flux'];
        }
        return null;
    }

    public function getUsedByConnecteur($id_ce, $flux = null, $id_e = null)
    {
        $sql = 'SELECT * FROM flux_entite WHERE id_ce = ?';
        $data = [$id_ce];
        if ($flux) {
            $sql .= ' AND flux = ?';
            $data[] = $flux;
        }
        if ($id_e) {
            $sql .= ' AND id_e = ?';
            $data[] = $id_e;
        }
        return $this->query($sql, $data);
    }

    /**
     * @param $id_ce
     * @return array
     * @deprecated use getFluxByConnecteur() instead
     */
    public function isUsed($id_ce)
    {
        return $this->getFluxByConnecteur($id_ce);
    }

    public function getEntiteByFlux($flux)
    {
        $sql = <<<SQL
SELECT DISTINCT entite.id_e, entite.denomination FROM flux_entite
JOIN entite ON flux_entite.id_e = entite.id_e
WHERE flux = ?
SQL;
        return $this->query($sql, $flux);
    }

    public function getAssociations(string $module): array
    {
        $sql = <<<SQL
SELECT * FROM flux_entite WHERE flux = ?
SQL;
        return $this->query($sql, $module);
    }

    public function getAssociatedConnectorsById(string $connectorId): array
    {
        $sql = <<<SQL
SELECT DISTINCT(flux_entite.id_ce), flux_entite.id_e
FROM flux_entite
INNER JOIN (
    SELECT connecteur_entite.id_ce
    FROM connecteur_entite
    WHERE connecteur_entite.id_connecteur = ?
) ce ON flux_entite.id_ce = ce.id_ce
SQL;
        return $this->query($sql, $connectorId);
    }

    public function getFluxAndEntityByConnectorId(string $id_ce): array
    {
        $sql = <<<SQL
SELECT flux_entite.id_e, flux_entite.flux FROM flux_entite
JOIN connecteur_entite ON flux_entite.id_ce = connecteur_entite.id_ce
WHERE connecteur_entite.id_ce = ?
SQL;
        return $this->query($sql, $id_ce);
    }
}
