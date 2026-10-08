<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

enum MfaManagementAction: string
{
    case REGENERATE = 'regenerate';
    case DISABLE = 'disable';

    public function description(): string
    {
        return match ($this) {
            self::REGENERATE => 'Régénérer vos codes de récupération invalidera définitivement les anciens.',
            self::DISABLE => 'Désactiver votre double authentification réduira la sécurité de votre compte.',
        };
    }

    public function submitLabel(): string
    {
        return match ($this) {
            self::REGENERATE => 'Régénérer les codes de récupération',
            self::DISABLE => 'Désactiver',
        };
    }

    public static function tryFromParam(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom($value) : null;
    }
}
