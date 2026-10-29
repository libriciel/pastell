<?php

declare(strict_types=1);

enum JournalAction: string
{
    case DELETED = 'Supprimé';
    case MODIFIED = 'Modifié';
    case ADDED = 'Ajouté';
    case CREATED = 'Créé';
}
