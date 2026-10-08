<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OTPHP\TOTP;
use Pastell\Clock\SystemClock;
use Random\RandomException;
use UtilisateurMfaRecoveryCodeSQL;
use UtilisateurMfaSQL;

final class MfaService
{
    private const string ISSUER = 'Pastell';
    private const int RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private readonly UtilisateurMfaSQL $utilisateurMfaSQL,
        private readonly UtilisateurMfaRecoveryCodeSQL $recoveryCodeSQL,
        private readonly SystemClock $clock,
    ) {
    }

    public function isEnabled(int $id_u): bool
    {
        return $this->utilisateurMfaSQL->isEnabled($id_u);
    }

    public function getInfo(int $id_u): array
    {
        return $this->utilisateurMfaSQL->getInfo($id_u);
    }

    public function generateSecret(): string
    {
        return TOTP::generate($this->clock)->getSecret();
    }

    public function getProvisioningUri(string $secret, string $label): string
    {
        $totp = TOTP::createFromSecret($secret, $this->clock);
        $totp->setLabel($label);
        $totp->setIssuer(self::ISSUER);
        return $totp->getProvisioningUri();
    }

    public function getQrCodeSvg(string $uri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
        return $writer->writeString($uri);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->getMatchingCounter($secret, $code) !== null;
    }

    public function verifyForUser(int $id_u, string $code): bool
    {
        $info = $this->getInfo($id_u);
        if (empty($info['secret'])) {
            return false;
        }
        $counter = $this->getMatchingCounter($info['secret'], $code);
        if ($counter === null || $counter <= (int)($info['last_used_counter'] ?? -1)) {
            return false;
        }
        $this->utilisateurMfaSQL->updateLastUsedCounter($id_u, $counter);
        return true;
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

    public function enroll(int $id_u, string $secret): void
    {
        $this->utilisateurMfaSQL->enroll($id_u, $secret);
    }

    public function confirm(int $id_u): void
    {
        $this->utilisateurMfaSQL->confirm($id_u);
    }

    public function delete(int $id_u): void
    {
        $this->utilisateurMfaSQL->delete($id_u);
        $this->recoveryCodeSQL->deleteAll($id_u);
    }

    /**
     * @return string[] Les codes en clair, à afficher une seule fois.
     * @throws RandomException
     */
    public function generateRecoveryCodes(int $id_u): array
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

    public function verifyRecoveryCode(int $id_u, string $code): bool
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
