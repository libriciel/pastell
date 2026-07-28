<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class RoleAPIController extends BaseAPIController
{
    public function get()
    {
        $this->checkOneDroitFor(DroitService::DROIT_ROLE, DroitType::LECTURE);
        return $this->getRoleUtilisateur()->getAuthorizedRoleToDelegate($this->getUtilisateurId());
    }
}
