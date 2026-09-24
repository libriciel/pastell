<?php

declare(strict_types=1);

namespace Pastell\Service\Menu;

use DaemonSQL;
use EntiteSQL;
use NotFoundException;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class MenuGaucheService
{
    public const string SYSTEM_INDEX = 'System/index';
    public const string SYSTEM_LOGIN_PAGE_CONFIGURATION = 'System/loginPageConfiguration';
    public const string SYSTEM_FLUX = 'System/flux';
    public const string SYSTEM_DEFINITION = 'System/definition';
    public const string SYSTEM_CONNECTEUR = 'System/connecteur';
    public const string SYSTEM_MAGIC_LINK = 'System/magicLink';
    public const string SYSTEM_MAGIC_LINK_HISTORY = 'System/magicLinkHistory';

    public const string ROLE_INDEX = 'Role/index';

    public const string EXTENSION_INDEX = 'Extension/index';

    public const string TYPE_DOSSIER_LIST = 'TypeDossier/list';

    public const string DAEMON_INDEX = 'Daemon/index';
    public const string DAEMON_VERROU = 'Daemon/verrou';
    public const string DAEMON_JOB = 'Daemon/job';
    public const string DAEMON_JOB_ACTIF = 'Daemon/job?filtre=actif';
    public const string DAEMON_FREQUENCE_CONFIGURATION = 'Daemon/frequenceConfiguration';
    public const string DAEMON_CONFIGURATION = 'Daemon/configuration';

    public const string ENTITE_DETAIL = 'Entite/detail';
    public const string ENTITE_UTILISATEUR = 'Entite/utilisateur';
    public const string ENTITE_CONNECTEUR_LOCAL = 'Entite/connecteur?global=0';
    public const string ENTITE_CONNECTEUR_GLOBAL = 'Entite/connecteur?global=1';
    public const string ENTITE_EXPORT_CONFIG = 'Entite/exportConfig';
    public const string ENTITE_IMPORT_CONFIG = 'Entite/importConfig';
    public const string ENTITE_AGENTS = 'Entite/agents';
    public const string ENTITE_DAEMON = 'Entite/daemon';
    public const string ENTITE_JOB = 'Entite/job';
    public const string ENTITE_JOB_ACTIF = 'Entite/job?filtre=actif';
    public const string ENTITE_DAEMON_ADMIN = 'Entite/daemonAdmin';

    public const string FLUX_INDEX = 'Flux/index';

    public const string DOCUMENT_LIST = 'Document/list';

    public const string MAILSEC_ANNUAIRE = 'MailSec/annuaire';
    public const string MAILSEC_GROUPES = 'MailSec/groupeList';
    public const string MAILSEC_GROUPES_ROLES = 'MailSec/groupeRoleList';


    public function __construct(
        private readonly DroitService $droitService,
        private readonly DaemonSQL $daemonSQL,
    ) {
    }

    public function getConfigurationMenu(): array
    {
        return [
            'Auto-test du système' => [
                MenuGaucheOption::fromLien('Test du système', self::SYSTEM_INDEX),
            ],
            'Configuration' => [
                MenuGaucheOption::fromLien('Configuration de la page de connexion', self::SYSTEM_LOGIN_PAGE_CONFIGURATION),
                MenuGaucheOption::fromLien('Rôles', self::ROLE_INDEX),
                MenuGaucheOption::fromLien('Extensions', self::EXTENSION_INDEX),
            ],
            'Types de dossier' => [
                MenuGaucheOption::fromLien('Types de dossier disponibles', self::SYSTEM_FLUX),
                MenuGaucheOption::fromLien('Types de dossier personnalisés (studio)', self::TYPE_DOSSIER_LIST),
                MenuGaucheOption::fromLien('Définition des types de dossier', self::SYSTEM_DEFINITION),
            ],
            'Connecteurs' => [
                MenuGaucheOption::fromLien('Connecteurs disponibles', self::SYSTEM_CONNECTEUR),
            ],
            'Accès temporaires' => [
                MenuGaucheOption::fromLien('Accès actifs', self::SYSTEM_MAGIC_LINK),
                MenuGaucheOption::fromLien('Historique des accès', self::SYSTEM_MAGIC_LINK_HISTORY),
            ],
        ];
    }

    public function getDaemonMenu(int $id_u): array
    {
        $daemon_edition = $this->droitService->hasDroitFor($id_u, EntiteSQL::ID_E_ENTITE_RACINE, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $configuration_options = [];
        if ($daemon_edition) {
            $configuration_options = [
                MenuGaucheOption::fromLien('Fréquence des connecteurs', self::DAEMON_FREQUENCE_CONFIGURATION),
                MenuGaucheOption::fromLien('Configuration des gestionnaires de tâches', self::DAEMON_CONFIGURATION),
            ];
        }
        return [
            'Tâches automatiques' => [
                MenuGaucheOption::fromLien('Gestionnaire de tâches', self::DAEMON_INDEX),
                MenuGaucheOption::fromLien("Files d'attente", self::DAEMON_VERROU),
                MenuGaucheOption::fromLien('Tous les travaux', self::DAEMON_JOB),
                MenuGaucheOption::fromLien('Travaux actifs', self::DAEMON_JOB_ACTIF),
            ],
            'Configuration' => $configuration_options,
        ];
    }

    public function getDocumentMenu(array $all_module, int $id_e): array
    {
        $menu = [];
        foreach ($all_module as $type_flux => $les_flux) {
            $menu[$type_flux] = [];
            foreach ($les_flux as $flux_id => $nom) {
                $menu[$type_flux][] = MenuGaucheOption::withParameters($nom, "Document/list?type=$flux_id", ['id_e' => $id_e]);
            }
        }
        return $menu;
    }

    /**
     * @throws NotFoundException
     */
    public function getEntiteMenu(int $id_e, int $id_u): array
    {
        $utilisateur_lecture = $this->droitService->hasDroitFor($id_u, $id_e, DroitService::DROIT_UTILISATEUR, DroitType::LECTURE);
        $connecteur_lecture = $this->droitService->hasDroitFor($id_u, $id_e, DroitService::DROIT_CONNECTEUR, DroitType::LECTURE);
        $system_edition = $this->droitService->hasDroitFor($id_u, $id_e, DroitService::DROIT_SYSTEM, DroitType::EDITION);
        $annuaire_lecture = $this->droitService->hasDroitFor($id_u, $id_e, DroitService::DROIT_ANNUAIRE, DroitType::LECTURE);
        $daemon_lecture = $this->droitService->hasDroitFor($id_u, $id_e, DroitService::DROIT_DAEMON, DroitType::LECTURE);
        $daemon_edition = $this->droitService->hasDroitFor($id_u, $id_e, DroitService::DROIT_DAEMON, DroitType::EDITION);
        $daemon_exists = $this->daemonSQL->getDaemonByEntity($id_e);

        $administration_options = [
            MenuGaucheOption::withParameters('Informations (entités)', self::ENTITE_DETAIL, ['id_e' => $id_e]),
        ];
        if ($utilisateur_lecture) {
            $administration_options[] = MenuGaucheOption::withParameters('Utilisateurs', self::ENTITE_UTILISATEUR, ['id_e' => $id_e]);
        }
        if ($connecteur_lecture) {
            $entite_racine = $id_e === EntiteSQL::ID_E_ENTITE_RACINE;
            $administration_options[] = MenuGaucheOption::withParameters(
                $entite_racine ? "Connecteurs d'entités" : 'Connecteurs',
                self::ENTITE_CONNECTEUR_LOCAL,
                ['id_e' => $id_e]
            );
            if ($entite_racine) {
                $administration_options[] = MenuGaucheOption::withParameters('Connecteurs globaux', self::ENTITE_CONNECTEUR_GLOBAL, ['id_e' => $id_e]);
            }
            $administration_options[] = MenuGaucheOption::withParameters(
                $id_e ? 'Types de dossier (association)' : 'Associations connecteurs globaux',
                self::FLUX_INDEX,
                ['id_e' => $id_e],
            );
        }

        if ($system_edition) {
            $administration_options[] = MenuGaucheOption::withParameters('Export de la configuration', self::ENTITE_EXPORT_CONFIG, ['id_e' => $id_e]);
            $administration_options[] = MenuGaucheOption::withParameters('Import de la configuration', self::ENTITE_IMPORT_CONFIG, ['id_e' => $id_e]);
        }

        $daemon_options = [];
        if (($daemon_exists || $id_e === EntiteSQL::ID_E_ENTITE_RACINE) && $daemon_lecture) {
            $daemon_options = [
                MenuGaucheOption::withParameters('Gestionnaire de tâches', self::ENTITE_DAEMON, ['id_e' => $id_e]),
                MenuGaucheOption::withParameters('Tous les travaux', self::ENTITE_JOB, ['id_e' => $id_e]),
                MenuGaucheOption::withParameters('Travaux actifs', self::ENTITE_JOB_ACTIF, ['id_e' => $id_e]),
            ];

            if ($daemon_edition) {
                $daemon_options[] = MenuGaucheOption::withParameters('Administration du gestionnaire de tâches', self::ENTITE_DAEMON_ADMIN, ['id_e' => $id_e]);
            }
        }

        $annuaire_options = [];
        if ($annuaire_lecture) {
            $annuaire_options = [
                MenuGaucheOption::withParameters('Contacts', self::MAILSEC_ANNUAIRE, ['id_e' => $id_e]),
                MenuGaucheOption::withParameters('Groupes', self::MAILSEC_GROUPES, ['id_e' => $id_e]),
                MenuGaucheOption::withParameters('Groupes basés sur des roles', self::MAILSEC_GROUPES_ROLES, ['id_e' => $id_e]),
            ];
        }

        $donnees_options = [];
        $donnees_options[] = MenuGaucheOption::withParameters('Agents (Actes)', self::ENTITE_AGENTS, ['id_e' => $id_e]);

        return [
            'Administration' => $administration_options,
            'Tâches automatiques' => $daemon_options,
            'Annuaire (mail sécurisé)' => $annuaire_options,
            'Données pour les types de dossier' => $donnees_options,
        ];
    }
}
