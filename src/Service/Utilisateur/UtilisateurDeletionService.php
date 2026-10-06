<?php

namespace Pastell\Service\Utilisateur;

use EntiteSQL;
use JournalAction;
use JournalEventType;
use Notification;
use NotificationDigestSQL;
use Pastell\Service\Journal\JournalEntryService;
use RoleUtilisateur;
use UtilisateurNewEmailSQL;
use UtilisateurSQL;
use UsersToken;

class UtilisateurDeletionService
{
    /**
     * @var UtilisateurSQL
     */
    private $utilisateurSQL;

    /**
     * @var JournalEntryService
     */
    private $journal;

    /**
     * @var RoleUtilisateur
     */
    private $roleUtilisateur;

    public function __construct(
        UtilisateurSQL $utilisateurSQL,
        RoleUtilisateur $roleUtilisateur,
        JournalEntryService $journal,
        private readonly Notification $notification,
        private readonly UsersToken $usersToken,
        private readonly UtilisateurNewEmailSQL $utilisateurNewEmailSQL,
        private readonly NotificationDigestSQL $notificationDigestSQL,
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
        $userInfo = $this->utilisateurSQL->getInfo($id_u);
        $this->roleUtilisateur->removeAllRole($id_u);
        $this->notification->removeAllForUser($id_u);
        $this->notificationDigestSQL->deleteByEmail($userInfo['email']);
        $this->usersToken->deleteAllForUser($id_u);
        $this->utilisateurNewEmailSQL->delete($id_u);
        $this->utilisateurSQL->desinscription($id_u);
        $this->journal->add(
            JournalEventType::MODIFICATION_UTILISATEUR,
            EntiteSQL::ID_E_ENTITE_RACINE,
            JournalEntryService::NO_ID_D,
            JournalAction::SUPPRIME->value,
            "Suppression de l'utilisateur id_u=$id_u"
        );
    }
}
