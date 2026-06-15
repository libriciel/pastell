<?php

declare(strict_types=1);

namespace Pastell\Service\MagicLink;

use ConfigurationSQL;
use ConflictException;
use Date;
use Exception;
use Journal;
use MagicLinkSQL;
use Pastell\Mailer\Mailer;
use Pastell\Service\TokenGenerator;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UtilisateurDeletionService;
use RoleUtilisateur;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use UnrecoverableException;
use UtilisateurSQL;

final class MagicLinkService
{
    private const string TEMP_USER_LOGIN_PREFIX = 'support-';
    private const string TEMP_USER_ROLE = 'admin';

    public function __construct(
        private readonly MagicLinkSQL $magicLink,
        private readonly TokenGenerator $tokenGenerator,
        private readonly Journal $journal,
        private readonly UserCreationService $userCreationService,
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly UtilisateurSQL $utilisateurSQL,
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
        $id_u = $this->createTemporaryUser($nom, $prenom, $email);

        $token = $this->tokenGenerator->generate();
        $expiresAt = date(Date::DATE_ISO, strtotime("+$durationInHours hours"));

        $magicLinkId = $this->magicLink->create($id_u, $token, $motif, $createdBy, $expiresAt, $nom, $prenom, $email);

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
            "Génération d'un accès temporaire pour $prenom $nom <$email> "
                . "(motif : $motif, expiration : $expiresAt)"
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
    private function createTemporaryUser(string $nom, string $prenom, string $email): int
    {
        $login = self::TEMP_USER_LOGIN_PREFIX . substr($this->tokenGenerator->generate(), 0, 12);

        $id_u = $this->userCreationService->create(
            $login,
            $email,
            $prenom,
            $nom,
            0,
            $this->tokenGenerator->generate(),
        );
        $this->roleUtilisateur->addRole($id_u, self::TEMP_USER_ROLE, 0);
        $this->utilisateurSQL->disable($id_u);

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

    public function getActiveLink(int $magicLinkId): ?array
    {
        return array_find($this->magicLink->getActive(), fn($link) => (int)$link['id'] === $magicLinkId);
    }

    public function isActive(int $magicLinkId): bool
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

    public function revoke(int $magicLinkId): void
    {
        $link = $this->getActiveLink($magicLinkId);

        $this->magicLink->revoke($magicLinkId);

        if ($link !== null) {
            $this->deleteTemporaryUser($magicLinkId, (int)$link['id_u']);
        }

        $this->journal->add(
            Journal::CONNEXION,
            0,
            0,
            'magic-link',
            "Révocation de l'accès #$magicLinkId"
        );
    }

    public function pruneExpired(): int
    {
        $count = 0;
        foreach ($this->magicLink->getToCleanUp() as $link) {
            $this->deleteTemporaryUser((int)$link['id'], (int)$link['id_u']);
            $count++;
        }
        return $count;
    }

    private function deleteTemporaryUser(int $magicLinkId, int $id_u): void
    {
        $this->utilisateurDeletionService->delete($id_u);
        $this->magicLink->markUserDeleted($magicLinkId);
    }
}
