<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

enum MfaManagementAction: string
{
    case ENABLE = 'enable';
    case REGENERATE = 'regenerate';
    case DISABLE = 'disable';
    case RESET = 'reset';

    public function description(): string
    {
        return match ($this) {
            self::ENABLE => 'Confirmez votre mot de passe pour activer la double authentification.',
            self::REGENERATE => 'Régénérer vos codes de récupération invalidera définitivement les anciens.',
            self::DISABLE => 'Désactiver votre double authentification réduira la sécurité de votre compte.',
            self::RESET => 'Réinitialiser votre double authentification vous obligera à reconfigurer votre application.',
        };
    }

    public function submitLabel(): string
    {
        return match ($this) {
            self::ENABLE => 'Continuer',
            self::REGENERATE => 'Régénérer les codes de récupération',
            self::DISABLE => 'Désactiver',
            self::RESET => 'Réinitialiser',
        };
    }

    public function requiresEnabledMfa(): bool
    {
        return $this !== self::ENABLE;
    }

    public static function tryFromParam(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom($value) : null;
    }
}
