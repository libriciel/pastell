<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Pastell\Service\Journal\JournalEntryService;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use UserTokenSQL;
use JournalEventType;

final class UserTokenService
{
    private const TOKEN_HASH_ALGORITHM = 'sha256';

    public function __construct(
        private readonly UserTokenSQL $userTokenSQL,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function createToken(int $userId, string $name, string $expiration = null): string
    {
        $token = $this->generateToken();
        $this->userTokenSQL->create($userId, $name, $this->hashToken($token), $expiration);
        $message = "L'utilisateur $userId a créer le token « $name »";
        if ($expiration) {
            $message .= " (date d'expiration : $expiration)";
        }
        $this->journalEntryService->add(
            JournalEventType::USER_MODIFICATION,
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
        $tokens = $this->userTokenSQL->getTokensOfUser($userId);
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
        return $this->userTokenSQL->getUser($tokenId)['id_u'] ?? null;
    }

    public function getUserFromToken(string $token): ?array
    {
        $user = $this->userTokenSQL->getUserFromToken($this->hashToken($token));
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
        $tokenInfo = $this->userTokenSQL->getInfo($tokenId);
        $this->userTokenSQL->delete($tokenId);
        $this->journalEntryService->add(
            JournalEventType::USER_MODIFICATION,
            0,
            '',
            'delete-token',
            "L'utilisateur {$tokenInfo['id_u']} a supprimé son jeton « {$tokenInfo['name']} »"
        );
    }


    public function renewToken(int $tokenId): string
    {
        $token = $this->generateToken();
        $tokenInfo = $this->userTokenSQL->getInfo($tokenId);
        $this->userTokenSQL->updateToken($tokenInfo['id'], $this->hashToken($token));
        $this->journalEntryService->add(
            JournalEventType::USER_MODIFICATION,
            0,
            '',
            'delete-token',
            "L'utilisateur {$tokenInfo['id_u']} a renouvelé son jeton « {$tokenInfo['name']} »"
        );
        return $token;
    }

    public function getTokenInfo(string $token): ?array
    {
        return $this->userTokenSQL->getInfoByToken($this->hashToken($token));
    }

    private function generateToken(): string
    {
        return (new UriSafeTokenGenerator())->generateToken();
    }

    private function hashToken(string $token): string
    {
        return \hash(self::TOKEN_HASH_ALGORITHM, $token);
    }
}
