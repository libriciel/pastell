<?php

namespace Pastell\Service\Droit;

use DocumentTypeFactory;
use EntiteSQL;
use NotFoundException;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use UtilisateurSQL;

class DroitService
{
    public const string DROIT_CONNECTEUR = 'connecteur';
    public const string DROIT_SYSTEM = 'system';
    public const string DROIT_ENTITE = 'entite';
    public const string DROIT_UTILISATEUR = 'utilisateur';
    public const string DROIT_DAEMON = 'daemon';
    public const string DROIT_JOURNAL = 'journal';
    public const string DROIT_ROLE = 'role';
    public const string DROIT_ANNUAIRE = 'annuaire';


    public const AUCUN_DROIT = 'aucun droit';

    public function __construct(
        private readonly UtilisateurRoleService $utilisateurRoleService,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly EntiteSQL $entiteSQL,
        private readonly UtilisateurSQL $utilisateurSQL,
    ) {
    }

    public function hasOneDroitFor(int $id_u, string $droit_id, DroitType $droit_type): bool
    {
        $droit = self::getDroitFor($droit_id, $droit_type);
        if ($this->isRestrictedDroit($droit)) {
            return false;
        }
        return in_array($droit, $this->utilisateurRoleService->getAllDroit($id_u), true);
    }

    public function getAllDocumentLecture(int $id_u, int $id_e): array
    {
        $liste_type = [];
        foreach ($this->utilisateurRoleService->getDroitsForEntite($id_u, $id_e) as $droit) {
            if (!preg_match('/^(.*):' . DroitType::LECTURE->value . '$/', $droit, $matches)) {
                continue;
            }
            $type = $matches[1];
            if (!$this->documentTypeFactory->isRestrictedFlux($type)) {
                $liste_type[] = $type;
            }
        }
        return $liste_type;
    }

    /**
     * @param $id_u (pas possible de typer. Authentification::getId() peut retourner false)
     * @param int $id_e
     * @return array
     */
    public function getUnrestrictedDroitsForEntite($id_u, int $id_e): array
    {
        $data = $this->utilisateurRoleService->getDroitsForEntite($id_u, $id_e);
        foreach ($data as $key => $droit) {
            if ($this->isRestrictedDroit($droit)) {
                unset($data[$key]);
            }
        }
        return array_values($data);
    }

    /**
     * @deprecated 4.1.24 Use DroitService::getUnrestrictedDroitsForEntite() instead
     */
    public function getAllDroitEntite($id_u, int $id_e): array
    {
        return $this->getUnrestrictedDroitsForEntite($id_u, $id_e);
    }

    public function getUnrestrictedDroits(int $id_u): array
    {
        $data = $this->utilisateurRoleService->getAllDroit($id_u);
        foreach ($data as $key => $droit) {
            if ($this->isRestrictedDroit($droit)) {
                unset($data[$key]);
            }
        }
        return array_values($data);
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

        return in_array($droit, $this->utilisateurRoleService->getDroitsForEntite($id_u, $id_e), true);
    }
}
