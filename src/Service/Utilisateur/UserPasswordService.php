<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use UtilisateurSQL;

final class UserPasswordService
{
    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
    ) {
    }

    public function setPassword(int $id_u, string $password): void
    {
        $this->utilisateurSQL->updatePasswordHash($id_u, password_hash($password, PASSWORD_DEFAULT));
    }

    public function verifyPassword(int $id_u, string $password): bool
    {
        $user_info = $this->utilisateurSQL->getInfo($id_u);
        if (!$user_info || $user_info['password'] === null) {
            return false;
        }
        return password_verify($password, $user_info['password']);
    }
}
