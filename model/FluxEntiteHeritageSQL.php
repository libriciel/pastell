<?php

class FluxEntiteHeritageSQL extends SQL
{
    public const ALL_FLUX = '__all_flux';

    private $fluxEntiteSQL;
    private $entiteSQL;

    public function __construct(SQLQuery $sqlQuery, FluxEntiteSQL $fluxEntiteSQL, EntiteSQL $entiteSQL)
    {
        parent::__construct($sqlQuery);
        $this->fluxEntiteSQL = $fluxEntiteSQL;
        $this->entiteSQL = $entiteSQL;
    }

    public function getAllWithSameType($id_e)
    {
        if ($this->hasInheritanceAllFlux($id_e)) {
            $id_e_mere = $this->entiteSQL->getEntiteMere($id_e);
            return $this->getAllWithSameType($id_e_mere);
        }

        $result = $this->fluxEntiteSQL->getAllWithSameType($id_e);

        foreach ($result as $flux => $def) {
            $result[$flux]['inherited_flux'] = false;
        }
        $inherited_flux = $this->getInheritance($id_e);

        if ($inherited_flux) {
            $id_e_mere = $this->entiteSQL->getEntiteMere($id_e);
            $all_inherited = $this->getAllWithSameType($id_e_mere);
            foreach ($inherited_flux as $flux) {
                if (isset($all_inherited[$flux])) {
                    $result[$flux] = $all_inherited[$flux];
                } else {
                    $result[$flux] = [];
                }
                $result[$flux]['inherited_flux'] = true;
            }
        }

        return $result;
    }

    public function getConnecteurId($id_e, $flux, $connecteur_type, $num_same_type = 0)
    {
        $id_e = $this->getRealAncetreForFlux($id_e, $flux);
        return $this->fluxEntiteSQL->getConnecteurId($id_e, $flux, $connecteur_type, $num_same_type);
    }

    private function getRealAncetreForFlux($id_e, $flux)
    {
        $id_e_mere = $this->entiteSQL->getEntiteMere($id_e);
        if ($id_e_mere == 0) {
            return $id_e;
        }
        if ($this->hasInheritanceAllFlux($id_e)) {
            return $this->getRealAncetreForFlux($id_e_mere, $flux);
        }
        if ($this->hasInheritance($id_e, $flux)) {
            return $this->getRealAncetreForFlux($id_e_mere, $flux);
        }
        return $id_e;
    }

    public function setInheritance($id_e, $flux)
    {
        $id_e_mere = $this->entiteSQL->getEntiteMere($id_e);
        if (! $id_e_mere) {
            return;
        }
        $this->deleteInheritance($id_e, $flux);
        $sql = "INSERT INTO flux_entite_heritage(id_e,flux) VALUES (?,?)";
        $this->query($sql, $id_e, $flux);
    }

    public function deleteInheritance($id_e, $flux)
    {
        $sql = "DELETE FROM flux_entite_heritage WHERE id_e=? AND flux=?";
        $this->query($sql, $id_e, $flux);
    }

    public function getInheritance($id_e)
    {
        $sql = "SELECT flux FROM flux_entite_heritage WHERE id_e=?";
        return $this->queryOneCol($sql, $id_e);
    }

    public function hasInheritance($id_e, $flux): bool
    {
        $sql = "SELECT count(*) FROM flux_entite_heritage WHERE id_e=? AND flux=?";
        return $this->queryOne($sql, $id_e, $flux) >= 1 ;
    }

    public function setInheritanceAllFlux($id_e)
    {
        $this->setInheritance($id_e, self::ALL_FLUX);
    }

    public function hasInheritanceAllFlux($id_e)
    {
        return $this->hasInheritance($id_e, self::ALL_FLUX);
    }

    public function deleteInheritanceAllFlux($id_e)
    {
        $this->deleteInheritance($id_e, self::ALL_FLUX);
    }

    public function toogleInheritance($id_e, $flux)
    {
        if ($this->hasInheritance($id_e, $flux)) {
            $this->deleteInheritance($id_e, $flux);
        } else {
            $this->setInheritance($id_e, $flux);
        }
    }
}
