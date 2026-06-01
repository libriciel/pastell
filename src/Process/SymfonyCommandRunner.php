<?php

declare(strict_types=1);

namespace Pastell\Process;

use Symfony\Component\Process\Process;

final class SymfonyCommandRunner implements CommandRunner
{
    public function run(array $command, ?string $cwd = null): CommandResult
    {
        return $this->execute(new Process($command, $cwd));
    }

    private function execute(Process $process): CommandResult
    {
        $process->run();

        return new CommandResult(
            exitCode: $process->getExitCode() ?? -1,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
        );
    }
}
