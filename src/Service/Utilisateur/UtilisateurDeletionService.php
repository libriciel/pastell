<?php

namespace Pastell\Service\Utilisateur;

use EntiteSQL;
use Notification;
use RoleUtilisateur;
use UtilisateurNewEmailSQL;
use UtilisateurSQL;
use Journal;
use UsersToken;

class UtilisateurDeletionService
{
    /**
     * @var UtilisateurSQL
     */
    private $utilisateurSQL;

    /**
     * @var Journal
     */
    private $journal;

    /**
     * @var RoleUtilisateur
     */
    private $roleUtilisateur;

    public function __construct(
        UtilisateurSQL $utilisateurSQL,
        RoleUtilisateur $roleUtilisateur,
        Journal $journal,
        private readonly Notification $notification,
        private readonly UsersToken $usersToken,
        private readonly UtilisateurNewEmailSQL $utilisateurNewEmailSQL,
    ) {
        $this->utilisateurSQL = $utilisateurSQL;
        $this->journal = $journal;
        $this->roleUtilisateur = $roleUtilisateur;
    }

    /**
     * Suppression de l'utilisateur
     * Attention, on enregistre pas les données nominatives dans le journal.
     * @param int $id_u
     */
    public function delete(int $id_u): void
    {
        $this->roleUtilisateur->removeAllRole($id_u);
        $this->notification->removeAllForUser($id_u);
        $this->usersToken->deleteAllForUser($id_u);
        $this->utilisateurNewEmailSQL->delete($id_u);
        $this->utilisateurSQL->desinscription($id_u);
        $this->journal->add(
            Journal::MODIFICATION_UTILISATEUR,
            EntiteSQL::ID_E_ENTITE_RACINE,
            Journal::NO_ID_D,
            Journal::ACTION_SUPPRIME,
            "Suppression de l'utilisateur id_u=$id_u"
        );
    }
}
