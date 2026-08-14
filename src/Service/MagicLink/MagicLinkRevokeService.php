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

    public function anonymise(array $link): void
    {
        $magicLinkId = (string)$link['id'];
        $this->magicLink->markUserDeleted($magicLinkId);
        $this->magicLink->anonymiseTitulaire(
            $magicLinkId,
            self::pseudonymise((string)$link['titulaire_nom']),
            self::pseudonymise((string)$link['titulaire_prenom']),
            self::pseudonymiseEmail((string)$link['titulaire_email']),
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
        $this->anonymise($link);

        $this->journal->add(
            Journal::CONNEXION,
            EntiteSQL::ID_E_ENTITE_RACINE,
            Journal::NO_ID_D,
            'magic-link',
            "Révocation de l'accès temporaire #$magicLinkId (motif : utilisateur supprimé)"
        );
    }

    private static function pseudonymise(string $value): string
    {
        $maskedLength = max(0, mb_strlen($value) - 2);
        return mb_substr($value, 0, 2) . str_repeat('*', $maskedLength);
    }

    private static function pseudonymiseEmail(string $email): string
    {
        $atPosition = mb_strpos($email, '@');
        if ($atPosition === false) {
            return self::pseudonymise($email);
        }
        $localPart = mb_substr($email, 0, $atPosition);
        $domain = mb_substr($email, $atPosition);
        return self::pseudonymise($localPart) . $domain;
    }
}
