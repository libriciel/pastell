<?php

declare(strict_types=1);

namespace Pastell\Service\MagicLink;

final readonly class MagicLinkCodeCheck
{
    public function __construct(
        public MagicLinkCodeStatus $status,
        public ?array $link = null,
        public int $remainingAttempts = 0,
    ) {
    }
}
