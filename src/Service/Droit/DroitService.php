<?php

namespace Pastell\Service\Droit;

use DocumentTypeFactory;
use RoleUtilisateur;

class DroitService
{
    public const DROIT_LECTURE = 'lecture';
    public const DROIT_ECRITURE = 'edition';
    public const DROIT_ACTION = 'action';

    public const DROIT_CONNECTEUR = 'connecteur';
    public const DROIT_SYSTEM = 'system';
    public const DROIT_ENTITE = 'entite';
    public const DROIT_UTILISATEUR = 'utilisateur';

    public function __construct(
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly bool $connectorActionPermission,
    ) {
    }

    private static function getPermission(string $part, string $action): string
    {
        return \sprintf('%s:%s', $part, $action);
    }

    public static function getDroitLecture(string $part): string
    {
        return self::getPermission($part, self::DROIT_LECTURE);
    }

    public static function getDroitEdition(string $part): string
    {
        return self::getPermission($part, self::DROIT_ECRITURE);
    }

    /**
     * @deprecated 4.1.8
     * In 5.0, make it static
     */
    public function getActionPermission(string $part): string
    {
        if ($this->connectorActionPermission) {
            return self::getPermission($part, self::DROIT_ACTION);
        }

        return self::getPermission($part, self::DROIT_ECRITURE);
    }

    /**
     * @param $id_u (pas possible de typer int. Authentification::getId() peut retourner false)
     * @param string $droit
     * @param $id_e (pas possible de typer int. Peut être '' EntiteControler::doEditionAction)
     * @return bool
     */
    public function hasDroit($id_u, string $droit, $id_e): bool
    {
        if ($id_u == 0) {
            return true;
        }
        if ($this->isRestrictedDroit($droit)) {
            return false;
        }
        return $this->roleUtilisateur->hasDroit($id_u, $droit, $id_e);
    }

    public function hasOneDroit(int $id_u, string $droit): bool
    {
        if ($this->isRestrictedDroit($droit)) {
            return false;
        }
        return $this->roleUtilisateur->hasOneDroit($id_u, $droit);
    }

    public function getAllDocumentLecture(int $id_u, int $id_e): array
    {
        $liste_type = $this->roleUtilisateur->getAllDocumentLecture($id_u, $id_e);
        foreach ($liste_type as $key => $type) {
            if ($this->documentTypeFactory->isRestrictedFlux($type)) {
                unset($liste_type[$key]);
            }
        }
        return $liste_type;
    }

    /**
     * @param $id_u (pas possible de typer. Authentification::getId() peut retourner false)
     * @param int $id_e
     * @return array
     */
    public function getAllDroitEntite($id_u, int $id_e): array
    {
        $data = $this->roleUtilisateur->getAllDroitEntite($id_u, $id_e);
        foreach ($data as $key => $droit) {
            if ($this->isRestrictedDroit($droit)) {
                unset($data[$key]);
            }
        }
        return array_values($data);
    }

    public function getAllDroit(int $id_u): array
    {
        $data = $this->roleUtilisateur->getAllDroit($id_u);
        foreach ($data as $key => $droit) {
            if ($this->isRestrictedDroit($droit)) {
                unset($data[$key]);
            }
        }
        return array_values($data);
    }

    public function hasDroitConnecteurLecture(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitLecture(self::DROIT_CONNECTEUR), $id_e);
    }

    public function hasDroitConnecteurEdition(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitEdition(self::DROIT_CONNECTEUR), $id_e);
    }

    public function hasConnectorActionPermission(int $entityId, int $userId): bool
    {
        return $this->hasDroit(
            $userId,
            $this->getActionPermission(self::DROIT_CONNECTEUR),
            $entityId,
        );
    }

    public function hasDroitUtilisateurLecture(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitLecture(self::DROIT_UTILISATEUR), $id_e);
    }

    public function clearRestrictedDroit(array $all_droit): array
    {
        foreach ($all_droit as $sql_droit => $checked) {
            if ($this->isRestrictedDroit($sql_droit)) {
                unset($all_droit[$sql_droit]);
            }
        }
        return $all_droit;
    }

    public function isRestrictedDroit(string $droit): bool
    {
        list($part) = explode(":", $droit);
        return $this->documentTypeFactory->isRestrictedFlux($part);
    }

    public function clearRestrictedConnecteur(array $list_connecteur, bool $global = false): array
    {
        if ($global) {
            foreach ($list_connecteur as $key => $connecteur) {
                if ($this->isRestrictedConnecteur($connecteur['id_connecteur'], true)) {
                    unset($list_connecteur[$key]);
                }
            }
        } else {
            foreach ($list_connecteur as $key => $connecteur) {
                if ($this->isRestrictedConnecteur($connecteur['id_connecteur'])) {
                    unset($list_connecteur[$key]);
                }
            }
        }
        return $list_connecteur;
    }

    public function isRestrictedConnecteur(string $id_connecteur, bool $global = false): bool
    {
        return $this->documentTypeFactory->isRestrictedConnecteur($id_connecteur, $global);
    }
}
