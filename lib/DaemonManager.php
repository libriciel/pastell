<?php

declare(strict_types=1);

class DaemonManager
{
    public const IS_RUNNING = 1;
    public const IS_STOPPED = 0;
    public const NB_WORKERS = 'NB_WORKERS';
    public const DAEMON_ADMIN_EMAIL = 'DAEMON_ADMIN_EMAIL';

    public function __construct(
        private readonly DaemonSQL $daemonSQL,
        private readonly JobQueueSQL $jobQueueSQL,
        private readonly ConfigurationSQL $configurationSQL
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
        if ($id_daemon === DaemonSQL::GLOBAL_DAEMON) {
            throw new UnrecoverableException(
                'Impossible de démarrer le gestionnaire de tâches global individuellement.'
            );
        }
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
        if ($id_daemon === DaemonSQL::GLOBAL_DAEMON) {
            throw new UnrecoverableException(
                'Impossible d\'arrêter le gestionnaire de tâches global individuellement.'
            );
        }
        if ($daemon === null) {
            throw new UnrecoverableException('Arrêt impossible, le gestionnaire de tâches n\'existe pas.');
        }
        $this->daemonSQL->setDaemonState($id_daemon, self::IS_STOPPED);
    }

    /**
     * @throws UnrecoverableException
     */
    public function addDaemon(int $id_e, int $nb_allocated_workers = 0): Daemon
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
            $this->updateClosestDaemon($job);
        }
        $this->allocateWorkers($id_daemon, $nb_allocated_workers);
        return $this->daemonSQL->getDaemon($id_daemon);
    }

    /**
     * @throws UnrecoverableException
     */
    public function removeDaemon(int $id_daemon): void
    {
        if ($id_daemon === DaemonSQL::GLOBAL_DAEMON) {
            throw new UnrecoverableException('Impossible de supprimer le gestionnaire de tâches global.');
        }
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        if ($daemon === null) {
            throw new UnrecoverableException('Suppression impossible, le gestionnaire de tâches n\'existe pas.');
        }
        $this->daemonSQL->deleteDaemon($daemon->id_daemon);
        foreach ($this->jobQueueSQL->getJobsByDaemon($daemon->id_daemon) as $job) {
            $this->updateClosestDaemon($job);
        }
        $this->daemonSQL->refreshAvailableWorkers();
    }

    public function allocateWorkers(int $id_daemon, int $nb_allocated_workers): void
    {
        $this->daemonSQL->allocateWorkers($id_daemon, $nb_allocated_workers);
        $this->daemonSQL->refreshAvailableWorkers();
    }

    public function updateClosestDaemon(Job $job): void
    {
        $closestDaemonId = $this->daemonSQL->getClosestDaemon($job->id_e);
        $this->jobQueueSQL->updateDaemon($job->id_job, $closestDaemonId);
    }

    /**
     * @throws UnrecoverableException
     */
    public function getAdminEmails(int $id_daemon): array
    {
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        if ($daemon === null) {
            throw new UnrecoverableException(
                'Impossible de récupérer les emails administrateurs, le daemon n\'existe pas.'
            );
        }
        return explode(
            ',',
            $this->configurationSQL->getConfiguration(
                self::DAEMON_ADMIN_EMAIL,
                $daemon->id_e ?? EntiteSQL::ID_E_ENTITE_RACINE
            )
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function setAdminEmails(int $id_daemon, string $emails): void
    {
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        if ($daemon === null) {
            throw new UnrecoverableException(
                'Impossible de récupérer les emails administrateurs, le daemon n\'existe pas.'
            );
        }
        $this->checkRFC2822Email($emails);
        $this->configurationSQL->setConfiguration(
            self::DAEMON_ADMIN_EMAIL,
            $emails,
            $daemon->id_e ?? EntiteSQL::ID_E_ENTITE_RACINE
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function checkRFC2822Email(string $emails): void
    {
        $emailList = array_filter(array_map('trim', explode(',', $emails)));
        foreach ($emailList as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new UnrecoverableException(
                    \sprintf('Email "%s" invalide : non conforme à la norme RFC 2822.', $email)
                );
            }
        }
    }
}
