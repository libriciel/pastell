<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use EntiteMfaObligationSQL;
use OTPHP\TOTP;
use Random\RandomException;
use UtilisateurMfaRecoveryCodeSQL;
use UtilisateurMfaSQL;
use UtilisateurSQL;

final class MfaService
{
    private const string ISSUER = 'Pastell';
    private const int RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private readonly UtilisateurMfaSQL $utilisateurMfaSQL,
        private readonly UtilisateurMfaRecoveryCodeSQL $recoveryCodeSQL,
        private readonly EntiteMfaObligationSQL $entiteMfaObligationSQL,
        private readonly UtilisateurSQL $utilisateurSQL,
    ) {
    }

    public function isEnabled(int $id_u): bool
    {
        return $this->utilisateurMfaSQL->isEnabled($id_u);
    }

    public function mustEnroll(int $id_u): bool
    {
        if ($this->isEnabled($id_u)) {
            return false;
        }
        return $this->utilisateurMfaSQL->isEnrolmentRequired($id_u) || $this->isObligatory($id_u);
    }

    public function isObligatory(int $id_u): bool
    {
        $info = $this->utilisateurSQL->getInfo($id_u);
        if (!$info || !empty($info['is_api'])) {
            return false;
        }
        return $this->entiteMfaObligationSQL->appliesTo((int)$info['id_e']);
    }

    public function getInfo(int $id_u): array
    {
        return $this->utilisateurMfaSQL->getInfo($id_u);
    }

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function getProvisioningUri(string $secret, string $label): string
    {
        $totp = TOTP::createFromSecret($secret);
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
        $totp = TOTP::createFromSecret($secret);
        $now = time();
        $period = $totp->getPeriod();
        return array_any([-1, 0, 1], fn($step) => $totp->verify($code, $now + $step * $period));
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

    public function requireReenrolment(int $id_u): void
    {
        $this->utilisateurMfaSQL->requireReenrolment($id_u);
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
