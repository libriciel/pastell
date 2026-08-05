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

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function indexAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_ROLE, DroitType::LECTURE);
        $this->setViewParameter('allRole', $this->getRoleSQL()->getAllRole());
        if ($this->hasDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_ROLE, DroitType::EDITION)) {
            $this->setViewParameter('nouveau_bouton_url', ["Ajouter" => "Role/edition"]);
        }
        $this->setViewParameter('page_title', "Rôles");
        $this->setViewParameter('template_milieu', "RoleIndex");
        $this->renderDefault();
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws NotFoundException
     */
    public function detailAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_ROLE, DroitType::LECTURE);
        $this->setViewParameter('role', $this->getGetInfo()->get('role'));
        $this->setDroitViewParameter(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_ROLE, DroitType::EDITION);
        $this->setViewParameter('role_info', $this->getRoleSQL()->getInfo($this->getViewParameterOrObject('role')));

        $roleDroit = $this->getInstance(RoleDroit::class);

        $all_droit_sql = $this->getRoleSQL()->getDroit($roleDroit->getAllDroit(), $role_id);
        $all_droit_utilisateur = $this->getObjectInstancier()->getInstance(DroitService::class)->clearRestrictedDroit($all_droit_sql);
        $this->setViewParameter('droits_administration', $this->getDroitsAdministration($all_droit_utilisateur));
        $this->setViewParameter('droits_type_dossier', $this->getDroitsTypeDossier($all_droit_utilisateur));

        $this->setViewParameter('page_title', "Gestion du rôle {$role_id} et des droits associés");
        $this->setViewParameter('template_milieu', 'RoleDetail');
        $this->renderDefault();
    }

    private const array DROIT_ADMINISTRATION_LIBELLE = [
        'entite' => 'Entité',
        'utilisateur' => 'Utilisateur',
        'role' => 'Rôle',
        'journal' => 'Journal',
        'system' => 'Système',
        'annuaire' => 'Annuaire',
        'connecteur' => 'Connecteur',
        'daemon' => 'Gestionnaire de tâches',
    ];

    private function getDroitsAdministration(array $all_droit_utilisateur): array
    {
        $droits_administration = [];
        foreach ($all_droit_utilisateur as $droit => $enabled) {
            $id_droit = explode(':', $droit, 2)[0];
            if (isset(self::DROIT_ADMINISTRATION_LIBELLE[$id_droit])) {
                $droits_administration[self::DROIT_ADMINISTRATION_LIBELLE[$id_droit]][$droit] = $enabled;
            }
        }

        $ordered = [];
        foreach (self::DROIT_ADMINISTRATION_LIBELLE as $libelle) {
            if (isset($droits_administration[$libelle])) {
                $ordered[$libelle] = $droits_administration[$libelle];
            }
        }
        return $ordered;
    }

    private function getDroitsTypeDossier(array $all_droit_utilisateur): array
    {
        $flux_info = [];
        /** @var DocumentTypeFactory $documentTypeFactory */
        $documentTypeFactory = $this->getInstance(DocumentTypeFactory::class);
        foreach ($documentTypeFactory->getAllType() as $type => $fluxList) {
            foreach ($fluxList as $type_dossier_id => $libelle) {
                $flux_info[$type_dossier_id]['libelle'] = $libelle;
                $flux_info[$type_dossier_id]['type'] = $type;
            }
        }

        $droits_type_dossier_by_categorie = [];
        foreach ($all_droit_utilisateur as $droit => $enabled) {
            $id_droit = explode(':', $droit, 2)[0];
            if (isset(self::DROIT_ADMINISTRATION_LIBELLE[$id_droit])) {
                continue;
            }
            $info = $flux_info[$id_droit];
            $droits_type_dossier_by_categorie[$info['type']][$id_droit]['libelle'] ??= $info['libelle'];
            $droits_type_dossier_by_categorie[$info['type']][$id_droit]['droits'][$droit] = $enabled;
        }

        foreach ($droits_type_dossier_by_categorie as &$group) {
            uasort($group, static fn(array $a, array $b): int => strcasecmp($a['libelle'], $b['libelle']));
        }
        unset($group);

        $currentLocale = setlocale(LC_COLLATE, '0');
        setlocale(LC_COLLATE, 'fr_FR.utf8');
        ksort($droits_type_dossier_by_categorie, SORT_LOCALE_STRING);
        setlocale(LC_COLLATE, $currentLocale);

        return $droits_type_dossier_by_categorie;
    }

    /**
     * @throws LastMessageException
     * @throws NotFoundException
     * @throws LastErrorException
     */
    public function editionAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_ROLE, DroitType::EDITION);
        $role = $this->getGetInfo()->get('role');

        if ($role) {
            $this->setViewParameter('nouveau', false);
            $this->setViewParameter('page_title', "Modification du rôle $role ");
            $role_info = $this->getRoleSQL()->getInfo($role);
            if (!$role_info) {
                throw new NotFoundException("Le rôle n'existe pas");
            }
            $this->setViewParameter('role_info', $role_info);
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

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doEditionAction(): void
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_ROLE, DroitType::EDITION);
        $role = $this->getPostInfo()->get('role');
        $role = preg_replace('/\s+/', '_', $role);
        $libelle = $this->getPostInfo()->get('libelle');
        $role_info = $this->getRoleSQL()->getInfo($role);

        if ($role_info && empty($libelle)) {
            $this->setLastError('Le libellé est obligatoire');
            $this->redirect("/Role/edition?role=$role");
        }
        if (!$role_info && (empty($libelle) || empty($role))) {
            $this->setLastError('Les deux champs sont obligatoires');
            $this->redirect('/Role/edition');
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
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_ROLE, DroitType::EDITION);
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

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function doDetailAction()
    {
        $this->checkDroitFor(EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_ROLE, DroitType::EDITION);
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
