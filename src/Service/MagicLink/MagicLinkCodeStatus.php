<?php

declare(strict_types=1);

namespace Pastell\Service\MagicLink;

enum MagicLinkCodeStatus
{
    case Valid;
    case WrongCode;
    case Revoked;
    case InvalidLink;
}
