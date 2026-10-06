<?php

declare(strict_types=1);

enum JournalAction: string
{
    case SUPPRIME = 'Supprimé';
    case MODIFFIE = 'Modifié';
    case AJOUTE = 'Ajouté';
    case CREATED = 'Créé';
}
