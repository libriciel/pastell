<?php

class JobQueueSQL extends SQL
{
    private WorkerSQL $workerSQL;
    private DaemonSQL $daemonSQL;

    public function __construct(
        SQLQuery $sqlQuery,
        WorkerSQL $workerSQL,
        DaemonSQL $daemonSQL,
    ) {
        parent::__construct($sqlQuery);
        $this->workerSQL = $workerSQL;
        $this->daemonSQL = $daemonSQL;
    }
    private function mapToJob(array $info): Job
    {
        $job = new Job();
        $job->id_e = $info['id_e'];
        $job->id_d = $info['id_d'];
        $job->id_u = $info['id_u'];
        $job->id_ce = $info['id_ce'];
        $job->etat_source = $info['etat_source'];
        $job->etat_cible = $info['etat_cible'];
        $job->type = $info['type'];
        $job->last_message = $info['last_message'];
        $job->is_lock = $info['is_lock'];
        $job->lock_since = $info['lock_since'];
        $job->id_verrou = $info['id_verrou'];
        $job->nb_try = $info['nb_try'];
        $job->first_try = $info['first_try'];
        $job->last_try = $info['last_try'];
        $job->next_try = $info['next_try'];
        $job->id_job = $info['id_job'];
        $job->id_daemon = $info['id_daemon'];
        $job->daemon = $this->daemonSQL->getDaemon($job->id_daemon);
        $job->worker = $this->workerSQL->getWorker($job->id_job);
        return $job;
    }

    public function deleteConnecteur($id_ce)
    {
        if ($id_ce == 0) {
            return;
        }
        $sql = "DELETE FROM job_queue WHERE id_ce=?";
        $this->query($sql, $id_ce);
    }

    public function deleteDocument($id_e, $id_d)
    {
        $sql = "DELETE FROM job_queue WHERE id_e=? AND id_d=?";
        $this->query($sql, $id_e, $id_d);
    }

    public function deleteDocumentForAllEntities(string $id_d): void
    {
        $sql = "DELETE FROM job_queue WHERE id_d=?";
        $this->query($sql, $id_d);
    }

    public function deleteJob($id_job)
    {
        $sql = "DELETE FROM job_queue WHERE id_job=?";
        $this->query($sql, $id_job);
    }

    public function getJobIdForConnecteur($id_ce, $etat_source)
    {
        $sql = "SELECT id_job FROM job_queue WHERE id_ce=? AND etat_source=?";
        return $this->queryOne($sql, $id_ce, $etat_source);
    }

    public function getJobIdForDocument($id_e, $id_d)
    {
        $sql = "SELECT id_job FROM job_queue WHERE id_e=? AND id_d=?";
        return $this->queryOne($sql, $id_e, $id_d);
    }

    public function getJobIdForDocumentAndAction(int $id_e, string $id_d, string $action)
    {
        $sql = "SELECT id_job FROM job_queue WHERE id_e=? AND id_d=? AND etat_cible=?";
        return $this->queryOne($sql, $id_e, $id_d, $action);
    }

    /**
     * @param Job $job
     * @return string
     * @throws Exception
     */
    public function createJob(Job $job)
    {
        if (! $job->isTypeOK()) {
            throw new Exception("Type de job non pris en charge");
        }
        $sql = "INSERT INTO job_queue(type,id_e,id_d,id_u,etat_source,etat_cible,id_ce,id_verrou,next_try, id_daemon) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $this->query($sql, $job->type, $job->id_e, $job->id_d, $job->id_u, $job->etat_source, $job->etat_cible, $job->id_ce, $job->id_verrou, $job->next_try, $job->id_daemon);
        $id_job = $this->lastInsertId();
        return $id_job;
    }

    public function updateJob(Job $job)
    {
        $sql = "UPDATE job_queue SET first_try=?,last_try=?,nb_try=?,next_try=?,last_message=?,id_verrou=? WHERE id_job=?" ;
        $this->queryOne($sql, $job->first_try, $job->last_try, $job->nb_try, $job->next_try, $job->getLastMessage(), $job->id_verrou, $job->id_job);
    }

    /**
     * @param $id_job
     * @return Job|null
     */
    public function getJob($id_job)
    {
        $sql = "SELECT * FROM job_queue " .
                " WHERE job_queue.id_job=? ";
        $info =  $this->queryOne($sql, $id_job);
        if (! $info) {
            return null;
        }
        return $this->mapToJob($info);
    }

    public function lock($id_job)
    {
        $sql = "UPDATE job_queue SET is_lock=1,lock_since=now() WHERE id_job=?";
        $this->query($sql, $id_job);
    }

    public function lockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible)
    {
        $sql = "UPDATE job_queue SET is_lock=1,lock_since=now() WHERE id_verrou=? AND etat_source=? AND etat_cible=?";
        $this->query($sql, $id_verrou, $etat_source, $etat_cible);
    }

    public function unlockAll(?int $id_daemon = null): void
    {
        $sql = 'UPDATE job_queue SET is_lock=0';
        $params = [];
        if ($id_daemon !== null) {
            $sql .= ' WHERE id_daemon=?';
            $params[] = $id_daemon;
        }
        $this->query($sql, $params);
    }

    public function unlock($id_job)
    {
        $sql = "UPDATE job_queue SET is_lock=0 WHERE id_job=?";
        $this->query($sql, $id_job);
    }

    public function unlockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible)
    {
        $sql = "UPDATE job_queue SET is_lock=0 WHERE id_verrou=? AND etat_source=? AND etat_cible=?";
        $this->query($sql, $id_verrou, $etat_source, $etat_cible);
    }

    public function getStatInfo()
    {
        $sql = "SELECT count(*) FROM job_queue";
        $info['nb_job'] = $this->queryOne($sql);

        $sql = "SELECT count(*) FROM job_queue WHERE is_lock=1";
        $info['nb_lock'] = $this->queryOne($sql);

        $sql = "SELECT count(*) FROM job_queue " .
            " WHERE next_try<now()";
        $info['nb_wait'] = $this->queryOne($sql);

        $info['nb_lock_one_hour'] = $this->getNbLockSinceOneHour();

        return $info;
    }

    public function getStatInfoForDaemon(int $id_daemon): array
    {
        $sql = 'SELECT count(*) FROM job_queue WHERE id_daemon = ?';
        $info['nb_job'] = $this->queryOne($sql, $id_daemon);
        $sql = 'SELECT count(*) FROM job_queue WHERE is_lock=1 AND id_daemon = ?';
        $info['nb_lock'] = $this->queryOne($sql, $id_daemon);
        $sql = 'SELECT count(*) FROM job_queue WHERE next_try<now() AND id_daemon = ?';
        $info['nb_wait'] = $this->queryOne($sql, $id_daemon);
        $info['nb_lock_one_hour'] = $this->getNbLockSinceOneHourForDaemon($id_daemon);
        return $info;
    }

    public function getNbLockSinceOneHour(): ?int
    {
        $last_hour = date("Y-m-d H:i:s", strtotime("-1 hour"));
        $sql = "SELECT count(*) FROM job_queue WHERE is_lock=1 AND lock_since < ?";
        return $this->queryOne($sql, $last_hour);
    }

    public function getNbLockSinceOneHourForDaemon(int $id_daemon): ?int
    {
        $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $sql = 'SELECT count(*) FROM job_queue WHERE is_lock=1 AND lock_since < ? AND id_daemon = ?';
        return $this->queryOne($sql, [$last_hour, $id_daemon]);
    }

    public function getMaxLastTryOneHourLate(int $id_daemon): ?string
    {
        $last_hour = date('Y-m-d H:i:s', strtotime('-1hour'));
        $sql = 'SELECT MAX(last_try) FROM job_queue WHERE next_try < ? AND nb_try > 0 AND is_lock=0 AND id_daemon = ?';
        return $this->queryOne($sql, [$last_hour, $id_daemon]);
    }

    public function getLateJobs(int $id_daemon): array
    {
        $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $sql = 'SELECT * FROM job_queue WHERE next_try < ? AND nb_try > 0 AND is_lock=0 AND id_daemon = ? ORDER BY next_try DESC';
        $result = $this->query($sql, [$last_hour, $id_daemon]);
        $job_list = [];
        foreach ($result as $job_info) {
            $job_list[] = $this->mapToJob($job_info);
        }
        return $job_list;
    }

    public function getJobLock()
    {
        $sql = "SELECT * FROM job_queue " .
                " WHERE is_lock=1" .
                " ORDER BY lock_since" .
                " LIMIT 20 ";
        return $this->query($sql);
    }

    public function hasDocumentJob($id_e, $id_d)
    {
        $sql = "SELECT count(*) FROM job_queue " .
                " WHERE id_e=? AND id_d=?";
        return boolval($this->queryOne($sql, $id_e, $id_d));
    }


    public function getJobInfo($id_job)
    {
        $sql = "SELECT * FROM job_queue WHERE id_job = ?";
        return $this->queryOne($sql, $id_job);
    }

    public function getCountJobByVerrouAndEtat()
    {
        $sql = "SELECT count(*) as count,sum(is_lock) as nb_lock, id_verrou,etat_source,etat_cible, max(last_try) as last_try, sum(next_try < now()) as nb_late FROM job_queue " .
            " GROUP BY id_verrou,etat_source,etat_cible ORDER BY count DESC,last_try ASC";
        return $this->query($sql);
    }

    public function updateDaemon(int $job_id, int $id_daemon): void
    {
        $sql = 'UPDATE job_queue
            SET id_daemon = ?
            WHERE id_job = ?';
        $this->query($sql, [$id_daemon, $job_id]);
    }

    /**
     * @return Job[]
     */
    public function getJobsByDaemon(int $id_daemon, ?int $limit = null, ?int $offset = null): array
    {
        $sql = 'SELECT * FROM job_queue WHERE id_daemon = ? ORDER BY next_try DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . ($offset !== null ? "$offset, $limit" : "$limit");
        }

        $result = [];
        foreach ($this->query($sql, $id_daemon) as $info) {
            $result[] = $this->mapToJob($info);
        }
        return $result;
    }

    /**
     * @return Job[]
     */
    public function getJobsByAncestor(int $id_e): array
    {
        $sql = 'SELECT *
        FROM job_queue jq
        JOIN entite_ancetre ea ON ea.id_e = jq.id_e
        WHERE id_e_ancetre = ?';
        $results = $this->query($sql, [$id_e]);
        $job_list = [];
        foreach ($results as $job_info) {
            $job_list[] = $this->mapToJob($job_info);
        }
        return $job_list;
    }

    /**
     * @return Job[]
     */
    public function getAllJobs(int $offset = 0, int $limit = 20): array
    {
        $sql = "SELECT *
        FROM job_queue jq
        ORDER BY next_try DESC
        LIMIT $offset, $limit";
        $results = $this->query($sql);
        $job_list = [];
        foreach ($results as $job_info) {
            $job_list[] = $this->mapToJob($job_info);
        }
        return $job_list;
    }

    /**
     * @return Job[]
     */
    public function getFilteredJobList(
        int $limit = 20,
        int $offset = 0,
        string $filtre = '',
        ?int $id_daemon = null
    ): array {
        if (!in_array($filtre, ['lock', 'actif', 'wait'])) {
            $filtre = '';
        }

        $sql = 'SELECT *, job_queue.id_job as id_job 
            FROM job_queue 
            LEFT JOIN worker ON job_queue.id_job = worker.id_job
            WHERE 1=1';

        $params = [];
        if ($id_daemon !== null) {
            $sql .= ' AND job_queue.id_daemon=?';
            $params[] = $id_daemon;
        }

        switch ($filtre) {
            case 'lock':
                $sql .= ' AND job_queue.is_lock=1 ';
                break;
            case 'wait':
                $sql .= ' AND next_try < now() AND job_queue.is_lock=0 ';
                break;
            case 'actif':
                $sql .= ' AND worker.termine=0 ';
                break;
        }

        $sql .= " ORDER BY job_queue.is_lock, job_queue.next_try 
              LIMIT $offset, $limit";

        $result = $this->query($sql, $params);
        $job_list = [];
        foreach ($result as $job_info) {
            $job = $this->mapToJob($job_info);
            $job_list[] = $job;
        }
        return $job_list;
    }

    public function getNbJob($filtre, ?int $id_daemon = null): int
    {
        $sql = <<<SQL
SELECT count(*)
FROM job_queue
LEFT JOIN worker ON job_queue.id_job = worker.id_job
WHERE 1=1 
SQL;

        $params = [];
        if ($id_daemon !== null) {
            $sql .= ' AND job_queue.id_daemon=?';
            $params[] = $id_daemon;
        }
        if ($filtre === 'lock') {
            $sql .= ' AND job_queue.is_lock=1';
        }
        if ($filtre === 'wait') {
            $sql .= ' AND job_queue.next_try < NOW()';
        }
        if ($filtre === 'actif') {
            $sql .= ' AND worker.termine = 0';
        }

        return $this->queryOne($sql, $params);
    }
}
