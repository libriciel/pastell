<?php

declare(strict_types=1);

namespace Pastell\Configuration;

enum JobStatus: int
{
    case WAITING = 0; // unlock, en attente, non suspendu
    case LAUNCHING = 1;
    case ERROR_DAEMON = 2;
    case ERROR_ACTION = 3;
    case SUSPENDED_TRY_MAX = 4;
    case SUSPENDED_BY_USER = 5;
    case KILLED_BY_USER = 6;

    public function label(): string
    {
        return match ($this) {
            self::WAITING => 'En attente',
            self::LAUNCHING => 'Lancement du processus',
            self::ERROR_DAEMON => 'Erreur de lancement du processus',
            self::ERROR_ACTION => "Erreur d'exécution de l'action",
            self::SUSPENDED_TRY_MAX => "Maximum d'essais atteint",
            self::SUSPENDED_BY_USER => 'Suspendu manuellement',
            self::KILLED_BY_USER => 'Processus tué manuellement',
        };
    }
}
