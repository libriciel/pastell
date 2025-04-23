<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use DaemonManager;

class GlobalDaemonInstall implements InstallableBootstrap
{
    public function __construct(
        private readonly DaemonManager $daemonManager,
    ) {
    }

    public function install(): InstallResult
    {
        $this->daemonManager->globalDaemonInstall();
        return InstallResult::InstallOk;
    }

    public function getName(): string
    {
        return 'Daemon configuration';
    }
}
