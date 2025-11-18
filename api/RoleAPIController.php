<?php

use Pastell\Service\Droit\DroitService;

class RoleAPIController extends BaseAPIController
{
    /**
     * @throws ForbiddenException
     */
    public function get()
    {
        $this->checkOneDroit(DroitService::getDroitLecture(DroitService::DROIT_ROLE));
        return $this->getRoleUtilisateur()->getAuthorizedRoleToDelegate($this->getUtilisateurId());
    }
}
