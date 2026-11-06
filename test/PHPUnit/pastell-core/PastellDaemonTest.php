<?php

declare(strict_types=1);

final class PastellDaemonTest extends PastellTestCase
{
    private const int DEAD_PID = 999999999;

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
        $this->workerSQL->attachJob($id_worker, 12);

        $this->pastellDaemon->cleanDeadOrphanWorkers();

        self::assertNull($this->workerSQL->getWorker($id_worker));
    }

    public function testKeepAliveOrphanWorker(): void
    {
        $id_worker = $this->workerSQL->create(getmypid());
        $this->workerSQL->attachJob($id_worker, 12);

        $this->pastellDaemon->cleanDeadOrphanWorkers();

        self::assertNotNull($this->workerSQL->getWorker($id_worker));
    }
}
