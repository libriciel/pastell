<?php

declare(strict_types=1);

class RecupFinParapheurRetrieveFoldersAsync extends ActionExecutor
{
    public function go(): bool
    {
        $jobManager = $this->objectInstancier->getInstance(JobManager::class);
        $jobManager->setJobForConnecteur(
            $this->id_ce,
            'recup',
            'La récupération va être déclenchée en tâche de fond'
        );
        $this->setLastMessage('Récupération asynchrone lancée');
        return true;
    }
}
