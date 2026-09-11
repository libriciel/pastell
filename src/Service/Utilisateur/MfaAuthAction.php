<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

enum MfaAuthAction: string
{
    case REGENERATE = 'regenerate';
    case REINITIALISATION = 'reinitialisation';
    case DESACTIVATION = 'desactivation';

    public function description(): string
    {
        return match ($this) {
            self::REGENERATE => 'Régénérer vos codes de récupération invalidera définitivement les anciens.',
            self::REINITIALISATION =>
                'Réinitialiser votre double authentification vous obligera à reconfigurer votre application.',
            self::DESACTIVATION => 'Désactiver votre double authentification réduira la sécurité de votre compte.',
        };
    }

    public function submitLabel(): string
    {
        return match ($this) {
            self::REGENERATE => 'Régénérer les codes de récupération',
            self::REINITIALISATION => 'Réinitialiser',
            self::DESACTIVATION => 'Désactiver',
        };
    }

    public static function tryFromParam(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom($value) : null;
    }
}
