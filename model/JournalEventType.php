<?php

declare(strict_types=1);

enum JournalEventType: int
{
    case DOCUMENT_ACTION = 1;
    case NOTIFICATION = 2;
    case ENTITY_MODIFICATION = 3;
    case USER_MODIFICATION = 4;
    case SECURE_MAIL = 5;
    case LOGIN = 6;
    case DOCUMENT_CONSULTATION = 7;
    case MAIL_SENDING = 8;
    case DOCUMENT_ACTION_ERROR = 9;
    case DOCUMENT_BATCH_PROCESSING = 10;
    case TEST = 11;
    case DOCUMENT_TYPE_EDITION = 12;
    case JOURNAL = 13;
    case COMMAND = 14;

    public function label(): string
    {
        return match ($this) {
            self::DOCUMENT_ACTION => 'Action sur un dossier',
            self::NOTIFICATION => 'Notification',
            self::ENTITY_MODIFICATION => 'Gestion des entités',
            self::USER_MODIFICATION => 'Gestion des utilisateurs',
            self::SECURE_MAIL => 'Mail sécurisé',
            self::LOGIN => 'Connexion',
            self::DOCUMENT_CONSULTATION => 'Consultation de dossier ou de document',
            self::MAIL_SENDING => 'Envoi de mail',
            self::DOCUMENT_ACTION_ERROR => 'Erreur lors de la tentative d\'une action',
            self::DOCUMENT_BATCH_PROCESSING => 'Programmation d\'un traitement par lot',
            self::TEST => 'Test',
            self::DOCUMENT_TYPE_EDITION => 'Action sur un type de dossier personnalisé',
            self::JOURNAL => 'Action sur le journal',
            self::COMMAND => 'Action par commande',
        };
    }
}
