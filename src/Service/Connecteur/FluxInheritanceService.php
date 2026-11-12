<?php

declare(strict_types=1);

namespace Pastell\Service\Connecteur;

use EntiteSQL;
use FluxEntiteHeritageSQL;
use FluxEntiteSQL;
use Pastell\Service\Entite\RootEntity;

final class FluxInheritanceService
{
    public function __construct(
        private readonly FluxEntiteHeritageSQL $fluxEntiteHeritageSQL,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly EntiteSQL $entiteSQL,
    ) {
    }

    public function getAllWithSameType(int $id_e): array
    {
        if ($this->fluxEntiteHeritageSQL->hasInheritanceAllFlux($id_e)) {
            return $this->getAllWithSameType((int)$this->entiteSQL->getEntiteMere($id_e));
        }

        $association_list = $this->fluxEntiteSQL->getAllWithSameType($id_e);
        foreach ($association_list as $flux => $flux_association_list) {
            $association_list[$flux]['inherited_flux'] = false;
        }

        $inherited_flux_list = $this->fluxEntiteHeritageSQL->getInheritance($id_e);
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

    public function getConnecteurId(
        int $id_e,
        string $flux,
        string $connecteur_type,
        int $num_same_type = 0
    ): int|false {
        $id_ce = $this->fluxEntiteSQL->getConnecteurId(
            $this->getEffectiveEntityForFlux($id_e, $flux),
            $flux,
            $connecteur_type,
            $num_same_type
        );
        return $id_ce === false ? false : (int)$id_ce;
    }

    public function setInheritance(int $id_e, string $flux): void
    {
        if ((int)$this->entiteSQL->getEntiteMere($id_e) === RootEntity::ID_E) {
            return;
        }
        $this->fluxEntiteHeritageSQL->deleteInheritance($id_e, $flux);
        $this->fluxEntiteHeritageSQL->addInheritance($id_e, $flux);
    }

    public function setInheritanceAllFlux(int $id_e): void
    {
        $this->setInheritance($id_e, FluxEntiteHeritageSQL::ALL_FLUX);
    }

    public function toggleInheritance(int $id_e, string $flux): void
    {
        if ($this->fluxEntiteHeritageSQL->hasInheritance($id_e, $flux)) {
            $this->fluxEntiteHeritageSQL->deleteInheritance($id_e, $flux);
            return;
        }
        $this->setInheritance($id_e, $flux);
    }

    private function getEffectiveEntityForFlux(int $id_e, string $flux): int
    {
        $parent_id_e = (int)$this->entiteSQL->getEntiteMere($id_e);
        while (
            $parent_id_e !== RootEntity::ID_E
            && (
                $this->fluxEntiteHeritageSQL->hasInheritanceAllFlux($id_e)
                || $this->fluxEntiteHeritageSQL->hasInheritance($id_e, $flux)
            )
        ) {
            $id_e = $parent_id_e;
            $parent_id_e = (int)$this->entiteSQL->getEntiteMere($id_e);
        }
        return $id_e;
    }
}
