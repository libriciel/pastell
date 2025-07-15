<?php

namespace Pastell\Service\Entite;

use ConnecteurEntiteSQL;
use DocumentEntite;
use EntiteSQL;
use FluxEntiteHeritageSQL;
use FluxEntiteSQL;
use Journal;
use UnrecoverableException;
use UtilisateurListe;

class EntiteDeletionService
{
    /**
     * @var EntiteSQL
     */
    private $entiteSQL;

    /**
     * @var Journal
     */
    private $journal;

    public function __construct(
        EntiteSQL $entiteSQL,
        Journal $journal,
        private readonly DocumentEntite $documentEntite,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly FluxEntiteHeritageSQL $fluxEntiteHeritageSQL,
        private readonly UtilisateurListe $utilisateurListe,
    ) {
        $this->entiteSQL = $entiteSQL;
        $this->journal = $journal;
    }

    /**
     * @param int $id_e
     * @throws UnrecoverableException
     */
    public function delete(int $id_e): void
    {
        $this->canDeleteOrThrow($id_e);
        $info = $this->entiteSQL->getInfo($id_e);
        $this->entiteSQL->removeEntite($id_e);
        $this->journal->add(
            Journal::MODIFICATION_ENTITE,
            $id_e,
            Journal::NO_ID_D,
            Journal::ACTION_SUPPRIME,
            "Suppression de l'entité id_e=$id_e\nInformation : " . json_encode($info)
        );
    }

    public function canDelete(int $id_e): bool
    {
        try {
            $this->canDeleteOrThrow($id_e);
        } catch (UnrecoverableException $e) {
            return false;
        }

        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    private function canDeleteOrThrow(int $id_e): void
    {
        if ($this->documentEntite->getNbAll($id_e)) {
            throw new UnrecoverableException("Suppression impossible : des documents sont définis sur l'entité {id_e=$id_e}");
        }
        if (count($this->entiteSQL->getFille($id_e))) {
            throw new UnrecoverableException("Suppression impossible : l'entité {id_e=$id_e} possède des entités filles");
        }
        if ($this->utilisateurListe->getNbUtilisateurWithEntiteDeBase($id_e)) {
            throw new UnrecoverableException("Suppression impossible : des utilisateurs sont définis sur l'entité {id_e=$id_e}");
        }
        if ($this->utilisateurListe->getNbUtilisateur($id_e)) {
            throw new UnrecoverableException("Suppression impossible : des utilisateurs sont définis sur l'entité {id_e=$id_e}");
        }
        if ($this->connecteurEntiteSQL->getAll($id_e)) {
            throw new UnrecoverableException("Suppression impossible : des connecteurs sont définis sur l'entité {id_e=$id_e}");
        }
        if (count($this->fluxEntiteSQL->getAllFluxEntite($id_e)) > 0) {
            throw new UnrecoverableException("Suppression impossible : des flux sont définis sur l'entité {id_e=$id_e}");
        }
        if (count($this->fluxEntiteHeritageSQL->getInheritance($id_e)) > 0) {
            throw new UnrecoverableException("Suppression impossible : des flux herités sont définis sur l'entité {id_e=$id_e}");
        }
    }
}
