<?php

namespace Pastell\Service\Utilisateur;

use EntiteSQL;
use JournalAction;
use JournalEventType;
use NotificationDigestSQL;
use Pastell\Service\Notification\NotificationService;
use Pastell\Service\Journal\JournalEntryService;
use UtilisateurNewEmailSQL;
use UtilisateurSQL;
use UsersToken;

class UtilisateurDeletionService
{
    /**
     * @var UtilisateurSQL
     */
    private $utilisateurSQL;

    private JournalEntryService $journalEntryService;

    private UtilisateurRoleService $utilisateurRoleService;

    public function __construct(
        UtilisateurSQL $utilisateurSQL,
        UtilisateurRoleService $utilisateurRoleService,
        JournalEntryService $journalEntryService,
        private readonly NotificationService $notificationService,
        private readonly UsersToken $usersToken,
        private readonly UtilisateurNewEmailSQL $utilisateurNewEmailSQL,
        private readonly NotificationDigestSQL $notificationDigestSQL,
    ) {
        $this->utilisateurSQL = $utilisateurSQL;
        $this->journalEntryService = $journalEntryService;
        $this->utilisateurRoleService = $utilisateurRoleService;
    }

    /**
     * Suppression de l'utilisateur
     * Attention, on enregistre pas les données nominatives dans le journal.
     * @param int $id_u
     */
    public function delete(int $id_u): void
    {
        $userInfo = $this->utilisateurSQL->getInfo($id_u);
        $this->utilisateurRoleService->removeAllRoles($id_u);
        $this->notificationService->removeAllForUser($id_u);
        $this->notificationDigestSQL->deleteByEmail($userInfo['email']);
        $this->usersToken->deleteAllForUser($id_u);
        $this->utilisateurNewEmailSQL->delete($id_u);
        $this->utilisateurSQL->desinscription($id_u);
        $this->journalEntryService->add(
            JournalEventType::USER_MODIFICATION,
            EntiteSQL::ID_E_ENTITE_RACINE,
            JournalEntryService::NO_ID_D,
            JournalAction::DELETED->value,
            "Suppression de l'utilisateur id_u=$id_u"
        );
    }
}
