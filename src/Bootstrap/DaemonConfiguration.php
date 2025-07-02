<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use ConfigurationSQL;
use DaemonManager;
use DaemonSQL;
use JobQueueSQL;
use ObjectInstancier;

class DaemonConfiguration implements InstallableBootstrap
{
    public function __construct(
        private readonly JobQueueSQL $jobQueueSQL,
        private readonly DaemonSQL $daemonSQL,
        private readonly ConfigurationSQL $configurationSQL,
        private readonly ObjectInstancier $objectInstancier
    ) {
    }

    public function install(): InstallResult
    {
        if (!$this->configurationSQL->hasConfiguration(DaemonManager::NB_WORKERS)) {
            $this->daemonSQL->setNbWorkers((int)NB_WORKERS);
        }

        if (!$this->configurationSQL->hasConfiguration(DaemonManager::DAEMON_ADMIN_EMAIL)) {
            $this->configurationSQL->setConfiguration(
                DaemonManager::DAEMON_ADMIN_EMAIL,
                implode(',', $this->objectInstancier->getInstance('admin_email')),
                0
            );
        }

        if ($this->daemonSQL->getGlobalDaemon() === null) {
            $this->daemonSQL->insertGlobalDaemon();
            foreach ($this->jobQueueSQL->getJobsByDaemon(DaemonSQL::UNASSIGNED_DAEMON) as $job) {
                $this->jobQueueSQL->updateDaemon($job->id_job, $this->daemonSQL->getClosestDaemon($job->id_e));
            }
        }
        return InstallResult::InstallOk;
    }

    public function getName(): string
    {
        return 'Daemon configuration';
    }
}
