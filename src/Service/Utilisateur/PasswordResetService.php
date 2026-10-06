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
        private readonly JournalEntryService $journal,
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
        $this->journal->add(
            JournalEventType::MODIFICATION_UTILISATEUR,
            $info['id_e'],
            0,
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
        $this->journal->addActionAutomatique(
            JournalEventType::MODIFICATION_UTILISATEUR,
            $info['id_e'],
            0,
            'mot de passe modifié',
            "Procédure initiée pour {$info['email']}",
        );
        return $token;
    }
}
