<?php

declare(strict_types=1);

namespace Pastell\Process;

interface CommandRunner
{
    public function run(array $command, ?string $cwd = null): CommandResult;
}
