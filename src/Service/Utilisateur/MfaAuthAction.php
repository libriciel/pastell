<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

enum MfaAuthAction: string
{
    case REGENERATE = 'regenerate';
    case DESACTIVATION = 'desactivation';

    public function description(): string
    {
        return match ($this) {
            self::REGENERATE => 'Régénérer vos codes de récupération invalidera définitivement les anciens.',
            self::DESACTIVATION => 'Désactiver votre double authentification réduira la sécurité de votre compte.',
        };
    }

    public function submitLabel(): string
    {
        return match ($this) {
            self::REGENERATE => 'Régénérer les codes de récupération',
            self::DESACTIVATION => 'Désactiver',
        };
    }

    public static function tryFromParam(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom($value) : null;
    }
}
