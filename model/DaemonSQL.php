<?php

declare(strict_types=1);

class DaemonSQL extends SQL
{
    public const GLOBAL_DAEMON = 1;

    private function mapToDaemon(array $info): Daemon
    {
        return new Daemon($info['id_daemon'], $info['id_e'], $info['state'], $info['nb_workers']);
    }
    public function getNbAllocatedWorkers(): int
    {
        $sql = 'SELECT SUM(nb_workers) FROM daemon where id_daemon != ?';
        return (int)$this->queryOne($sql, self::GLOBAL_DAEMON);
    }

    public function getNbSharedWorkers(): int
    {
        $sql = 'SELECT nb_workers FROM daemon WHERE id_daemon = ?';
        return $this->queryOne($sql, self::GLOBAL_DAEMON);
    }

    public function getDaemon(int $id_daemon): ?Daemon
    {
        $sql = 'SELECT * FROM daemon WHERE id_daemon=?';
        $info = $this->queryOne($sql, $id_daemon);
        if (! $info) {
            return null;
        }

        return $this->mapToDaemon($info);
    }

    public function setDaemonState(int $id_daemon, int $state): void
    {
        $sql = 'UPDATE daemon SET state=? WHERE id_daemon=?';
        $this->query($sql, $state, $id_daemon);
    }

    /**
     * @return Daemon[]
     */
    public function getRunningDaemons(): array
    {
        $sql = 'SELECT * FROM daemon WHERE state = ?';
        $result = [];
        foreach ($this->query($sql, Daemon::STATE_ACTIVE) as $info) {
            $result[] = $this->mapToDaemon($info);
        }
        return $result;
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
        $sql = 'UPDATE daemon SET nb_workers = ? WHERE id_daemon = ?';
        $this->query($sql, [$shared_workers, self::GLOBAL_DAEMON]);
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
        return $this->getDaemon(self::GLOBAL_DAEMON);
    }


    public function insertGlobalDaemon(): bool
    {
        $sql = 'INSERT INTO daemon (id_daemon, id_e, nb_workers) VALUES (?, ?, ?)';
        $this->query($sql, [self::GLOBAL_DAEMON, null, NB_WORKERS]);

        return $this->lastInsertId() !== false;
    }
}
