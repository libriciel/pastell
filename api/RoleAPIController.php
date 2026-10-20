<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Utilisateur\RoleDelegationService;

class RoleAPIController extends BaseAPIController
{
    public function __construct(
        private readonly RoleDelegationService $roleDelegationService,
    ) {
    }

    public function get()
    {
        $this->checkOneDroitFor(DroitService::DROIT_ROLE, DroitType::LECTURE);
        return $this->roleDelegationService->getAuthorizedRoleToDelegate((int) $this->getUtilisateurId());
    }
}
