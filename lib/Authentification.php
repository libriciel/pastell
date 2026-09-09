<?php

class Authentification
{
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
}
