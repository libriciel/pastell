<?php

namespace Pastell\Service\Droit;

use DocumentTypeFactory;
use RoleUtilisateur;

class DroitService
{
    public const DROIT_LECTURE = 'lecture';
    public const DROIT_ECRITURE = 'edition';

    public const DROIT_CONNECTEUR = 'connecteur';
    public const DROIT_SYSTEM = 'system';
    public const DROIT_ENTITE = 'entite';
    public const DROIT_UTILISATEUR = 'utilisateur';
    public const DROIT_DAEMON = 'daemon';

    public static function getDroitLecture(string $part): string
    {
        return sprintf("%s:%s", $part, self::DROIT_LECTURE);
    }

    public static function getDroitEdition(string $part): string
    {
        return sprintf("%s:%s", $part, self::DROIT_ECRITURE);
    }

    private $roleUtilisateur;
    private $documentTypeFactory;

    public function __construct(RoleUtilisateur $roleUtilisateur, DocumentTypeFactory $documentTypeFactory)
    {
        $this->roleUtilisateur = $roleUtilisateur;
        $this->documentTypeFactory = $documentTypeFactory;
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

    /**
     * @param int $id_u
     * @param string $droit
     * @return bool
     */
    public function hasOneDroit(int $id_u, string $droit): bool
    {
        if ($this->isRestrictedDroit($droit)) {
            return false;
        }
        return $this->roleUtilisateur->hasOneDroit($id_u, $droit);
    }

    /**
     * @param int $id_u
     * @param int $id_e
     * @return array
     */
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

    /**
     * @param int $id_u
     * @return array
     */
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
     * @param int $id_e
     * @param int $id_u
     * @return bool
     */
    public function hasDroitConnecteurLecture(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitLecture(self::DROIT_CONNECTEUR), $id_e);
    }

    /**
     * @param int $id_e
     * @param int $id_u
     * @return bool
     */
    public function hasDroitConnecteurEdition(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitEdition(self::DROIT_CONNECTEUR), $id_e);
    }

    /**
     * @param int $id_e
     * @param int $id_u
     * @return bool
     */
    public function hasDroitUtilisateurLecture(int $id_e, int $id_u): bool
    {
        return $this->hasDroit($id_u, self::getDroitLecture(self::DROIT_UTILISATEUR), $id_e);
    }

    /**
     * @param array $all_droit
     * @return array
     */
    public function clearRestrictedDroit(array $all_droit): array
    {
        foreach ($all_droit as $sql_droit => $checked) {
            if ($this->isRestrictedDroit($sql_droit)) {
                unset($all_droit[$sql_droit]);
            }
        }
        return $all_droit;
    }

    /**
     * @param string $droit
     * @return bool
     */
    public function isRestrictedDroit(string $droit): bool
    {
        list($part) = explode(":", $droit);
        return $this->documentTypeFactory->isRestrictedFlux($part);
    }

    /**
     * @param array $list_connecteur
     * @param bool $global
     * @return array
     */
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

    /**
     * @param string $id_connecteur
     * @param bool $global
     * @return bool
     */
    public function isRestrictedConnecteur(string $id_connecteur, bool $global = false): bool
    {
        return $this->documentTypeFactory->isRestrictedConnecteur($id_connecteur, $global);
    }
}
