<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Menu\MenuGaucheService;

class RoleControler extends PastellControler
{
    public function _beforeAction()
    {
        parent::_beforeAction();
        $this->setMenuGaucheSelect(MenuGaucheService::ROLE_INDEX);
        $this->setViewParameter('menu', $this->getInstance(MenuGaucheService::class)->getConfigurationMenu());
    }

    public function indexAction()
    {
        $this->verifDroit(0, "role:lecture");
        $this->setViewParameter('allRole', $this->getRoleSQL()->getAllRole());
        if ($this->hasDroit(0, "role:edition")) {
            $this->setViewParameter('nouveau_bouton_url', ["Ajouter" => "Role/edition"]);
        }
        $this->setViewParameter('page_title', "Rôles");
        $this->setViewParameter('template_milieu', "RoleIndex");
        $this->renderDefault();
    }

    public function detailAction()
    {
        $this->verifDroit(0, "role:lecture");
        $this->setViewParameter('role', $this->getGetInfo()->get('role'));
        $this->setViewParameter('role_edition', $this->hasDroit(0, "role:edition"));
        $this->setViewParameter('role_info', $this->getRoleSQL()->getInfo($this->getViewParameterOrObject('role')));

        /** @var RoleDroit $roleDroit */
        $roleDroit = $this->getInstance(RoleDroit::class);

        $all_droit = $roleDroit->getAllDroit();
        $all_droit_sql = $this->getRoleSQL()->getDroit($all_droit, $this->getViewParameterOrObject('role'));
        $this->setViewParameter('all_droit_utilisateur', $this->getObjectInstancier()->getInstance(DroitService::class)->clearRestrictedDroit($all_droit_sql));

        $this->setViewParameter('page_title', "Gestion du rôle {$this->getViewParameterOrObject('role')} et des droits associés");
        $this->setViewParameter('template_milieu', "RoleDetail");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function editionAction(): void
    {
        $this->verifDroit(0, 'role:edition');
        $role = $this->getGetInfo()->get('role');

        if ($role) {
            $this->setViewParameter('nouveau', false);
            $this->setViewParameter('page_title', "Modification du rôle $role ");
            $this->setViewParameter('role_info', $this->getRoleSQL()->getInfo($role));
            $this->setViewParameter('cancelRedirectUrl', '/Role/detail?role=' . $role);
        } else {
            $this->setViewParameter('nouveau', true);
            $this->setViewParameter('page_title', "Ajout d'un rôle");
            $this->setViewParameter('role_info', ['libelle' => '','role' => '']);
            $this->setViewParameter('cancelRedirectUrl', '/Role/index');
        }
        $this->setViewParameter('template_milieu', 'RoleEdition');
        $this->renderDefault();
    }

    public function doEditionAction()
    {
        $this->verifDroit(0, "role:edition");
        $role = $this->getPostInfo()->get('role');
        $role = preg_replace("/\s+/", "_", $role);
        $libelle = $this->getPostInfo()->get('libelle');

        if (empty($libelle) || empty($role)) {
            $this->setLastError("Les deux champs sont obligatoires");
            $this->redirect("/Role/edition");
        }

        $this->getRoleSQL()->edit($role, $libelle);
        if ($this->getPostInfo()->get('nouveau')) {
            $this->getRoleSQL()->addDroit($role, DroitService::getDroitFor(DroitService::DROIT_JOURNAL, DroitType::LECTURE));
            $this->getRoleSQL()->addDroit($role, DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE));
        }
        $this->redirect("/Role/detail?role=$role");
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doDeleteAction()
    {
        $this->verifDroit(0, "role:edition");
        $role = $this->getPostInfo()->get('role');

        if ($this->getRoleUtilisateur()->anybodyHasRole($role)) {
            $this->setLastError("Le rôle $role est attribué à des utilisateurs");
            $this->redirect("/Role/detail?role=$role");
        }

        if ($this->getInstance(AnnuaireRoleSQL::class)->getNbByRole($role) > 0) {
            $this->setLastError("Le rôle $role est utilisé dans l'annuaire");
            $this->redirect("/Role/detail?role=$role");
        }

        $this->getRoleSQL()->delete($role);
        $this->setLastMessage("Le rôle $role a été supprimé");
        $this->redirect("/Role/index");
    }

    public function doDetailAction()
    {
        $this->verifDroit(0, "role:edition");
        $role = $this->getPostInfo()->get('role');
        $droit = $this->getPostInfo()->get('droit', []);

        $roleDroit = $this->getInstance(RoleDroit::class);
        if ($roleDroit->areExistingRolesDroits($droit) === false) {
            $this->redirect("/Role/detail?role=$role");
        }

        $this->getRoleSQL()->updateDroit($role, $droit);
        $this->setLastMessage("Le rôle $role a été mis à jour");
        $this->redirect("/Role/detail?role=$role");
    }
}
