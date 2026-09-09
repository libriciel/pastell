<?php

declare(strict_types=1);

namespace Pastell\Service\MagicLink;

use EntiteSQL;
use Journal;
use MagicLinkSQL;

final class MagicLinkRevokeService
{
    public function __construct(
        private readonly MagicLinkSQL $magicLink,
        private readonly Journal $journal,
    ) {
    }

    public function maskTitulaire(array $link): void
    {
        $magicLinkId = (string)$link['id'];
        $this->magicLink->markUserDeleted($magicLinkId);
        $this->magicLink->updateTitulaire(
            $magicLinkId,
            $this->mask((string)$link['titulaire_nom']),
            $this->mask((string)$link['titulaire_prenom']),
            $this->maskEmail((string)$link['titulaire_email']),
        );
    }

    public function closeForDeletedUser(int $id_u): void
    {
        $link = $this->magicLink->getActiveByUserId($id_u);
        if ($link === null) {
            return;
        }

        $magicLinkId = (string)$link['id'];
        $this->magicLink->revoke($magicLinkId);
        $this->maskTitulaire($link);

        $this->journal->add(
            Journal::CONNEXION,
            EntiteSQL::ID_E_ENTITE_RACINE,
            Journal::NO_ID_D,
            'magic-link',
            "Révocation de l'accès temporaire #$magicLinkId (motif : utilisateur supprimé)"
        );
    }

    private function mask(string $value): string
    {
        $maskedLength = max(0, mb_strlen($value) - 2);
        return mb_substr($value, 0, 2) . str_repeat('*', $maskedLength);
    }

    private function maskEmail(string $email): string
    {
        $atPosition = mb_strpos($email, '@');
        if ($atPosition === false) {
            return $this->mask($email);
        }
        $localPart = mb_substr($email, 0, $atPosition);
        $domain = mb_substr($email, $atPosition);
        return $this->mask($localPart) . $domain;
    }
}
