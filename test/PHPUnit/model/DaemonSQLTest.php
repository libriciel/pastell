<?php

declare(strict_types=1);

class DaemonSQLTest extends PastellTestCase
{
    private DaemonSQL $daemonSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $this->daemonSQL->checkConfig();
        $this->daemonSQL->insertGlobalDaemon();
    }

    public function testCreate(): void
    {
        static::assertNotNull($this->daemonSQL->insertDaemon(1));
    }

    public function testGetDaemon(): void
    {
        $id_e = 1;
        $id_daemon = $this->daemonSQL->insertDaemon($id_e);
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        static::assertSame($id_e, $daemon->id_e);
    }

    public function testSetDaemonState(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1);
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        static::assertSame(Daemon::STATE_INACTIVE, $daemon->state);
        $this->daemonSQL->setDaemonState($id_daemon, Daemon::STATE_ACTIVE);
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        static::assertSame(Daemon::STATE_ACTIVE, $daemon->state);
    }

    public function testAllocateWorkers(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1);
        $this->daemonSQL->allocateWorkers($id_daemon, 42);
        $daemon = $this->daemonSQL->getDaemon($id_daemon);
        static::assertSame(42, $daemon->nb_workers);
    }

    public function testRefreshAvailableWorkers(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1);
        $this->daemonSQL->allocateWorkers($id_daemon, 4);
        $this->daemonSQL->refreshAvailableWorkers();
        $globalDaemon = $this->daemonSQL->getGlobalDaemon();
        static::assertSame(1, $globalDaemon->nb_workers);
    }

    public function testDeleteDaemon(): void
    {
        static::assertNotNull($this->daemonSQL->insertDaemon(5));
        $this->daemonSQL->deleteDaemon(5);
        static::assertNull($this->daemonSQL->getDaemon(5));
    }

    public function testGetAllRunningDaemons(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1);
        $this->daemonSQL->setDaemonState($id_daemon, Daemon::STATE_ACTIVE);
        $id_daemon = $this->daemonSQL->insertDaemon(2);
        $this->daemonSQL->setDaemonState($id_daemon, Daemon::STATE_INACTIVE);
        $daemons = $this->daemonSQL->getRunningDaemons();
        static::assertCount(1, $daemons);
    }

    public function testGetNbSharedWorkers(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1);
        $this->daemonSQL->allocateWorkers($id_daemon, 4);
        $this->daemonSQL->refreshAvailableWorkers();
        static::assertEquals($this->daemonSQL->getNbTotalWorkers() - 4, $this->daemonSQL->getNbSharedWorkers());
    }

    public function testGetAllocatedWorkers(): void
    {
        $id_daemon = $this->daemonSQL->insertDaemon(1);
        $this->daemonSQL->allocateWorkers($id_daemon, 10);
        $id_daemon = $this->daemonSQL->insertDaemon(2);
        $this->daemonSQL->allocateWorkers($id_daemon, 5);
        static::assertSame(15, $this->daemonSQL->getNbAllocatedWorkers());
    }

    public function testGetClosestDaemonDefault(): void
    {
        $closestDaemon = $this->daemonSQL->getClosestDaemon(1);
        static::assertSame(DaemonSQL::GLOBAL_DAEMON, $closestDaemon);
    }

    public function testGetClosestDaemon(): void
    {
        $id_close_daemon = $this->daemonSQL->insertDaemon(1);
        $closestDaemon = $this->daemonSQL->getClosestDaemon(1);
        static::assertSame($id_close_daemon, $closestDaemon);
    }

    public function testSetNbWorkers(): void
    {
        $this->daemonSQL->setNbWorkers(10);
        static::assertEquals(10, $this->daemonSQL->getNbTotalWorkers());
    }
}
