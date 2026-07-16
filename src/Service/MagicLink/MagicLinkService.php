<?php

declare(strict_types=1);

namespace Pastell\Service\MagicLink;

use ConfigurationSQL;
use ConflictException;
use Date;
use EntiteSQL;
use Exception;
use Journal;
use MagicLinkSQL;
use Pastell\Mailer\Mailer;
use Pastell\Service\TokenGenerator;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UtilisateurDeletionService;
use Pastell\Utilities\Identifier\UuidGenerator;
use Random\RandomException;
use RoleUtilisateur;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use UnrecoverableException;

final class MagicLinkService
{
    public const int MAX_DURATION_IN_HOURS = 24;

    public const int HISTORY_RETENTION_IN_MONTHS = 1;

    public const int MAX_CODE_ATTEMPTS = 3;

    private const int CODE_LENGTH = 6;

    private const string TEMP_USER_ROLE = 'admin';

    public function __construct(
        private readonly MagicLinkSQL $magicLink,
        private readonly TokenGenerator $tokenGenerator,
        private readonly UuidGenerator $uuidGenerator,
        private readonly Journal $journal,
        private readonly UserCreationService $userCreationService,
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly UtilisateurDeletionService $utilisateurDeletionService,
        private readonly Mailer $mailer,
        private readonly ConfigurationSQL $configurationSQL,
        private readonly string $site_base,
        private readonly string $plateforme_mail,
    ) {
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     * @throws Exception
     */
    public function create(
        string $motif,
        int $durationInHours,
        int $createdBy,
        string $nom,
        string $prenom,
        string $email,
    ): void {
        if ($durationInHours < 1 || $durationInHours > self::MAX_DURATION_IN_HOURS) {
            throw new UnrecoverableException(
                \sprintf(
                    'La durée de vie d\'un accès temporaire doit être comprise entre 1 et %d heures',
                    self::MAX_DURATION_IN_HOURS
                )
            );
        }

        $magicLinkId = $this->uuidGenerator->generate();
        $id_u = $this->createTemporaryUser($magicLinkId, $email);

        $token = $this->tokenGenerator->generate();
        $code = $this->generateCode();
        $expiresAt = date(Date::DATE_ISO, strtotime("+$durationInHours hours"));

        $this->magicLink->create(
            $magicLinkId,
            $id_u,
            $token,
            $code,
            $motif,
            $createdBy,
            $expiresAt,
            $nom,
            $prenom,
            $email,
        );

        try {
            $this->sendMagicLinkEmail($email, $prenom, $nom, $motif, $expiresAt, $token);
        } catch (TransportExceptionInterface $e) {
            $this->magicLink->delete($magicLinkId);
            $this->utilisateurDeletionService->delete($id_u);
            throw $e;
        }

        $this->journal->add(
            Journal::CONNEXION,
            0,
            0,
            'magic-link',
            "Génération de l'accès temporaire #$magicLinkId (motif : $motif, expiration : $expiresAt)"
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     */
    public function resend(string $magicLinkId): void
    {
        $link = $this->getActiveLink($magicLinkId);
        if ($link === null) {
            throw new UnrecoverableException("Cet accès temporaire n'existe pas ou n'est plus actif.");
        }

        $token = $this->tokenGenerator->generate();
        $this->magicLink->updateToken($magicLinkId, $token);

        $this->sendMagicLinkEmail(
            (string)$link['titulaire_email'],
            (string)$link['titulaire_prenom'],
            (string)$link['titulaire_nom'],
            (string)$link['motif'],
            (string)$link['expires_at'],
            $token,
        );

        $this->journal->add(
            Journal::CONNEXION,
            0,
            0,
            'magic-link',
            "Renvoi de l'accès temporaire #$magicLinkId"
        );
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendMagicLinkEmail(
        string $email,
        string $prenom,
        string $nom,
        string $motif,
        string $expiresAt,
        string $token,
    ): void {
        $link = \sprintf('%s/Connexion/magicLink?token=%s', rtrim($this->site_base, '/'), $token);

        $templatedEmail = new TemplatedEmail()
            ->from(new Address($this->plateforme_mail, $this->configurationSQL->getLibellePlateformeMail()))
            ->to($email)
            ->subject('[Pastell] Accès temporaire')
            ->htmlTemplate('magic-link.html.twig')
            ->context([
                'link' => $link,
                'prenom' => $prenom,
                'nom' => $nom,
                'motif' => $motif,
                'expires_at' => $expiresAt,
            ]);
        $this->mailer->send($templatedEmail);
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     * @throws Exception
     */
    private function createTemporaryUser(string $magicLinkId, string $email): int
    {
        $id_u = $this->userCreationService->create(
            $magicLinkId,
            $email,
            $magicLinkId,
            $magicLinkId,
            EntiteSQL::ID_E_ENTITE_RACINE,
            $this->tokenGenerator->generate(),
        );

        $this->roleUtilisateur->addRole($id_u, self::TEMP_USER_ROLE, 0);

        return $id_u;
    }

    public function getActiveLinks(): array
    {
        return $this->magicLink->getActive();
    }

    public function getHistory(string $search = ''): array
    {
        return $this->magicLink->getHistory($search);
    }

    public function getActiveLink(string $magicLinkId): ?array
    {
        return array_find($this->magicLink->getActive(), fn($link) => (string)$link['id'] === $magicLinkId);
    }

    public function isActive(string $magicLinkId): bool
    {
        return $this->getActiveLink($magicLinkId) !== null;
    }

    public function getValidLinkFromToken(string $token): ?array
    {
        $link = $this->magicLink->getFromToken($token);
        if (
            $link === null
            || $link['revoked_at'] !== null
            || strtotime($link['expires_at']) < time()
        ) {
            return null;
        }
        return $link;
    }

    public function checkCode(string $token, string $code): MagicLinkCodeCheck
    {
        $link = $this->getValidLinkFromToken($token);
        if ($link === null) {
            return new MagicLinkCodeCheck(MagicLinkCodeStatus::InvalidLink);
        }

        if (hash_equals((string)$link['code'], $code)) {
            return new MagicLinkCodeCheck(MagicLinkCodeStatus::Valid, $link);
        }

        $this->magicLink->incrementCodeAttempts((string)$link['id']);
        $attempts = (int)$link['code_attempts'] + 1;

        if ($attempts >= self::MAX_CODE_ATTEMPTS) {
            $this->revoke((string)$link['id']);
            return new MagicLinkCodeCheck(MagicLinkCodeStatus::Revoked);
        }

        return new MagicLinkCodeCheck(
            MagicLinkCodeStatus::WrongCode,
            remainingAttempts: self::MAX_CODE_ATTEMPTS - $attempts,
        );
    }

    public function revoke(string $magicLinkId): void
    {
        $link = $this->getActiveLink($magicLinkId);

        $this->magicLink->revoke($magicLinkId);

        if ($link !== null) {
            $this->deleteTemporaryUser($link);
        }

        $this->journal->add(
            Journal::CONNEXION,
            EntiteSQL::ID_E_ENTITE_RACINE,
            0,
            'magic-link',
            "Révocation de l'accès #$magicLinkId"
        );
    }

    public function pruneExpired(): int
    {
        $count = 0;
        foreach ($this->magicLink->getToCleanUp() as $link) {
            $this->deleteTemporaryUser($link);
            $count++;
        }
        return $count;
    }

    public function pruneHistory(): int
    {
        $expirationDate = date(
            Date::DATE_ISO,
            strtotime('-' . self::HISTORY_RETENTION_IN_MONTHS . ' month'),
        );
        return $this->magicLink->deleteClosedBefore($expirationDate);
    }

    /**
     * @throws RandomException
     */
    private function generateCode(): string
    {
        return str_pad((string)random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function deleteTemporaryUser(array $link): void
    {
        $magicLinkId = (string)$link['id'];
        $this->utilisateurDeletionService->delete((int)$link['id_u']);
        $this->magicLink->markUserDeleted($magicLinkId);
        $this->magicLink->anonymiseTitulaire(
            $magicLinkId,
            $this->pseudonymise((string)$link['titulaire_nom']),
            $this->pseudonymise((string)$link['titulaire_prenom']),
            $this->pseudonymiseEmail((string)$link['titulaire_email']),
        );
    }

    private function pseudonymise(string $value): string
    {
        $maskedLength = max(0, mb_strlen($value) - 2);
        return mb_substr($value, 0, 2) . str_repeat('*', $maskedLength);
    }

    private function pseudonymiseEmail(string $email): string
    {
        $atPosition = mb_strpos($email, '@');
        if ($atPosition === false) {
            return $this->pseudonymise($email);
        }
        $localPart = mb_substr($email, 0, $atPosition);
        $domain = mb_substr($email, $atPosition);
        return $this->pseudonymise($localPart) . $domain;
    }
}
