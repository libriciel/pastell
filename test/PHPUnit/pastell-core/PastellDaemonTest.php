<?php

declare(strict_types=1);

use Pastell\Command\Daemon\StartWorker;
use Symfony\Component\Process\Process;

final class PastellDaemonTest extends PastellTestCase
{
    private const int DEAD_PID = 999999999;
    private const int ID_JOB = 12;
    private const int PROCESS_START_TIMEOUT_IN_SECONDS = 5;

    private WorkerSQL $workerSQL;
    private PastellDaemon $pastellDaemon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workerSQL = $this->getObjectInstancier()->getInstance(WorkerSQL::class);
        $this->pastellDaemon = $this->getObjectInstancier()->getInstance(PastellDaemon::class);
    }

    public function testCleanDeadOrphanWorker(): void
    {
        $id_worker = $this->workerSQL->create(self::DEAD_PID, self::ID_JOB);

        $this->pastellDaemon->cleanDeadOrphanWorkers();

        self::assertNull($this->workerSQL->getWorker($id_worker));
    }

    public function testCleanRecycledPidWorker(): void
    {
        $id_worker = $this->workerSQL->create(getmypid(), self::ID_JOB);

        $this->pastellDaemon->cleanDeadOrphanWorkers();

        self::assertNull($this->workerSQL->getWorker($id_worker));
    }

    public function testCleanFinishedOrphanWorker(): void
    {
        $id_worker = $this->workerSQL->create(self::DEAD_PID, self::ID_JOB);
        $this->workerSQL->error($id_worker, 'error');

        $this->pastellDaemon->cleanDeadOrphanWorkers();

        self::assertNull($this->workerSQL->getWorker($id_worker));
    }

    public function testKeepAliveOrphanWorker(): void
    {
        self::assertTrue($this->isKeptAfterCleaning([(string)self::ID_JOB]));
    }

    public function testKeepWorkerWithOptions(): void
    {
        self::assertTrue($this->isKeptAfterCleaning(['--env=prod', (string)self::ID_JOB, '-vvv']));
    }

    public function testCleanOtherJobWorker(): void
    {
        self::assertFalse($this->isKeptAfterCleaning([(string)(self::ID_JOB + 1)]));
    }

    public function testKeepStartingWorker(): void
    {
        $jobQueueSQL = $this->getObjectInstancier()->getInstance(JobQueueSQL::class);
        $job = new Job();
        $job->type = Job::TYPE_CONNECTEUR;
        $job->id_ce = 1;
        $job->etat_source = 'source';
        $job->etat_cible = 'target';
        $id_job = (int)$jobQueueSQL->createJob($job);
        $id_worker = $this->workerSQL->create(self::DEAD_PID, $id_job);

        $this->pastellDaemon->cleanDeadOrphanWorkers();

        self::assertNotNull($this->workerSQL->getWorker($id_worker));
    }

    private function isPhpStarted(int $pid): bool
    {
        $command_line = (string)@file_get_contents("/proc/$pid/cmdline");
        return str_starts_with($command_line, PHP_BINARY . "\0");
    }

    /**
     * @param string[] $worker_arguments
     */
    private function isKeptAfterCleaning(array $worker_arguments): bool
    {
        $command = [PHP_BINARY, '-r', 'sleep(60);', '--', StartWorker::NAME, ...$worker_arguments];
        $process = new Process($command);
        $process->start();
        try {
            $pid = (int)$process->getPid();
            $deadline = microtime(true) + self::PROCESS_START_TIMEOUT_IN_SECONDS;
            while (! $this->isPhpStarted($pid)) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    self::fail('The fake worker process did not start');
                }
                usleep(1000);
            }
            $id_worker = $this->workerSQL->create($pid, self::ID_JOB);

            $this->pastellDaemon->cleanDeadOrphanWorkers();

            return $this->workerSQL->getWorker($id_worker) !== null;
        } finally {
            $process->stop(0);
        }
    }
}
