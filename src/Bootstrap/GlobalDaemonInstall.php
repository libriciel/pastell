<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use DaemonSQL;
use JobQueueSQL;

class GlobalDaemonInstall implements InstallableBootstrap
{
    public function __construct(
        private readonly JobQueueSQL $jobQueueSQL,
        private readonly DaemonSQL $daemonSQL,
    ) {
    }

    public function install(): InstallResult
    {
        $this->daemonSQL->checkConfig();
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
        return 'Global daemon install';
    }
}
