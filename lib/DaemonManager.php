<?php

class DaemonManager
{
    public const IS_RUNNING = 1;
    public const IS_STOPPED = 0;

    public function __construct(
        private readonly DaemonSQL $daemonSQL,
    ) {
    }

    public function status()
    {
        $command = 'supervisorctl status pastell-daemon';
        exec($command, $output);
        if (str_contains($output[0], 'RUNNING')) {
            return self::IS_RUNNING;
        }
        return self::IS_STOPPED;
    }

    public function getDaemonPID()
    {
        $command = 'supervisorctl pid pastell-daemon';
        exec($command, $output, $result_code);
        if ($result_code !== 0) {
            return 0;
        }
        return (int)$output[0];
    }

    public function start()
    {
        if ($this->status() === self::IS_RUNNING) {
            return self::IS_RUNNING;
        }

        $command = 'supervisorctl start pastell-daemon';
        exec($command);
        return $this->status();
    }


    public function stop()
    {
        if ($this->status() == self::IS_STOPPED) {
            return self::IS_STOPPED;
        }
        $command = 'supervisorctl stop pastell-daemon';
        exec($command);
        $this->daemonSQL->setDaemonState(DaemonSQL::GLOBAL_DAEMON, self::IS_STOPPED);
        return $this->status();
    }

    public function restart()
    {
        $this->stop();
        $this->start();
    }
}
