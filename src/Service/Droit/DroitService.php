<?php

namespace Pastell\Service\Droit;

use DocumentTypeFactory;
use EntiteSQL;
use NotFoundException;
use RoleUtilisateur;
use UtilisateurSQL;

class DroitService
{
    /**
     * @deprecated 4.1.21 Use DroitType::LECTURE instead
     */
    public const DROIT_LECTURE = 'lecture';

    /**
     * @deprecated 4.1.21 Use DroitType::EDITION instead
     */
    public const DROIT_ECRITURE = 'edition';

    /**
     * @deprecated 4.1.21 Use DroitType::ACTION instead
     */
    public const DROIT_ACTION = 'action';

    /**
     * @deprecated 4.1.21 Use DroitType::CREATION instead
     */
    public const DROIT_CREATION = 'creation';

    /**
     * @deprecated 4.1.21 Use DroitType::SUPPRESSION instead
     */
    public const DROIT_SUPPRESSION = 'suppression';

    public const DROIT_CONNECTEUR = 'connecteur';
    public const DROIT_SYSTEM = 'system';
    public const DROIT_ENTITE = 'entite';
    public const DROIT_UTILISATEUR = 'utilisateur';
    public const DROIT_DAEMON = 'daemon';
    public const DROIT_JOURNAL = 'journal';
    public const DROIT_ROLE = 'role';
    public const DROIT_ANNUAIRE = 'annuaire';

    public function __construct(
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly EntiteSQL $entiteSQL,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly bool $connectorActionPermission,
    ) {
    }


    /**
     * @deprecated 4.1.21 Use DroitService::getDroitFor() instead
     */
    public static function getDroitLecture(string $part): string
    {
        return self::getDroitFor($part, DroitType::LECTURE);
    }

    /**
     * @deprecated 4.1.21 Use DroitService::getDroitFor() instead
     */
    public static function getDroitEdition(string $part): string
    {
        return self::getDroitFor($part, DroitType::EDITION);
    }

    /**
     * @deprecated 4.1.21 Use DroitService::getDroitFor() instead
     */
    public function getActionPermission(string $part): string
    {
        if ($this->connectorActionPermission) {
            return self::getDroitFor($part, DroitType::ACTION);
        }
        return self::getDroitEdition($part);
    }

    /**
     * @param $id_u (pas possible de typer int. Authentification::getId() peut retourner false)
     * @param string $droit
     * @param $id_e (pas possible de typer int. Peut être '' EntiteControler::doEditionAction)
     * @return bool
     * @deprecated 4.1.21 Use hasDroitFor() instead
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

    /**
     * @deprecated 4.1.21 Use DroitService::hasDroitFor() instead
     */
    public function hasDroitConnecteurLecture(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitFor(self::DROIT_CONNECTEUR, DroitType::LECTURE), $id_e);
    }

    /**
     * @deprecated 4.1.21 Use DroitService::hasDroitFor() instead
     */
    public function hasDroitConnecteurEdition(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitFor(self::DROIT_CONNECTEUR, DroitType::EDITION), $id_e);
    }

    /**
     * @deprecated 4.1.21 Use DroitService::hasDroitFor() instead
     */
    public function hasConnectorActionPermission(int $entityId, int $userId): bool
    {
        return $this->hasDroit($userId, $this->getActionPermission(self::DROIT_CONNECTEUR), $entityId);
    }

    /**
     * @deprecated 4.1.21 Use DroitService::hasDroitFor() instead
     */
    public function hasDroitUtilisateurLecture(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitFor(self::DROIT_UTILISATEUR, DroitType::LECTURE), $id_e);
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

    public static function getDroitFor(string $droit_id, DroitType $droit_type): string
    {
        return \sprintf('%s:%s', $droit_id, $droit_type->value);
    }

    /**
     * @throws NotFoundException
     */
    public function hasDroitFor($id_u, int $id_e, string $droit_id, DroitType $droit_type): bool
    {
        //DroitType::ACTION feature flag, delete in 5.0
        if ($droit_type === DroitType::ACTION && !$this->connectorActionPermission) {
            $droit_type = DroitType::EDITION;
        }

        $droit = self::getDroitFor($droit_id, $droit_type);

        if ($id_e !== EntiteSQL::ID_E_ENTITE_RACINE && !$this->entiteSQL->getInfo($id_e)) {
            throw new NotFoundException("L'entité $id_e n'existe pas");
        }

        if ($id_u === 0) {
            return true;
        }

        if (!$this->utilisateurSQL->getInfo($id_u)) {
            return false;
        }

        if ($this->isRestrictedDroit($droit)) {
            return false;
        }

        return $this->roleUtilisateur->hasDroit($id_u, $droit, $id_e);
    }
}
