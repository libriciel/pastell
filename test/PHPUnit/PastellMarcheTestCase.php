<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Utilisateur\RoleService;

class PastellMarcheTestCase extends PastellTestCase
{
    public function reinitDatabase()
    {
        parent::reinitDatabase();

        $roleService = $this->getObjectInstancier()->getInstance(RoleService::class);

        $flux_id_list = [
            'pes-marche',
            'piece-marche',
            'piece-marche-par-etape',
            'dossier-marche'
        ];

        foreach ($flux_id_list as $id_flux) {
            $roleService->addDroit('admin', DroitService::getDroitFor($id_flux, DroitType::LECTURE));
            $roleService->addDroit('admin', DroitService::getDroitFor($id_flux, DroitType::EDITION));
        }
    }
}
