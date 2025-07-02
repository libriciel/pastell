<?php

declare(strict_types=1);

class DaemonManagerTest extends PastellTestCase
{
    private DaemonManager $daemonManager;
    private DaemonSQL $daemonSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->daemonSQL = $this->getObjectInstancier()->getInstance(DaemonSQL::class);
        $this->daemonSQL->setNbWorkers(10);
        $this->daemonSQL->insertGlobalDaemon();
        $this->daemonManager = $this->getObjectInstancier()->getInstance(DaemonManager::class);
    }

    public function testStatus(): void
    {
        $this->daemonManager->stop();
        static::assertSame(DaemonManager::IS_STOPPED, $this->daemonManager->status());
    }

    public function testStartDaemonThrowsIfGlobal(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->daemonManager->startDaemon(DaemonSQL::GLOBAL_DAEMON);
    }

    public function testStartDaemonThrowsIfNotFound(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->daemonManager->startDaemon(9999);
    }

    public function testStopDaemonThrowsIfGlobal(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->daemonManager->stopDaemon(DaemonSQL::GLOBAL_DAEMON);
    }

    public function testStopDaemonThrowsIfNotFound(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->daemonManager->stopDaemon(9999);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testStartAndStopDaemon(): void
    {
        $daemon = $this->daemonManager->addDaemon(2, 1);

        $this->daemonManager->startDaemon($daemon->id_daemon);
        $startedDaemon = $this->daemonSQL->getDaemonByEntity(2);
        static::assertSame(DaemonManager::IS_RUNNING, $startedDaemon->state);

        $this->daemonManager->stopDaemon($daemon->id_daemon);
        $stoppedDaemon = $this->daemonSQL->getDaemonByEntity(2);
        static::assertSame(DaemonManager::IS_STOPPED, $stoppedDaemon->state);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testAddDaemonSuccess(): void
    {
        $entityId = 10;
        $daemon = $this->daemonManager->addDaemon($entityId, 1);
        static::assertNotNull($this->daemonSQL->getDaemon($daemon->id_daemon));
        static::assertSame($entityId, $daemon->id_e);
        static::assertSame(10 - 1, $this->daemonSQL->getNbSharedWorkers());
    }

    /**
     * @throws UnrecoverableException
     */
    public function testAddDaemonThrowsIfAlreadyExists(): void
    {
        $entityId = 11;
        $this->daemonManager->addDaemon($entityId, 1);

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Création impossible, un daemon existe déjà pour cette entité.');

        $this->daemonManager->addDaemon($entityId, 1);
    }

    public function testAddDaemonThrowsIfNotEnoughWorkers(): void
    {
        $entityId = 12;
        $nbFree = $this->daemonSQL->getNbSharedWorkers();

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Création impossible, pas assez de workers disponibles.');

        $this->daemonManager->addDaemon($entityId, $nbFree + 1);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testRemoveDaemonSuccess(): void
    {
        $entityId = 13;
        $daemon = $this->daemonManager->addDaemon($entityId, 2);

        static::assertNotNull($this->daemonSQL->getDaemon($daemon->id_daemon));

        $this->daemonManager->removeDaemon($daemon->id_daemon);

        static::assertNull($this->daemonSQL->getDaemon($daemon->id_daemon));
    }

    public function testRemoveDaemonThrowsIfGlobal(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Impossible de supprimer le gestionnaire de tâches global.');

        $this->daemonManager->removeDaemon(DaemonSQL::GLOBAL_DAEMON);
    }

    public function testRemoveDaemonThrowsIfNotFound(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Suppression impossible, le gestionnaire de tâches n'existe pas.");

        $this->daemonManager->removeDaemon(9999);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testAllocateWorkers(): void
    {
        $daemon = $this->daemonManager->addDaemon(1, 5);
        $this->daemonManager->allocateWorkers($daemon->id_daemon, 3);
        $updatedDaemon = $this->daemonSQL->getDaemon($daemon->id_daemon);
        $globalDaemon = $this->daemonSQL->getGlobalDaemon();
        static::assertSame(3, $updatedDaemon->nb_workers);
        static::assertSame(10 - 3, $globalDaemon->nb_workers);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testDaemonAdminMail(): void
    {
        $daemon = $this->daemonManager->addDaemon(1, 5);
        $this->daemonManager->setAdminEmails($daemon->id_daemon, 'admin@example.com,root@example.com');
        $result = $this->daemonManager->getAdminEmails($daemon->id_daemon);

        static::assertSame(['admin@example.com', 'root@example.com'], $result);
    }

    public function testGetAdminMailsThrowsIfDaemonNotFound(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Impossible de récupérer les emails administrateurs, le daemon n'existe pas.");
        $this->daemonManager->getAdminEmails(9999);
    }

    public function testSetAdminMailsThrowsIfDaemonNotFound(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Impossible de récupérer les emails administrateurs, le daemon n'existe pas.");
        $this->daemonManager->setAdminEmails(9999, 'admin@example.com');
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetAdminMailsReturnsEmptyArrayIfEmptyString(): void
    {
        $daemon = $this->daemonManager->addDaemon(2, 5);

        $this->daemonManager->setAdminEmails($daemon->id_daemon, '');
        $result = $this->daemonManager->getAdminEmails($daemon->id_daemon);

        static::assertSame([''], $result);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetAdminMailsHandlesSpaces(): void
    {
        $daemon = $this->daemonManager->addDaemon(3, 5);

        $this->daemonManager->setAdminEmails($daemon->id_daemon, ' admin@example.com , root@example.com ');
        $result = $this->daemonManager->getAdminEmails($daemon->id_daemon);

        $trimmed = array_map('trim', $result);
        static::assertSame(['admin@example.com', 'root@example.com'], $trimmed);
    }
}
