<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use ConnecteurFactory;
use LDAPVerification;
use UnrecoverableException;
use UtilisateurSQL;

final class UserAuthenticationService
{
    public const string ADMIN_LOGIN = 'admin';

    public function __construct(
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly UtilisateurSQL $utilisateurSQL,
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function verifyPassword(int $id_u, string $password): bool
    {
        $login = $this->getLogin($id_u);
        $verificationConnecteur = $this->connecteurFactory->getGlobalConnecteur('Vérification');
        if ($verificationConnecteur instanceof LDAPVerification && $login !== self::ADMIN_LOGIN) {
            return $verificationConnecteur->verifLogin($login, $password);
        }
        return $this->utilisateurSQL->verifPassword($id_u, $password);
    }

    public function isExternal(int $id_u): bool
    {
        return $this->getLogin($id_u) !== self::ADMIN_LOGIN
            && $this->connecteurFactory->getGlobalConnecteur('Authentification') !== false;
    }

    private function getLogin(int $id_u): string
    {
        return $this->utilisateurSQL->getInfo($id_u)['login'];
    }
}
