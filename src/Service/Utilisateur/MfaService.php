<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use JournalEventType;
use NotFoundException;
use OTPHP\TOTP;
use Pastell\Service\Journal\JournalEntryService;
use Psr\Clock\ClockInterface;
use Random\RandomException;
use UnrecoverableException;
use UtilisateurMfaRecoveryCodeSQL;
use UtilisateurMfaSQL;
use UtilisateurSQL;

final class MfaService
{
    private const string ISSUER = 'Pastell';
    private const int RECOVERY_CODE_COUNT = 10;
    private const int RECOVERY_CODE_WARNING_THRESHOLD = 3;

    public function __construct(
        private readonly UtilisateurMfaSQL $utilisateurMfaSQL,
        private readonly UtilisateurMfaRecoveryCodeSQL $recoveryCodeSQL,
        private readonly ClockInterface $clock,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function isEnabled(int $id_u): bool
    {
        return $this->utilisateurMfaSQL->isEnabled($id_u);
    }

    public function isPending(int $id_u): bool
    {
        return $this->getPendingSecret($id_u) !== null;
    }

    public function canEnrol(int $id_u): bool
    {
        return !$this->isEnabled($id_u) || $this->isPending($id_u);
    }

    public function getEnrolment(int $id_u, string $label): ?MfaEnrolment
    {
        $secret = $this->getPendingSecret($id_u);
        if ($secret === null) {
            return null;
        }
        return new MfaEnrolment($secret, $this->getQrCodeSvg($this->getProvisioningUri($secret, $label)));
    }

    private function getPendingSecret(int $id_u): ?string
    {
        return $this->utilisateurMfaSQL->getInfo($id_u)['pending_secret'] ?? null;
    }

    public function getActivatedAt(int $id_u): ?string
    {
        return $this->utilisateurMfaSQL->getInfo($id_u)['activated_at'] ?? null;
    }

    private function getProvisioningUri(string $secret, string $label): string
    {
        return TOTP::createFromSecret($secret, $this->clock)
            ->withLabel($label)
            ->withIssuer(self::ISSUER)
            ->getProvisioningUri();
    }

    private function getQrCodeSvg(string $uri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
        return $writer->writeString($uri);
    }

    private function verifyForUser(int $id_u, ?string $secret, string $code): bool
    {
        if (empty($secret)) {
            return false;
        }
        $counter = $this->getMatchingCounter($secret, $code);
        if ($counter === null) {
            return false;
        }
        return $this->utilisateurMfaSQL->updateLastUsedCounter($id_u, $counter);
    }

    private function getMatchingCounter(string $secret, string $code): ?int
    {
        $totp = TOTP::createFromSecret($secret, $this->clock);
        $now = $this->clock->now()->getTimestamp();
        $period = $totp->getPeriod();
        foreach ([-1, 0, 1] as $step) {
            $timestamp = $now + $step * $period;
            if ($totp->verify($code, $timestamp)) {
                return intdiv($timestamp, $period);
            }
        }
        return null;
    }

    public function enroll(int $id_u): void
    {
        $this->utilisateurMfaSQL->enroll($id_u, TOTP::generate($this->clock)->getSecret());
    }

    /**
     * @return string[]
     * @throws UnrecoverableException
     * @throws RandomException
     */
    public function activate(int $id_u, string $code): array
    {
        $was_enabled = $this->isEnabled($id_u);
        $pending_secret = $this->getPendingSecret($id_u);
        if ($pending_secret === null && $was_enabled) {
            throw new UnrecoverableException('La double authentification est déjà activée.');
        }
        $counter = $pending_secret === null ? null : $this->getMatchingCounter($pending_secret, $code);
        if ($counter === null) {
            throw new UnrecoverableException('Le code saisi est invalide.');
        }
        $this->utilisateurMfaSQL->confirm($id_u, $counter);
        $user_info = $this->utilisateurSQL->getInfo($id_u);
        if ($was_enabled) {
            $this->addJournalEntry(
                $user_info,
                $id_u,
                'double authentification réinitialisée',
                "{$user_info['login']} ($id_u) a réinitialisé sa double authentification"
            );
        } else {
            $this->addJournalEntry(
                $user_info,
                $id_u,
                'double authentification activée',
                "{$user_info['login']} ($id_u) a activé la double authentification"
            );
        }
        return $this->generateRecoveryCodes($id_u);
    }

    /**
     * @return string[]
     * @throws RandomException
     * @throws UnrecoverableException
     */
    public function regenerateRecoveryCodes(int $id_u): array
    {
        $this->assertEnabled($id_u);
        $recovery_codes = $this->generateRecoveryCodes($id_u);
        $user_info = $this->utilisateurSQL->getInfo($id_u);
        $this->addJournalEntry(
            $user_info,
            $id_u,
            'codes de récupération régénérés',
            "{$user_info['login']} ($id_u) a régénéré ses codes de récupération"
        );
        return $recovery_codes;
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function disable(int $id_u, ?int $admin_id_u = null): void
    {
        $user_info = $this->utilisateurSQL->getInfo($id_u);
        if (!$user_info) {
            throw new NotFoundException("Utilisateur $id_u inconnu");
        }
        $this->assertEnabled($id_u);
        $this->delete($id_u);
        $this->addJournalEntry(
            $user_info,
            $admin_id_u ?? $id_u,
            'double authentification désactivée',
            $admin_id_u === null
                ? "{$user_info['login']} ($id_u) a désactivé la double authentification"
                : "{$user_info['login']} ($id_u) : double authentification désactivée par un administrateur"
        );
    }

    public function verifyLoginCode(int $id_u, string $code, string $remote_address): MfaLoginResult
    {
        $user_info = $this->utilisateurSQL->getInfo($id_u);
        if (!$user_info || !$this->isEnabled($id_u)) {
            return MfaLoginResult::INVALID;
        }
        $secret = $this->utilisateurMfaSQL->getInfo($id_u)['secret'] ?? null;
        if ($this->verifyForUser($id_u, $secret, $code)) {
            return MfaLoginResult::TOTP;
        }
        if ($this->verifyRecoveryCode($id_u, $code)) {
            $this->addJournalEntry(
                $user_info,
                $id_u,
                'Connexion par code de récupération',
                "{$user_info['login']} s'est connecté avec un code de récupération depuis l'adresse $remote_address",
                JournalEventType::LOGIN
            );
            return MfaLoginResult::RECOVERY_CODE;
        }
        $this->addJournalEntry(
            $user_info,
            $id_u,
            'Échec double authentification',
            "{$user_info['login']} a saisi un code de double authentification invalide depuis l'adresse $remote_address",
            JournalEventType::LOGIN
        );
        return MfaLoginResult::INVALID;
    }

    /**
     * @throws UnrecoverableException
     */
    private function assertEnabled(int $id_u): void
    {
        if (!$this->isEnabled($id_u)) {
            throw new UnrecoverableException("La double authentification n'est pas activée.");
        }
    }

    public function isRecoveryCodeCountLow(int $remaining_count): bool
    {
        return $remaining_count <= self::RECOVERY_CODE_WARNING_THRESHOLD;
    }

    private function addJournalEntry(
        array $user_info,
        int $author_id_u,
        string $action,
        string $message,
        JournalEventType $journalEventType = JournalEventType::USER_MODIFICATION,
    ): void {
        $this->journalEntryService->addSQL(
            $journalEventType,
            (int)$user_info['id_e'],
            $author_id_u,
            JournalEntryService::NO_ID_D,
            $action,
            $message
        );
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function reset(int $id_u, ?int $admin_id_u = null): void
    {
        $user_info = $this->utilisateurSQL->getInfo($id_u);
        if (!$user_info) {
            throw new NotFoundException("Utilisateur $id_u inconnu");
        }
        $this->assertEnabled($id_u);
        if ($admin_id_u === null) {
            $this->enroll($id_u);
            return;
        }
        $this->delete($id_u);
        $this->addJournalEntry(
            $user_info,
            $admin_id_u,
            'double authentification réinitialisée',
            "{$user_info['login']} ($id_u) : double authentification réinitialisée par un administrateur"
        );
    }

    public function cancelEnrolment(int $id_u): void
    {
        if ($this->isEnabled($id_u)) {
            $this->utilisateurMfaSQL->clearPendingSecret($id_u);
            return;
        }
        $this->delete($id_u);
    }

    public function startEnrolment(int $id_u): void
    {
        if (!$this->isPending($id_u)) {
            $this->enroll($id_u);
        }
    }

    public function delete(int $id_u): void
    {
        $this->utilisateurMfaSQL->delete($id_u);
        $this->recoveryCodeSQL->deleteAll($id_u);
    }

    public function deleteAllExceptAdmin(): void
    {
        $this->utilisateurMfaSQL->deleteAllExcept(UserAuthenticationService::ADMIN_LOGIN);
        $this->recoveryCodeSQL->deleteAllExcept(UserAuthenticationService::ADMIN_LOGIN);
    }

    /**
     * @return string[]
     * @throws RandomException
     */
    private function generateRecoveryCodes(int $id_u): array
    {
        $displayCodes = [];
        $hashes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $normalized = bin2hex(random_bytes(10));
            $displayCodes[] = implode('-', str_split($normalized, 5));
            $hashes[] = $this->hashRecoveryCode($normalized);
        }
        $this->recoveryCodeSQL->replaceAll($id_u, $hashes);
        return $displayCodes;
    }

    private function verifyRecoveryCode(int $id_u, string $code): bool
    {
        $normalized = strtolower((string)preg_replace('/[^a-zA-Z0-9]/', '', $code));
        if ($normalized === '') {
            return false;
        }
        return $this->recoveryCodeSQL->consume($id_u, $this->hashRecoveryCode($normalized));
    }

    public function countRemainingRecoveryCodes(int $id_u): int
    {
        return $this->recoveryCodeSQL->countRemaining($id_u);
    }

    private function hashRecoveryCode(string $normalizedCode): string
    {
        return hash('sha256', $normalizedCode);
    }
}
