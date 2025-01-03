<?php

declare(strict_types=1);

class DaemonManager
{
    public const IS_RUNNING = 1;
    public const IS_STOPPED = 0;

    public function __construct(
        private readonly DaemonSQL $daemonSQL,
        private readonly JobQueueSQL $jobQueueSQL,
    ) {
    }

    public function status(): int
    {
        $command = 'supervisorctl status pastell-daemon';
        exec($command, $output);
        if (str_contains($output[0], 'RUNNING')) {
            return self::IS_RUNNING;
        }
        return self::IS_STOPPED;
    }

    public function getDaemonPID(): int
    {
        $command = 'supervisorctl pid pastell-daemon';
        exec($command, $output, $result_code);
        if ($result_code !== 0) {
            return 0;
        }
        return (int)$output[0];
    }

    /**
     * @throws UnrecoverableException
     */
    public function start(): int
    {
        if ($this->status() === self::IS_RUNNING) {
            return self::IS_RUNNING;
        }

        if (!$this->daemonSQL->getDaemon(0)) {
            $this->addDaemon(0, (int) NB_WORKERS);
        }
        $command = 'supervisorctl start pastell-daemon';
        exec($command);
        return $this->status();
    }


    public function stop(): int
    {
        if ($this->status() === self::IS_STOPPED) {
            return self::IS_STOPPED;
        }
        $command = 'supervisorctl stop pastell-daemon';
        exec($command);
        $this->daemonSQL->setDaemonState(DaemonSQL::GLOBAL_DAEMON, self::IS_STOPPED);
        return $this->status();
    }

    /**
     * @throws UnrecoverableException
     */
    public function restart(): void
    {
        $this->stop();
        $this->start();
    }

    /**
     * @throws UnrecoverableException
     */
    public function startDaemon(int $id_daemon): void
    {
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        if ($daemon === null) {
            throw new UnrecoverableException('Démarrage impossible, le gestionnaire de tâches n\'existe pas.');
        }
        $this->daemonSQL->setDaemonState($id_daemon, self::IS_RUNNING);
    }

    /**
     * @throws UnrecoverableException
     */
    public function stopDaemon(int $id_daemon): void
    {
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        if ($daemon === null) {
            throw new UnrecoverableException('Arrêt impossible, le gestionnaire de tâches n\'existe pas.');
        }
        $this->daemonSQL->setDaemonState($id_daemon, self::IS_STOPPED);
    }

    /**
     * @throws UnrecoverableException
     */
    public function addDaemon(int $id_e, int $nb_allocated_workers = 0): void
    {
        if ($this->daemonSQL->getDaemonByEntity($id_e)) {
            throw new UnrecoverableException('Création impossible, un daemon existe déjà pour cette entité.');
        }
        $nb_free_workers = $this->daemonSQL->getNbSharedWorkers();
        if ($nb_allocated_workers >= $nb_free_workers) {
            throw new UnrecoverableException('Création impossible, pas assez de workers disponibles.');
        }

        $id_daemon = $this->daemonSQL->insertDaemon($id_e);
        foreach ($this->jobQueueSQL->getJobsByAncestor($id_e) as $job) {
            $this->jobQueueSQL->updateClosestDaemon($job->id_job);
        }
        $this->daemonSQL->allocateWorkers($id_daemon, $nb_allocated_workers);
        $this->daemonSQL->refreshAvailableWorkers();
    }

    /**
     * @throws UnrecoverableException
     */
    public function removeDaemon(int $id_daemon): void
    {
        if ($id_daemon === 1) {
            throw new UnrecoverableException('Impossible de supprimer le gestionnaire de tâches global.');
        }
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        if ($daemon === null) {
            throw new UnrecoverableException('Suppression impossible, le gestionnaire de tâches n\'existe pas.');
        }
        $this->daemonSQL->deleteDaemon($daemon->id_daemon);
        foreach ($this->jobQueueSQL->getJobsByDaemon($id_daemon) as $job) {
            $this->jobQueueSQL->updateClosestDaemon($job->id_job);
        }
        $this->daemonSQL->refreshAvailableWorkers();
    }

    public function allocateWorkers(int $id_e_daemin, int $nb_allocated_workers): void
    {
        $this->daemonSQL->allocateWorkers($id_e_daemin, $nb_allocated_workers);
        $this->daemonSQL->refreshAvailableWorkers();
    }

    public function getNbWorkers(): int
    {
        return $this->daemonSQL->getNbTotalWorkers();
    }

    public function globalDaemonInstall(): void
    {
        if ($this->daemonSQL->getGlobalDaemon() === null) {
            $this->daemonSQL->insertGlobalDaemon();
            foreach ($this->jobQueueSQL->getAllJobs() as $job) {
                $this->jobQueueSQL->updateClosestDaemon($job->id_job);
            }
        }
    }
}
