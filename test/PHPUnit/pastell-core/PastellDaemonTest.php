<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

final class PastellDaemonTest extends PastellTestCase
{
    private const int DEAD_PID = 999999999;
    private const int ID_JOB = 12;

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
        $id_worker = $this->workerSQL->create(self::DEAD_PID);
        $this->workerSQL->attachJob($id_worker, self::ID_JOB);

        $this->pastellDaemon->cleanDeadOrphanWorkers();

        self::assertNull($this->workerSQL->getWorker($id_worker));
    }

    public function testCleanRecycledPidWorker(): void
    {
        $id_worker = $this->workerSQL->create(getmypid());
        $this->workerSQL->attachJob($id_worker, self::ID_JOB);

        $this->pastellDaemon->cleanDeadOrphanWorkers();

        self::assertNull($this->workerSQL->getWorker($id_worker));
    }

    public function testKeepAliveOrphanWorker(): void
    {
        $process = new Process(
            [PHP_BINARY, '-r', 'sleep(60);', 'app:daemon:start-worker', (string)self::ID_JOB]
        );
        $process->start();
        try {
            $pid = $process->getPid();
            $process->waitUntil(
                static fn (): bool => str_starts_with((string)file_get_contents("/proc/$pid/cmdline"), PHP_BINARY . "\0")
            );
            $id_worker = $this->workerSQL->create($pid);
            $this->workerSQL->attachJob($id_worker, self::ID_JOB);

            $this->pastellDaemon->cleanDeadOrphanWorkers();

            self::assertNotNull($this->workerSQL->getWorker($id_worker));
        } finally {
            $process->stop(0);
        }
    }
}
