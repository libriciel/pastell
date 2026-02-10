<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor0;

use Exception;
use Pastell\Service\Droit\DroitService;
use Pastell\Updater\Version;
use PastellLogger;
use RoleDroit;
use RoleSQL;

final class AddConnectorPermission implements Version
{
    public function __construct(
        private readonly RoleSQL $roleSQL,
        private readonly RoleDroit $roleDroit,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * Suppression de la constante CONNECTEUR_DROIT.
     * Il faut maintenant ajouter les droits 'connecteur:lecture' et 'connecteur:edition'
     * afin de gérer les connecteurs et les associations de types de documents.
     * Il faut reporter les droits (entite:) existants aux nouveaux droits (connecteur:) #1136
     * @throws Exception
     */
    public function update(): void
    {
        $this->logger?->info('Start');

        $roleDroitConnecteur = [];
        $roleDroitEntite = [];
        foreach ($this->roleSQL->getAllRole() as $role) {
            $droit = $this->roleSQL->getDroit($this->roleDroit->getAllDroit(), $role['role']);
            $this->roleDroitFilter($role['role'], $droit, DroitService::DROIT_CONNECTEUR, $roleDroitConnecteur);
            $this->roleDroitFilter($role['role'], $droit, DroitService::DROIT_ENTITE, $roleDroitEntite);
        }

        if (!empty($roleDroitConnecteur)) {
            $this->logger?->info(
                sprintf(
                    "Nothing to do. There are already connector permission for role: `%s`",
                    json_encode($roleDroitConnecteur)
                )
            );
        }

        $numberOfRole = count($roleDroitEntite);
        $this->logger?->info(
            sprintf(
                "There are %s role whith entite permission to copy to connector",
                $numberOfRole
            )
        );

        foreach ($roleDroitEntite as $role => $droitEntite) {
            foreach ($droitEntite as $droit) {
                list(, $type) = explode(":", $droit);
                $this->roleSQL->addDroit($role, DroitService::DROIT_CONNECTEUR . ":" . $type);
                $this->logger?->info(
                    sprintf(
                        "Add  %s:%s for %s",
                        DroitService::DROIT_CONNECTEUR,
                        $type,
                        $role
                    )
                );
            }
        }
    }

    private function roleDroitFilter(string $role, array $droit, string $familleDroit, array &$roleDroit): void
    {
        $droitFilter = array_filter($droit, static function ($value, $key) use ($familleDroit) {
            list($part) = explode(":", $key);
            return $value == 1 && $part === $familleDroit;
        }, ARRAY_FILTER_USE_BOTH);
        if (!empty($droitFilter)) {
            $roleDroit[$role] = array_keys($droitFilter);
        }
    }
}
