<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupActesS2low\Action;

use JobManager;

final class FetchActesAsync extends \ConnecteurTypeActionExecutor
{
    public function go(): bool
    {
        $jobManager = $this->objectInstancier->getInstance(JobManager::class);
        $jobManager->setJobForConnecteur(
            $this->id_ce,
            'fetch',
            'La récupération va être déclenchée en tâche de fond'
        );
        $this->setLastMessage('Récupération asynchrone lancée');
        return true;
    }
}
