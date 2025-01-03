<?php

declare(strict_types=1);

class DaemonSQL extends SQL
{
    public function getNbAllocatedWorkers(): int
    {
        $sql = 'SELECT SUM(nb_workers) FROM daemon where id_daemon != 1';
        return (int)$this->queryOne($sql);
    }

    public function getNbSharedWorkers(): int
    {
        $sql = 'SELECT nb_workers FROM daemon WHERE id_daemon = 1';
        return (int)$this->queryOne($sql);
    }

    public function getDaemon(int $id_daemon): ?Daemon
    {
        $sql = 'SELECT * FROM daemon WHERE id_daemon=?';
        $info = $this->queryOne($sql, $id_daemon);
        if (! $info) {
            return null;
        }

        return new Daemon($info['id_daemon'], $info['id_e'], $info['state'], $info['nb_workers']);
    }

    public function setDaemonState(int $id_daemon, int $state): void
    {
        $sql = 'UPDATE daemon SET state=? WHERE id_daemon=?';
        $this->query($sql, $state, $id_daemon);
    }

    public function getRunningDaemons(): array
    {
        $sql = 'SELECT * FROM daemon WHERE state = 1';
        return $this->query($sql);
    }

    public function insertDaemon(int $id_e): int
    {
        $sql = 'INSERT INTO daemon(id_e) VALUES (?);';
        $this->query($sql, [$id_e]);
        return (int) $this->lastInsertId();
    }

    public function allocateWorkers(int $id_daemon, $nb_allocated_workers): void
    {
        $sql = 'UPDATE daemon SET nb_workers=? WHERE id_daemon=?';
        $this->query($sql, [$nb_allocated_workers, $id_daemon]);
    }

    public function refreshAvailableWorkers(): void
    {
        $nb_allocated_workers = $this->getNbAllocatedWorkers();
        $shared_workers = NB_WORKERS - $nb_allocated_workers;
        $sql = 'UPDATE daemon SET nb_workers = ? WHERE id_daemon = 1';
        $this->query($sql, $shared_workers);
    }

    public function deleteDaemon(int $id_daemon): void
    {
        $sql = 'DELETE FROM daemon WHERE id_daemon=?';
        $this->query($sql, $id_daemon);
    }

    public function getAllDaemonsInfo(): array
    {
        $sql = 'SELECT * FROM daemon d JOIN entite e ON d.id_e = e.id_e ';
        return $this->query($sql);
    }

    public function getGlobalDaemon(): ?Daemon
    {
        return $this->getDaemon(1);
    }


    public function insertGlobalDaemon(): bool
    {
        $sql = 'INSERT INTO daemon (id_daemon, id_e, nb_workers) VALUES (1, ?, ?)';
        $this->query($sql, [null, NB_WORKERS]);

        return $this->lastInsertId() !== false;
    }
}
