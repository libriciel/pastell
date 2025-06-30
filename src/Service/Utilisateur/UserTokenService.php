<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use UsersToken;

final class UserTokenService
{
    public function __construct(
        private readonly UsersToken $usersToken,
        private readonly \Journal $journal,
    ) {
    }

    public function createToken(int $userId, string $name, ?string $expiration = null): string
    {
        $token = $this->generateToken();
        $this->usersToken->create($userId, $name, $token, $expiration);
        $message = "L'utilisateur $userId a créer le token « $name »";
        if ($expiration) {
            $message .= " (date d'expiration : $expiration)";
        }
        $this->journal->add(
            \Journal::MODIFICATION_UTILISATEUR,
            0,
            '',
            'create-token',
            $message
        );
        return $token;
    }

    public function getTokens(int $userId): array
    {
        $now = \time();
        $tokens = $this->usersToken->getTokensOfUser($userId);
        \array_walk($tokens, static function (&$token) use ($now) {
            if ($token['expired_at'] === null) {
                $isExpired = false;
            } else {
                $isExpired = \strtotime($token['expired_at']) < $now;
            }
            $token['is_expired'] = $isExpired;
        });
        return $tokens;
    }

    public function getUser(int $tokenId): ?int
    {
        return $this->usersToken->getUser($tokenId)['id_u'] ?? null;
    }

    public function getUserFromToken(string $token): ?array
    {
        $user = $this->usersToken->getUserFromToken($token);
        if ($user !== null) {
            if ($user['expired_at'] === null) {
                $isExpired = false;
            } else {
                $isExpired = \strtotime($user['expired_at']) < \time();
            }
            $user['is_expired'] = $isExpired;
        }
        return $user;
    }

    public function deleteToken(int $tokenId): void
    {
        $tokenInfo = $this->usersToken->getTokenInfo($tokenId);
        $this->usersToken->deleteToken($tokenId);
        $this->journal->add(
            \Journal::MODIFICATION_UTILISATEUR,
            0,
            '',
            'delete-token',
            "L'utilisateur {$tokenInfo['id_u']} a supprimé son jeton « {$tokenInfo['name']} »"
        );
    }


    public function renewToken(int $tokenId): string
    {
        $token = $this->generateToken();
        $tokenInfo = $this->usersToken->getTokenInfo($tokenId);
        $this->usersToken->updateToken($tokenInfo['id'], $token);
        $this->journal->add(
            \Journal::MODIFICATION_UTILISATEUR,
            0,
            '',
            'delete-token',
            "L'utilisateur {$tokenInfo['id_u']} a renouvelé son jeton « {$tokenInfo['name']} »"
        );
        return $token;
    }

    private function generateToken(): string
    {
        return (new UriSafeTokenGenerator())->generateToken();
    }

    public function getTokenInfo(string $token): array
    {
        return $this->usersToken->getTokenInfoByToken($token);
    }
}
