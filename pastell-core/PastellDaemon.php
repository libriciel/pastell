<?php

use Monolog\Logger;
use Pastell\Configuration\JobStatus;
use Symfony\Component\Process\Process;

class PastellDaemon
{
    public function __construct(
        private readonly WorkerSQL $workerSQL,
        private readonly JobQueueSQL $jobQueueSQL,
        private readonly ActionExecutorFactory $actionExecutorFactory,
        private readonly DocumentSQL $document,
        private readonly NotificationMail $notificationMail,
        private readonly Logger $logger,
        private readonly bool $unlock_job_error_at_startup,
        private readonly string $pastell_path,
        private readonly DaemonSQL $daemonSQL,
    ) {
    }

    public function jobMaster(): never
    {
        $this->logger->info('Daemon starting');
        $this->daemonSQL->setDaemonState(DaemonSQL::GLOBAL_DAEMON, DaemonManager::IS_RUNNING);
        // Ajout d'un flag "UNLOK_JOB_ERROR_AT_STARTUP" pour ne pas verrouiller les jobs qui ne se sont pas
        // terminés correctement suite à un arrêt brutal du serveur
        // (ex: restart apache sans avoir arrêté le daemon avec des worker actifs). (r1992)
        if ($this->unlock_job_error_at_startup) {
            foreach ($this->workerSQL->getAllRunningWorker() as $worker) {
                if (! posix_getpgid($worker->pid)) {
                    $this->workerSQL->success($worker->id_worker); // supprime le worker
                    $this->logger->alert('Daemon detects and cleans a worker that did not end correctly', $worker->toArray());
                }
            }
        }

        while (true) {
            foreach ($this->daemonSQL->getRunningDaemons() as $daemon) {
                $this->jobMasterOneRun($daemon);
            }
            pcntl_signal_dispatch();
            sleep(1);
            pcntl_signal_dispatch();
        }
    }

    /**
     * @throws Exception
     */
    private function jobMasterOneRun(Daemon $daemon): void
    {
        foreach ($this->workerSQL->getRunningWorkersForDaemon($daemon->id_daemon) as $worker) {
            if (! posix_getpgid($worker->pid)) {
                if ($worker->termine === 1) {
                    $this->logger->warning("Worker $worker->id_worker has already finished his job, Skipping...", $worker->toArray());
                    continue;
                }
                $this->jobQueueSQL->lock($worker->id_job, JobStatus::ERROR_DAEMON);
                $this->workerSQL->error(
                    $worker->id_worker,
                    "Message du gestionnaire de tâches : ce travail ne s'est pas terminé correctement"
                );
                $this->logger->error("Daemon $daemon->id_daemon detected a dead worker", $worker->toArray());
            }
        }
        $nb_worker_alive = count($this->workerSQL->getRunningWorkersForDaemon($daemon->id_daemon));
        $nb_worker_to_launch = max(0, $daemon->nb_workers - $nb_worker_alive);
        $job_id_list = $this->workerSQL->getJobsToLaunch($nb_worker_to_launch, $daemon->id_daemon);

        foreach ($job_id_list as $id_job) {
            $this->launchWorker($id_job);
        }
    }

    /**
     * @throws Exception
     */
    private function launchWorker($id_job): void
    {
        $job = $this->jobQueueSQL->getJob($id_job);
        if (! $job) {
            return;
        }

        if (! $job->isTypeOK()) {
            throw new Exception("Ce type de travail n'est pas traité par tâche automatique");
        }

        $existingWorker = $this->workerSQL->getRunningWorker($job->id_job);
        if ($existingWorker !== null) {
            throw new RuntimeException("Le travail $job->id_job est déjà attaché à la tâche automatique  #{$existingWorker->id_worker}");
        }

        //Le master lock le job jusqu'à ce que son worker le délock pour éviter que le master ne sélectionne à nouveau
        // ce job (si le lancement du worker est plus lent que la boucle du master)
        $this->jobQueueSQL->lock($job->id_job, JobStatus::LAUNCHING);

        $process = Process::fromShellCommandline(
            \sprintf(
                'nohup %s %s %s > /dev/null 2>&1 &',
                PHP_PATH,
                $this->pastell_path . '/bin/console app:daemon:start-worker',
                $job->id_job
            )
        );
        $process->run();
        $this->logger->info("Daemon starts worker for job #$job->id_job : " . json_encode($job, JSON_THROW_ON_ERROR));
    }

    public function runningWorker(string $jobId): void
    {
        try {
            $this->runningWorkerThrow($jobId);
        } catch (Exception $e) {
            $this->logger->error('Worker ends with an error : ' . $e->getMessage());
            return;
        }
        $this->logger->info(sprintf('Worker %s exits normally', getmypid()));
    }

    /**
     * @throws Exception
     */
    private function runningWorkerThrow(string $jobId): void
    {
        $this->logger->pushProcessor(function ($record) use ($jobId) {
            $record['extra']['id_job'] = $jobId;
            return $record;
        });
        $this->launchJob($jobId);
    }

    /**
     * @throws Exception
     */
    private function launchJob($id_job): void
    {
        $this->logger->info(sprintf('Worker %s looks for job %s', getmypid(), $id_job));
        $job = $this->jobQueueSQL->getJob($id_job);
        if (! $job) {
            throw new Exception("Aucun travail trouvé pour l'id_job $id_job");
        }

        if (! $job->isTypeOK()) {
            throw new Exception("Ce type de travail n'est pas traité par tâche automatique");
        }

        $this->logger->pushProcessor(function ($record) use ($job) {
            $record['extra']['id_verrou'] = $job->id_verrou;
            return $record;
        });

        $workerSQL = $this->workerSQL;

        $existingWorker = $workerSQL->getRunningWorker((int) $id_job);
        if ($existingWorker !== null) {
            throw new RuntimeException("Le travail $id_job est déjà attaché à la tâche automatique  #{$existingWorker->id_worker}");
        }

        $pid = getmypid();
        $id_worker = $workerSQL->create($pid);
        $workerSQL->attachJob($id_worker, $id_job);
        $this->jobQueueSQL->unlock($id_job);

        if ($job->type === Job::TYPE_DOCUMENT) {
            $this->actionExecutorFactory->executeOnDocument(
                $job->id_e,
                $job->id_u,
                $job->id_d,
                $job->etat_cible,
                [],
                true,
                [],
                $id_worker
            );
        } elseif ($job->type === Job::TYPE_TRAITEMENT_LOT) {
            $result = $this->actionExecutorFactory->executeOnDocument(
                $job->id_e,
                $job->id_u,
                $job->id_d,
                $job->etat_cible,
                [],
                true,
                [],
                $id_worker
            );
            if (!$result) {
                $info = $this->document->getInfo($job->id_d);
                $message = "Echec de l'execution de l'action dans la cadre d'un traitement par lot : " .
                    $this->actionExecutorFactory->getLastMessage();
                $this->logger->error($message . ' ' . $job->asString());
                $this->notificationMail->notify($job->id_e, $job->id_d, $job->etat_cible, $info['type'], $message);
            }
        } elseif ($job->type === Job::TYPE_CONNECTEUR) {
            $this->actionExecutorFactory->executeOnConnecteur(
                $job->id_ce,
                $job->id_u,
                $job->etat_cible,
                true,
                [],
                $id_worker
            );
        } else {
            throw new Exception("Type de travail {$job->type} inconnu");
        }

        $workerSQL->success($id_worker);
    }

    public function stop(): never
    {
        $this->logger->info('SIGTERM caught !');
        while (true) {
            $nb_running = count($this->workerSQL->getAllRunningWorker());
            if ($nb_running === 0) {
                $this->logger->info('No more running worker : exited');
                exit(0);
            }
            $this->logger->info("$nb_running running workers left, wait 5s...");
            sleep(5);
        }
    }
}
