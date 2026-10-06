<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Exception;
use Pastell\Service\TokenGenerator;
use UtilisateurSQL;
use JournalEventType;
use Pastell\Service\Journal\JournalEntryService;

final class PasswordResetService
{
    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly JournalEntryService $journalEntryService,
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * @throws Exception
     */
    public function changePassword(int $id_u, string $password): void
    {
        $info = $this->utilisateurSQL->getInfo($id_u);
        $this->utilisateurSQL->setPassword($id_u, $password);
        $this->utilisateurSQL->reinitPassword($id_u, $this->tokenGenerator->generate());
        $this->journalEntryService->add(
            JournalEventType::USER_MODIFICATION,
            $info['id_e'],
            JournalEntryService::NO_ID_D,
            'mot de passe modifié',
            "{$info['login']} ({$info['id_u']}) a modifié son mot de passe",
        );
    }

    /**
     * @throws Exception
     */
    public function generateResetToken(int $id_u): string
    {
        $info = $this->utilisateurSQL->getInfo($id_u);
        $token = $this->tokenGenerator->generate();
        $this->utilisateurSQL->reinitPassword($id_u, $token);
        $this->journalEntryService->addActionAutomatique(
            JournalEventType::USER_MODIFICATION,
            $info['id_e'],
            JournalEntryService::NO_ID_D,
            'mot de passe modifié',
            "Procédure initiée pour {$info['email']}",
        );
        return $token;
    }
}
