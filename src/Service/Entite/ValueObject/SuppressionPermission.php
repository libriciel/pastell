<?php

namespace Pastell\Service\Entite\ValueObject;

final class SuppressionPermission
{
    public function __construct(
        private readonly bool $authorise,
        private readonly ?string $raison = null,
    ) {
    }

    public function isGranted(): bool
    {
        return $this->authorise;
    }

    public function getRaisonRefus(): string
    {
        return $this->raison;
    }
}
