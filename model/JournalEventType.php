<?php

declare(strict_types=1);

enum JournalEventType: int
{
    case DOCUMENT_ACTION = 1;
    case NOTIFICATION = 2;
    case MODIFICATION_ENTITE = 3;
    case MODIFICATION_UTILISATEUR = 4;
    case MAIL_SECURISE = 5;
    case CONNEXION = 6;
    case DOCUMENT_CONSULTATION = 7;
    case ENVOI_MAIL = 8;
    case DOCUMENT_ACTION_ERROR = 9;
    case DOCUMENT_TRAITEMENT_LOT = 10;
    case TEST = 11;
    case TYPE_DOSSIER_EDITION = 12;
    case JOURNAL = 13;
    case COMMANDE = 14;
}
