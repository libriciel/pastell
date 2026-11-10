<?php

declare(strict_types=1);

namespace Pastell\Service\Connecteur\Frequence;

final class ConnecteurFrequenceExpressionLine
{
    public function __construct(
        public readonly int $nb_try,
        public readonly int $frequence = 0,
        public readonly bool $in_seconds = false,
        public readonly string $cron = '',
    ) {
    }
}
