<?php

declare(strict_types=1);

namespace Pastell\Service\Droit;

enum DroitType: string
{
    case LECTURE = 'lecture';
    case EDITION = 'edition';
    case CREATION = 'creation';
    case SUPPRESSION = 'suppression';
    case ACTION = 'action';
}
