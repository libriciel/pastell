<?php

use Pastell\Service\Entite\RootEntity;

class FluxEntiteHeritageSQL extends SQL
{
    public const ALL_FLUX = '__all_flux';

    public function __construct(
        SQLQuery $sqlQuery,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly EntiteSQL $entiteSQL,
    ) {
        parent::__construct($sqlQuery);
    }

    /**
     * @deprecated 4.1.26 Use FluxInheritanceService::getAllWithSameType() instead
     */
    public function getAllWithSameType(int $id_e): array
    {
        if ($this->hasInheritanceAllFlux($id_e)) {
            return $this->getAllWithSameType((int)$this->entiteSQL->getEntiteMere($id_e));
        }

        $association_list = $this->fluxEntiteSQL->getAllWithSameType($id_e);
        foreach ($association_list as $flux => $flux_association_list) {
            $association_list[$flux]['inherited_flux'] = false;
        }

        $inherited_flux_list = $this->getInheritance($id_e);
        if (!$inherited_flux_list) {
            return $association_list;
        }

        $parent_association_list = $this->getAllWithSameType((int)$this->entiteSQL->getEntiteMere($id_e));
        foreach ($inherited_flux_list as $flux) {
            $association_list[$flux] = $parent_association_list[$flux] ?? [];
            $association_list[$flux]['inherited_flux'] = true;
        }
        return $association_list;
    }

    /**
     * @deprecated 3.0 Use FluxInheritanceService::getAllWithSameType() instead
     */
    public function getAll(int $id_e): array
    {
        if ($this->hasInheritanceAllFlux($id_e)) {
            return $this->getAll((int)$this->entiteSQL->getEntiteMere($id_e));
        }

        $association_list = $this->fluxEntiteSQL->getAll($id_e);
        foreach ($association_list as $flux => $flux_association_list) {
            $association_list[$flux]['inherited_flux'] = false;
        }

        $inherited_flux_list = $this->getInheritance($id_e);
        if (!$inherited_flux_list) {
            return $association_list;
        }

        $parent_association_list = $this->getAll((int)$this->entiteSQL->getEntiteMere($id_e));
        foreach ($inherited_flux_list as $flux) {
            $association_list[$flux] = $parent_association_list[$flux] ?? [];
            $association_list[$flux]['inherited_flux'] = true;
        }
        return $association_list;
    }

    /**
     * @deprecated 4.1.26 Use FluxInheritanceService::getConnecteurId() instead
     */
    public function getConnecteurId(
        int $id_e,
        string $flux,
        string $connecteur_type,
        int $num_same_type = 0
    ): int|false {
        $id_ce = $this->fluxEntiteSQL->getConnecteurId(
            $this->getRealAncetreForFlux($id_e, $flux),
            $flux,
            $connecteur_type,
            $num_same_type
        );
        return $id_ce === false ? false : (int)$id_ce;
    }

    private function getRealAncetreForFlux(int $id_e, string $flux): int
    {
        $parent_id_e = (int)$this->entiteSQL->getEntiteMere($id_e);
        if ($parent_id_e === RootEntity::ID_E) {
            return $id_e;
        }
        if ($this->hasInheritanceAllFlux($id_e) || $this->hasInheritance($id_e, $flux)) {
            return $this->getRealAncetreForFlux($parent_id_e, $flux);
        }
        return $id_e;
    }

    /**
     * @deprecated 4.1.26 Use FluxInheritanceService::setInheritance() instead
     */
    public function setInheritance(int $id_e, string $flux): void
    {
        if (!$this->entiteSQL->getEntiteMere($id_e)) {
            return;
        }
        $this->deleteInheritance($id_e, $flux);
        $this->addInheritance($id_e, $flux);
    }

    public function addInheritance(int $id_e, string $flux): void
    {
        $sql = <<<SQL
INSERT INTO flux_entite_heritage(id_e, flux) VALUES (?, ?)
SQL;
        $this->query($sql, $id_e, $flux);
    }

    public function deleteInheritance(int $id_e, string $flux): void
    {
        $sql = <<<SQL
DELETE FROM flux_entite_heritage WHERE id_e = ? AND flux = ?
SQL;
        $this->query($sql, $id_e, $flux);
    }

    public function getInheritance(int $id_e): array
    {
        $sql = <<<SQL
SELECT flux FROM flux_entite_heritage WHERE id_e = ?
SQL;
        return $this->queryOneCol($sql, $id_e);
    }

    public function hasInheritance(int $id_e, string $flux): bool
    {
        $sql = <<<SQL
SELECT count(*) FROM flux_entite_heritage WHERE id_e = ? AND flux = ?
SQL;
        return $this->queryOne($sql, $id_e, $flux) >= 1;
    }

    /**
     * @deprecated 4.1.26 Use FluxInheritanceService::setInheritanceAllFlux() instead
     */
    public function setInheritanceAllFlux(int $id_e): void
    {
        $this->setInheritance($id_e, self::ALL_FLUX);
    }

    public function hasInheritanceAllFlux(int $id_e): bool
    {
        return $this->hasInheritance($id_e, self::ALL_FLUX);
    }

    public function deleteInheritanceAllFlux(int $id_e): void
    {
        $this->deleteInheritance($id_e, self::ALL_FLUX);
    }

    /**
     * @deprecated 4.1.26 Use FluxInheritanceService::toggleInheritance() instead
     */
    public function toogleInheritance(int $id_e, string $flux): void
    {
        if ($this->hasInheritance($id_e, $flux)) {
            $this->deleteInheritance($id_e, $flux);
            return;
        }
        $this->setInheritance($id_e, $flux);
    }
}
