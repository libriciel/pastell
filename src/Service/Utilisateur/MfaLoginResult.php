<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

enum MfaLoginResult
{
    case TOTP;
    case RECOVERY_CODE;
    case INVALID;
}
