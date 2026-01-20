<?php

namespace Pastell\Service\Connecteur;

use ConnecteurEntiteSQL;
use FluxDefinitionFiles;
use FluxEntiteSQL;
use Exception;
use Pastell\Service\Droit\DroitService;
use PHPUnit\Framework\Constraint\Count;
use UnrecoverableException;

class ConnecteurAssociationService
{
    public function __construct(
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly DroitService $droitService,
        private readonly FluxDefinitionFiles $fluxDefinitionFiles,
        private readonly ConnecteurActionService $connecteurActionService
    ) {
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function addConnecteurAssociation(
        int $id_e,
        int $id_ce,
        ?string $type_connecteur = null,
        int $id_u = 0,
        string $type_dossier = '',
        int $num_same_type = 0
    ): int {

        $info = $this->connecteurEntiteSQL->getInfo($id_ce);
        if ($type_connecteur === null) {
            $type_connecteur = $info['type'];
        }
        if ($info['type'] != $type_connecteur) {
            throw new UnrecoverableException(
                "Le connecteur n'est pas du bon type :  {$info['type']} présenté, $type_connecteur requis"
            );
        }
        if (! $this->droitService->hasDroitConnecteurEdition($id_e, $id_u)) {
            throw new UnrecoverableException("Vous n'avez pas le droit d'édition pour les connecteurs");
        }
        if ($type_dossier !== '') {
            $info = $this->fluxDefinitionFiles->getInfo($type_dossier);
            if (!$info) {
                throw new UnrecoverableException("Le type de dossier « $type_dossier » n'existe pas.");
            }
        }

        $this->deleteConnecteurAssociation($id_e, $type_connecteur, $id_u, $type_dossier, $num_same_type);
        $id_fe = $this->fluxEntiteSQL->addConnecteur($id_e, $type_dossier, $type_connecteur, $id_ce, $num_same_type);

        $message =  ($type_dossier !== '') ?
            "Association au type de dossier $type_dossier en position " . ++$num_same_type .
            " du type de connecteur $type_connecteur pour l'entité id_e = $id_e"
            : "Association au type de connecteur $type_connecteur";

        $this->connecteurActionService->add(
            $id_e,
            $id_u,
            $id_ce,
            $type_dossier,
            ConnecteurActionService::ACTION_ASSOCIE,
            $message
        );

        return $id_fe;
    }

    /**
     * @throws Exception
     */
    public function deleteConnecteurAssociation(
        int $id_e,
        string $type_connecteur,
        int $id_u = 0,
        string $type_dossier = '',
        int $num_same_type = 0
    ): void {

        $id_ce = (int)$this->fluxEntiteSQL->getConnecteurId($id_e, $type_dossier, $type_connecteur, $num_same_type);
        $this->fluxEntiteSQL->deleteConnecteur($id_e, $type_dossier, $type_connecteur, $num_same_type);

        if ($id_ce) {
            $message =  ($type_dossier !== '') ?
                "Dissociation du type de dossier $type_dossier en position " . ++$num_same_type .
                " du type de connecteur $type_connecteur pour l'entité id_e = $id_e"
                : "Dissociation du type de connecteur $type_connecteur";

            $this->connecteurActionService->add(
                $id_e,
                $id_u,
                $id_ce,
                $type_dossier,
                ConnecteurActionService::ACTION_DISSOCIE,
                $message
            );
        }
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function migrateConnecteurAssociation(
        int $id_ce_source,
        int $id_ce_target,
        int $id_u = 0
    ): int {
        $sourceInfo = $this->connecteurEntiteSQL->getInfo($id_ce_source);
        $targetInfo = $this->connecteurEntiteSQL->getInfo($id_ce_target);

        if (!$sourceInfo) {
            throw new UnrecoverableException("Le connecteur source n'existe pas : id_ce=$id_ce_source");
        }
        if (!$targetInfo) {
            throw new UnrecoverableException("Le connecteur cible n'existe pas : id_ce=$id_ce_target");
        }
        if ($sourceInfo['type'] !== $targetInfo['type']) {
            throw new UnrecoverableException(
                "Les connecteurs ne sont pas du même type : {$sourceInfo['type']} (source) vs {$targetInfo['type']} (cible)"
            );
        }

        $associations = $this->fluxEntiteSQL->getUsedByConnecteur($id_ce_source);

        foreach ($associations as $association) {
            $this->addConnecteurAssociation(
                $association['id_e'],
                $id_ce_target,
                $association['type'],
                $id_u,
                $association['flux'],
                $association['num_same_type']
            );
        }

        return count($associations);
    }

    /**
     * @throws Exception
     */
    public function deleteConnecteurAssociationById_fe(
        int $id_fe,
        ?int $id_e = null,
        int $id_u = 0
    ): void {

        $infoAssociation = $this->fluxEntiteSQL->getConnecteurById($id_fe);
        if (!$infoAssociation) {
            throw new Exception("Le connecteur-flux n'existe pas : {id_fe=$id_fe}");
        }
        if ($id_e && ($id_e != $infoAssociation['id_e'])) {
            throw new Exception("Le connecteur-flux n'existe pas sur l'entité spécifié : {id_fe=$id_fe, id_e=$id_e}");
        }

        $this->fluxEntiteSQL->removeConnecteur($id_fe);

        $type_dossier = $infoAssociation['flux'];
        $type_connecteur = $infoAssociation['type'];
        $id_e = $infoAssociation['id_e'];
        $message =  ($infoAssociation['flux'] != $this->fluxEntiteSQL::FLUX_GLOBAL_NAME) ?
            "Dissociation du type de dossier $type_dossier en position " . ++$infoAssociation['num_same_type'] .
            " du type de connecteur $type_connecteur pour l'entité id_e = $id_e"
            : "Dissociation du type de connecteur $type_connecteur";

        $this->connecteurActionService->add(
            $id_e,
            $id_u,
            $infoAssociation['id_ce'],
            $type_dossier,
            ConnecteurActionService::ACTION_DISSOCIE,
            $message
        );
    }
}
