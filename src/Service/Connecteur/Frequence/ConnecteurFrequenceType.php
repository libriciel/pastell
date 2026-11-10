<?php

declare(strict_types=1);

namespace Pastell\Service\Connecteur\Frequence;

enum ConnecteurFrequenceType: string
{
    case GLOBAL = 'global';
    case ENTITE = 'entite';

    public static function fromValue(string $value): ?self
    {
        if ($value === '') {
            return null;
        }
        return self::tryFrom($value) ?? self::ENTITE;
    }

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global',
            self::ENTITE => 'Entité',
        };
    }
}
