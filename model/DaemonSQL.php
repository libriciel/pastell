<?php

declare(strict_types=1);

class DaemonSQL extends SQL
{
    public const UNASSIGNED_DAEMON = 0;
    public const GLOBAL_DAEMON = 1;
    public const DISPLAY_LIMIT = 20;

    private ConfigurationSQL $configurationSQL;

    public function __construct(
        SQLQuery $sqlQuery,
        ConfigurationSQL $configurationSQL
    ) {
        parent::__construct($sqlQuery);
        $this->configurationSQL = $configurationSQL;
    }

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
        if (!$info) {
            return null;
        }
        return $this->mapToDaemon($info);
    }

    public function getDaemonByEntity(int $id_e): ?Daemon
    {
        $sql = 'SELECT * FROM daemon WHERE id_e=?';
        $info = $this->queryOne($sql, $id_e);
        if (!$info) {
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
        $sql = 'SELECT * FROM daemon d LEFT JOIN entite e ON d.id_e = e.id_e WHERE (is_active = ? OR d.id_e IS NULL) AND state = ?';
        $result = [];
        foreach ($this->query($sql, [Daemon::STATE_ACTIVE, EntiteSQL::STATE_ACTIVE]) as $info) {
            $result[] = $this->mapToDaemon($info);
        }
        return $result;
    }

    public function getAllDaemons(): array
    {
        $sql = 'SELECT * FROM daemon d LEFT JOIN entite e ON d.id_e = e.id_e WHERE is_active = ? OR d.id_e IS NULL';
        $result = [];
        foreach ($this->query($sql, [EntiteSQL::STATE_ACTIVE]) as $info) {
            $result[] = $this->mapToDaemon($info);
        }
        return $result;
    }

    public function insertDaemon(int $id_e): int
    {
        $sql = 'INSERT INTO daemon(id_e) VALUES (?);';
        $this->query($sql, [$id_e]);
        return (int)$this->lastInsertId();
    }

    public function allocateWorkers(int $id_daemon, $nb_allocated_workers): void
    {
        $sql = 'UPDATE daemon SET nb_workers=? WHERE id_daemon=?';
        $this->query($sql, [$nb_allocated_workers, $id_daemon]);
    }

    public function refreshAvailableWorkers(): void
    {
        $shared_workers = $this->getNbWorkers() - $this->getNbAllocatedWorkers();
        $sql = 'UPDATE daemon SET nb_workers = ? WHERE id_daemon = ?';
        $this->query($sql, [$shared_workers, self::GLOBAL_DAEMON]);
    }

    public function deleteDaemon(int $id_daemon): void
    {
        $sql = 'DELETE FROM daemon WHERE id_daemon=?';
        $this->query($sql, $id_daemon);
    }

    public function getGlobalDaemon(): ?Daemon
    {
        return $this->getDaemon(self::GLOBAL_DAEMON);
    }

    public function insertGlobalDaemon(): bool
    {
        $sql = 'INSERT INTO daemon (id_daemon, id_e, nb_workers) VALUES (?, ?, ?)';
        $this->query(
            $sql,
            [self::GLOBAL_DAEMON, null, $this->getNbWorkers()]
        );
        return $this->lastInsertId() !== false;
    }

    public function getClosestDaemon(int $id_e): int
    {
        $sql = 'SELECT d.id_daemon
            FROM entite_ancetre ea
            JOIN daemon d ON d.id_e = ea.id_e_ancetre
            WHERE ea.id_e = ?
            ORDER BY ea.niveau
            LIMIT 1';
        return $this->queryOne($sql, [$id_e]) ?: self::GLOBAL_DAEMON;
    }

    public function getNbWorkers(): int
    {
        return (int)$this->configurationSQL->getConfiguration(DaemonManager::NB_WORKERS, ConfigurationSQL::NULL_ID_E);
    }

    public function setNbWorkers(int $nb_workers): void
    {
        $this->configurationSQL->setConfiguration(
            DaemonManager::NB_WORKERS,
            (string)$nb_workers,
            ConfigurationSQL::NULL_ID_E
        );
        $this->refreshAvailableWorkers();
    }

    public function getAllEntiteInfo(int $offset, string $search): array
    {
        $sql = "SELECT *, e.id_e as id_e
            FROM daemon d
            JOIN entite e ON d.id_e = e.id_e
            WHERE is_active = 1
              AND denomination LIKE ?
            ORDER BY state DESC
            LIMIT $offset," . self::DISPLAY_LIMIT;
        return $this->query($sql, ["%$search%"]);
    }
}
