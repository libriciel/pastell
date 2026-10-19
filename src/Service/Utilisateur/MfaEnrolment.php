<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

final readonly class MfaEnrolment
{
    public function __construct(
        public string $secret,
        public string $qrCodeSvg,
    ) {
    }
}
