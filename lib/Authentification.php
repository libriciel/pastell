<?php

class Authentification
{
    private const int MFA_PENDING_TTL_IN_SECONDS = 300;
    private const int MFA_ENROLMENT_TTL_IN_SECONDS = 600;

    public function connexion($login, $id_u)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['connexion']['login'] = $login;
        $_SESSION['connexion']['id_u'] = $id_u;
    }

    /** @phpstan-impure  */
    public function isConnected()
    {
        return isset($_SESSION['connexion']);
    }

    public function getLogin()
    {
        if (! $this->isConnected()) {
            return false;
        }
        return $_SESSION['connexion']['login'];
    }

    public function getId()
    {
        if (! $this->isConnected()) {
            return false;
        }
        return $_SESSION['connexion']['id_u'];
    }

    public function deconnexion()
    {
        if ($this->isConnected()) {
            unset($_SESSION['connexion']);
        }
        $this->clearMfaPending();
    }

    public function setMagicLinkId(string $magicLinkId): void
    {
        $_SESSION['connexion']['magic_link_id'] = $magicLinkId;
    }

    public function getMagicLinkId(): ?string
    {
        if (! $this->isConnected()) {
            return null;
        }
        return $_SESSION['connexion']['magic_link_id'] ?? null;
    }

    public function authoriseMfaEnrolment(): void
    {
        $_SESSION['connexion']['mfa_enrolment_authorised_at'] = time();
    }

    public function isMfaEnrolmentAuthorised(): bool
    {
        $authorised_at = $_SESSION['connexion']['mfa_enrolment_authorised_at'] ?? null;
        if ($authorised_at === null) {
            return false;
        }
        if (time() - $authorised_at > self::MFA_ENROLMENT_TTL_IN_SECONDS) {
            $this->clearMfaEnrolmentAuthorisation();
            return false;
        }
        return true;
    }

    public function clearMfaEnrolmentAuthorisation(): void
    {
        unset($_SESSION['connexion']['mfa_enrolment_authorised_at']);
    }

    public function setMfaPending(int $id_u, string $login, ?string $request_uri): void
    {
        $_SESSION['mfa_pending'] = [
            'id_u' => $id_u,
            'login' => $login,
            'request_uri' => $request_uri,
            'created_at' => time(),
        ];
    }

    public function getMfaPending(): ?array
    {
        $mfa_pending = $_SESSION['mfa_pending'] ?? null;
        if ($mfa_pending === null) {
            return null;
        }
        if (time() - ($mfa_pending['created_at'] ?? 0) > self::MFA_PENDING_TTL_IN_SECONDS) {
            $this->clearMfaPending();
            return null;
        }
        return $mfa_pending;
    }

    public function clearMfaPending(): void
    {
        unset($_SESSION['mfa_pending']);
    }
}
