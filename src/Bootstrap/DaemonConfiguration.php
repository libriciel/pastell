<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use ConfigurationSQL;
use DaemonManager;
use DaemonSQL;
use JobQueueSQL;

class DaemonConfiguration implements InstallableBootstrap
{
    public function __construct(
        private readonly JobQueueSQL $jobQueueSQL,
        private readonly DaemonSQL $daemonSQL,
        private readonly ConfigurationSQL $configurationSQL,
        private readonly array $admin_email,
    ) {
    }

    public function install(): InstallResult
    {
        if (!$this->configurationSQL->hasConfiguration(DaemonManager::NB_WORKERS, ConfigurationSQL::NULL_ID_E)) {
            $this->daemonSQL->setNbWorkers((int)NB_WORKERS);
        }

        if ($this->daemonSQL->getGlobalDaemon() === null) {
            $this->daemonSQL->insertGlobalDaemon(implode(',', $this->admin_email));
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
