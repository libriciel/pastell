<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class PastellMarcheTestCase extends PastellTestCase
{
    public function reinitDatabase()
    {
        parent::reinitDatabase();

        /** @var RoleSQL $roleSQL */
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);

        $flux_id_list = [
            'pes-marche',
            'piece-marche',
            'piece-marche-par-etape',
            'dossier-marche'
        ];

        foreach ($flux_id_list as $id_flux) {
            $roleSQL->addDroit('admin', DroitService::getDroitFor($id_flux, DroitType::LECTURE));
            $roleSQL->addDroit('admin', DroitService::getDroitFor($id_flux, DroitType::EDITION));
        }
    }
}
