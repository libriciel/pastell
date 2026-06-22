<?php

declare(strict_types=1);

namespace Pastell\Service\Menu;

final class MenuGaucheOption
{
    public function __construct(
        public readonly string $libelle,
        public readonly string $id,
        public readonly string $lien,
    ) {
    }

    public static function fromLien(string $libelle, string $lien): self
    {
        return new self($libelle, $lien, $lien);
    }

    public static function buildUrl(string $base, array $parameters): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';
        return $base . $separator . http_build_query($parameters);
    }

    public static function withParameters(string $libelle, string $id, array $parameters): self
    {
        return new self($libelle, $id, self::buildUrl($id, $parameters));
    }
}
