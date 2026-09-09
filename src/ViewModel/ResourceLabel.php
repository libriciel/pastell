<?php

declare(strict_types=1);

namespace Pastell\ViewModel;

final class ResourceLabel
{
    public function __construct(
        public readonly string $singular,
        public readonly string $plural,
        public readonly bool $feminine,
    ) {
    }

    public function deletionLabel(int $count): string
    {
        $plural = $count > 1;
        $noun = $plural ? $this->plural : $this->singular;
        $verb = $plural ? 'vont être' : 'va être';
        $participle = 'supprimé' . ($this->feminine ? 'e' : '') . ($plural ? 's' : '');
        return "$noun $verb $participle";
    }
}
