<?php

use Pastell\Service\Droit\DroitService;

class UtilisateurRoleAPIController extends BaseAPIController
{
    public const ALL_ROLES = "ALL_ROLES";

    public function __construct(
        private readonly UtilisateurSQL $utilisateur,
        private readonly RoleSQL $roleSQL,
        private readonly EntiteSQL $entiteSQL
    ) {
    }

    /**
     * @param $id_u
     * @return array|bool|mixed
     * @throws NotFoundException
     */
    private function verifExists($id_u)
    {
        $infoUtilisateur = $this->utilisateur->getInfo($id_u);
        if (!$infoUtilisateur) {
            throw new NotFoundException("L'utilisateur n'existe pas : {id_u=$id_u}");
        }
        return $infoUtilisateur;
    }

    /**
     * @param $role
     * @throws NotFoundException
     */
    private function verifRoleExists($role)
    {
        if (!$this->roleSQL->getInfo($role)) {
            throw new NotFoundException("Le role spécifié n'existe pas {role=$role}");
        }
    }

    /**
     * @return array
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function get()
    {
        $id_u = $this->getFromQueryArgs(0);
        $id_e = $this->getFromRequest('id_e', 0);
        $this->checkDroit($id_e, DroitService::getDroitLecture(DroitService::DROIT_UTILISATEUR));

        $this->verifExists($id_u);

        $role_list = $this->getRoleUtilisateur()->getRole($id_u);
        $all_droit_utilisateur = $this->getDroitService()->getAllDroitEntite($id_u, $id_e);

        // Construction du tableau de retour
        $result = [];
        foreach ($role_list as $id_u_role => $role_info) {
            $result[$id_u_role] = [
                'id_u' => (string)$role_info['id_u'],
                'role' => $role_info['role'],
                'id_e' => (string)$role_info['id_e'],
                'droits' => array_keys($this->roleSQL->getDroit($all_droit_utilisateur, $role_info['role']))
            ];
        }

        return $result;
    }

    /**
     * @return mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function post()
    {
        $id_u = $this->getFromQueryArgs(0);
        $role = $this->getFromRequest('role');
        $id_e = $this->getFromRequest('id_e');
        return $this->addRoleUtilisateur($id_u, $role, $id_e);
    }

    /**
     * @return mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function delete()
    {
        $id_u = $this->getFromQueryArgs(0);
        $role = $this->getFromRequest('role');
        $id_e = $this->getFromRequest('id_e');
        return $this->deleteRoleUtilisateur($id_u, $role, $id_e);
    }

    /**
     * @param $id_u
     * @param $role
     * @param $id_e
     * @return mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    private function addRoleUtilisateur($id_u, $role, $id_e)
    {
        $this->checkDroit($id_e, DroitService::getDroitEdition(DroitService::DROIT_UTILISATEUR));
        $this->verifExists($id_u);
        $this->verifRoleExists($role);

        if (!$this->getRoleUtilisateur()->hasRole($id_u, $role, $id_e)) {
            $this->getRoleUtilisateur()->addRole($id_u, $role, $id_e);
        }

        $result['result'] = self::RESULT_OK;
        return $result;
    }

    /**
     * @param $id_u
     * @param $role
     * @param $id_e
     * @return mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    private function deleteRoleUtilisateur($id_u, $role, $id_e)
    {
        $this->checkDroit($id_e, DroitService::getDroitEdition(DroitService::DROIT_UTILISATEUR));
        $this->verifExists($id_u);

        if ($role === self::ALL_ROLES) {
            $this->getRoleUtilisateur()->removeAllRolesEntite($id_u, $id_e);
        } else {
            $this->verifRoleExists($role);
            $this->getRoleUtilisateur()->removeRole($id_u, $role, $id_e);
        }

        $result['result'] = self::RESULT_OK;
        return $result;
    }

    /**
     * @return array|mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function compatV1Edition()
    {
        $function = $this->getFromQueryArgs(2);
        if ($function == 'add') {
            return $this->addSeveralAction();
        } else {
            return $this->deleteSeveralAction();
        }
    }


    /**
     * @return array|mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws Exception
     */
    public function addSeveralAction()
    {
        $data = $this->getRequest();
        $infoUtilisateurExistant = $this->utilisateur->getUserFromData($data);
        $id_u = $infoUtilisateurExistant['id_u'];

        $id_e = $this->getFromRequest('id_e', 0);
        if ($id_e) {
            $infoEntiteExistante = $this->entiteSQL->getEntiteFromData($data);
            $id_e = $infoEntiteExistante['id_e'];
        }

        $roles = $this->getFromRequest('role');
        if (! $roles) {
            return [];
        }

        $deleteRoles = $this->getFromRequest('deleteRoles', false);
        if ($deleteRoles) {
            $this->deleteRoleUtilisateur($id_u, self::ALL_ROLES, $id_e);
        }

        if (is_array($roles)) {
            $result = [];
            foreach ($roles as $role) {
                $result[] = $this->addRoleUtilisateur($id_u, $role, $id_e);
            }
        } else {
            $result = $this->addRoleUtilisateur($id_u, $roles, $id_e);
        }
        return $result;
    }

    /**
     * @return array|mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws Exception
     */
    public function deleteSeveralAction()
    {
        $data = $this->getRequest();

        $infoUtilisateurExistant = $this->utilisateur->getUserFromData($data);
        $infoEntiteExistante = $this->entiteSQL->getEntiteFromData($data);

        $roles = $this->getFromRequest('role');

        if (! $roles) {
            return [];
        }

        $id_e = $infoEntiteExistante['id_e'];
        $id_u = $infoUtilisateurExistant['id_u'];

        if (is_array($roles)) {
            $result = [];
            foreach ($roles as $role) {
                $result[] = $this->deleteRoleUtilisateur($id_u, $role, $id_e);
            }
        } else {
            $result = $this->deleteRoleUtilisateur($id_u, $roles, $id_e);
        }
        return $result;
    }
}
