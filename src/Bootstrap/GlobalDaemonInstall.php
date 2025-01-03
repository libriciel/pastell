<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use DaemonManager;
use DaemonSQL;
use JobQueueSQL;

class GlobalDaemonInstall implements InstallableBootstrap
{
    public function __construct(
        private readonly DaemonManager $daemonManager,
        private readonly JobQueueSQL $jobQueueSQL,
        private readonly DaemonSQL $daemonSQL,
    ) {
    }

    public function install(): InstallResult
    {
        $this->daemonManager->globalDaemonInstall();
        foreach ($this->jobQueueSQL->getJobsByDaemon(DaemonSQL::UNASSIGNED_DAEMON) as $job) {
            $this->jobQueueSQL->updateDaemon($job->id_job, $this->daemonSQL->getClosestDaemon($job->id_e));
        }
        return InstallResult::InstallOk;
    }

    public function getName(): string
    {
        return 'Global daemon install';
    }
}
