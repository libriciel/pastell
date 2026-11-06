<?php

declare(strict_types=1);

namespace Pastell\Service\Connecteur\Frequence;

enum ConnecteurFrequenceActionType: string
{
    case DOCUMENT = 'document';
    case CONNECTEUR = 'connecteur';

    public static function fromValue(string $value): ?self
    {
        if ($value === '') {
            return null;
        }
        return self::tryFrom($value) ?? self::DOCUMENT;
    }

    public function label(): string
    {
        return match ($this) {
            self::DOCUMENT => 'Document',
            self::CONNECTEUR => 'Connecteur',
        };
    }
}
