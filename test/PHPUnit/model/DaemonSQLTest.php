<?php

declare(strict_types=1);

class DaemonSQLTest extends PastellTestCase
{
    private DaemonSQL $daemonSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $this->daemonSQL->setNbWorkers((int)NB_WORKERS);
        $this->daemonSQL->insertGlobalDaemon('admin@email.com');
    }

    public function testGetDaemon(): void
    {
        $id_e = 1;
        $id_daemon = $this->daemonSQL->insertDaemon($id_e, 0, 'mail@libriciel.invalid', 5);
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        static::assertSame($id_e, $daemon->id_e);
    }

    public function testSetDaemonState(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1, 0, 'mail@libriciel.invalid', 5);
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        static::assertSame(Daemon::STATE_INACTIVE, $daemon->state);
        $this->daemonSQL->setDaemonState($id_daemon, Daemon::STATE_ACTIVE);
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        static::assertSame(Daemon::STATE_ACTIVE, $daemon->state);
    }

    public function testAllocateWorkers(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1, 42, 'mail@libriciel.invalid', 5);
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        static::assertSame(42, $daemon->nb_workers);
    }

    public function testRefreshAvailableWorkers(): void
    {
        $this->daemonSQL->insertDaemon(1, 4, 'mail@libriciel.invalid', 5);
        $this->daemonSQL->refreshAvailableWorkers();
        $globalDaemon = $this->daemonSQL->getGlobalDaemon();
        static::assertSame(1, $globalDaemon->nb_workers);
    }

    public function testDeleteDaemon(): void
    {
        $this->daemonSQL->deleteDaemon(5);
        static::assertNull($this->daemonSQL->getDaemon(5));
    }

    public function testGetAllRunningDaemons(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1, 0, 'mail@libriciel.invalid', 5);
        $this->daemonSQL->setDaemonState($id_daemon, Daemon::STATE_ACTIVE);
        $id_daemon = $this->daemonSQL->insertDaemon(2, 0, 'mail@libriciel.invalid', 5);
        $this->daemonSQL->setDaemonState($id_daemon, Daemon::STATE_INACTIVE);
        $daemons = $this->daemonSQL->getRunningDaemons();
        static::assertCount(1, $daemons);
    }

    public function testGetNbSharedWorkers(): void
    {
        $this->daemonSQL->insertDaemon(1, 4, 'mail@libriciel.invalid', 5);
        $this->daemonSQL->refreshAvailableWorkers();
        static::assertSame(
            $this->daemonSQL->getNbWorkers() - 4,
            $this->daemonSQL->getNbSharedWorkers()
        );
    }

    public function testGetAllocatedWorkers(): void
    {
        $this->daemonSQL->insertDaemon(1, 10, 'mail@libriciel.invalid', 5);
        $this->daemonSQL->insertDaemon(2, 5, 'mail@libriciel.invalid', 5);
        static::assertSame(15, $this->daemonSQL->getNbAllocatedWorkers());
    }

    public function testGetClosestDaemonDefault(): void
    {
        $closestDaemon = $this->daemonSQL->getClosestDaemon(1);
        static::assertSame(DaemonSQL::GLOBAL_DAEMON, $closestDaemon);
    }

    public function testGetClosestDaemon(): void
    {
        $id_close_daemon = $this->daemonSQL->insertDaemon(1, 0, 'mail@libriciel.invalid', 5);
        $closestDaemon = $this->daemonSQL->getClosestDaemon(1);
        static::assertSame($id_close_daemon, $closestDaemon);
    }

    public function testGetNbWorkers(): void
    {
        static::assertSame((int)NB_WORKERS, $this->daemonSQL->getNbWorkers());
    }

    public function testSetNbWorkers(): void
    {
        $this->daemonSQL->setNbWorkers(10);
        static::assertSame(10, $this->daemonSQL->getNbWorkers());
    }

    public function testGetAllDaemons(): void
    {
        $this->daemonSQL->insertDaemon(1, 0, 'mail@libriciel.invalid', 5);
        $this->daemonSQL->insertDaemon(2, 0, 'mail@libriciel.invalid', 5);
        $allDaemons = $this->daemonSQL->getAllDaemons();
        static::assertGreaterThanOrEqual(2, count($allDaemons));
    }
}
