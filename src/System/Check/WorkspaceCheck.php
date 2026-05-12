<?php

namespace Pastell\System\Check;

use ConfigurationSQL;
use FreeSpace;
use Pastell\System\CheckInterface;
use Pastell\System\HealthCheckItem;
use VerifEnvironnement;

class WorkspaceCheck implements CheckInterface
{
    public function __construct(
        private readonly VerifEnvironnement $verifEnvironnement,
        private readonly FreeSpace $freeSpace,
        private readonly ConfigurationSQL $configurationSQL,
    ) {
    }

    public function check(): array
    {
        $spaceUsed = $this->freeSpace->getFreeSpace(WORKSPACE_PATH);
        $usagePercent = $this->freeSpace->getUsagePercent(WORKSPACE_PATH);
        $threshold = $this->configurationSQL->getWorkspaceAlertThreshold();

        return [
            new HealthCheckItem(
                WORKSPACE_PATH . ' accessible en lecture/écriture ?',
                $this->verifEnvironnement->checkWorkspace() ? 'OK' : 'KO'
            )->setSuccess($this->verifEnvironnement->checkWorkspace()),
            new HealthCheckItem(
                'Taille totale de la partition',
                $spaceUsed['disk_total_space']
            ),
            new HealthCheckItem(
                'Taille des données',
                $spaceUsed['disk_use_space']
            ),
            new HealthCheckItem(
                "Taux d'occupation",
                $spaceUsed['disk_use_percent']
            )->setSuccess($usagePercent < $threshold)
        ];
    }
}
