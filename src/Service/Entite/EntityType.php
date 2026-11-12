<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

enum EntityType: string
{
    case COLLECTIVITE = 'collectivite';
    case CENTRE_DE_GESTION = 'centre_de_gestion';

    public function label(): string
    {
        return match ($this) {
            self::COLLECTIVITE => 'Collectivité',
            self::CENTRE_DE_GESTION => 'Centre de gestion',
        };
    }

    public static function getLabel(string $type): string
    {
        return self::tryFrom($type)?->label() ?? $type;
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
