<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

enum EntityType: string
{
    case COLLECTIVITE = 'collectivite';
    case CENTRE_DE_GESTION = 'centre_de_gestion';
}
