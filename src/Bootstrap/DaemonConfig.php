<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use DaemonSQL;

class DaemonConfig implements InstallableBootstrap
{
    public function __construct(
        private readonly DaemonSQL $daemonSQL,
    ) {
    }

    public function install(): InstallResult
    {
        $this->daemonSQL->checkConfig();
        return InstallResult::InstallOk;
    }

    public function getName(): string
    {
        return 'Daemon configuration';
    }
}
