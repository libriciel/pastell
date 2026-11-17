<?php

class WorkerSQL extends SQL
{
    public function create(int $pid): int
    {
        $sql = <<<SQL
INSERT INTO worker (pid, date_begin) VALUES (?, now())
SQL;
        $this->query($sql, $pid);
        return (int)$this->lastInsertId();
    }

    public function getInfo(int $id_worker): array|false
    {
        $sql = <<<SQL
SELECT * FROM worker WHERE id_worker = ?
SQL;
        return $this->queryOne($sql, $id_worker);
    }

    public function error(int $id_worker, string $message): void
    {
        $sql = <<<SQL
UPDATE worker SET message = ?, date_end = now(), termine = 1 WHERE id_worker = ?
SQL;
        $this->query($sql, $message, $id_worker);
    }

    public function getRunningWorkerInfo(int $id_job): array|false
    {
        $sql = <<<SQL
SELECT * FROM worker WHERE id_job = ? AND termine = 0
SQL;
        return $this->queryOne($sql, $id_job);
    }

    public function attachJob(int $id_worker, int $id_job): void
    {
        $sql = <<<SQL
UPDATE worker SET id_job = ? WHERE id_worker = ?
SQL;
        $this->query($sql, $id_job, $id_worker);
    }

    public function success(int $id_worker): void
    {
        $sql = <<<SQL
DELETE FROM worker WHERE id_worker = ?
SQL;
        $this->query($sql, $id_worker);
    }

    public function getAllRunningWorker(): array
    {
        $sql = <<<SQL
SELECT * FROM worker WHERE termine = 0
SQL;
        return $this->query($sql);
    }

    /**
     * @deprecated 4.1.26 Use JobLaunchService::getJobsToLaunch() instead
     */
    public function getJobToLaunch($limit)
    {
        if ($limit <= 0) {
            return [];
        }
        $sql = "SELECT job_queue.id_job,next_try FROM job_queue " .
            " LEFT JOIN worker ON job_queue.id_job=worker.id_job AND worker.termine=0" .
            " WHERE worker.id_worker IS NULL " .
            " AND next_try<=now() " .
            " AND is_lock=0 " .
            " AND id_verrou = '' " .
            " ORDER BY next_try " .
            " LIMIT $limit";
        $job_list = $this->query($sql);
        foreach ($this->getAllVerrou() as $verrou_id) {
            $job_list = array_merge($job_list, $this->getFirstJobToLaunch($verrou_id));
        }

        usort($job_list, function ($a, $b) {
            return strtotime($a['next_try']) - strtotime($b['next_try']);
        });

        $column = array_column($job_list, 'id_job');

        return array_slice($column, 0, $limit);
    }

    /**
     * @deprecated 4.1.26 Use JobLaunchService::getJobsToLaunchByLock() instead
     */
    public function getFirstJobToLaunch($verrou_id)
    {
        $sql = "SELECT count(*) FROM job_queue " .
            " JOIN worker ON worker.id_job=job_queue.id_job " .
            " WHERE termine=0 AND id_verrou = ? ";
        $nb_job_par_verrou_en_cours = $this->queryOne($sql, $verrou_id);

        if ($nb_job_par_verrou_en_cours >= NB_JOB_PAR_VERROU) {
            return [];
        }

        $nb_job_par_verrou = NB_JOB_PAR_VERROU - $nb_job_par_verrou_en_cours;

        $sql = "SELECT job_queue.id_job,next_try FROM job_queue " .
            " LEFT JOIN worker ON job_queue.id_job=worker.id_job AND worker.termine=0" .
            " WHERE worker.id_worker IS NULL " .
            " AND next_try<now() " .
            " AND is_lock=0 " .
            " AND id_verrou = ? " .
            " ORDER BY next_try  " .
            " LIMIT $nb_job_par_verrou ";
        return $this->query($sql, $verrou_id);
    }

    public function getAllVerrou(): array
    {
        $sql = <<<SQL
SELECT DISTINCT id_verrou FROM job_queue WHERE id_verrou != ''
SQL;
        return $this->queryOneCol($sql);
    }

    public function getVerrou(): array
    {
        $sql = <<<SQL
SELECT id_verrou FROM job_queue
JOIN worker ON worker.id_job = job_queue.id_job
WHERE termine = 0 AND id_verrou != ''
SQL;
        return $this->queryOneCol($sql);
    }

    public function getNbActif(): int
    {
        $sql = <<<SQL
SELECT count(*) FROM worker WHERE termine = 0
SQL;
        return (int)$this->queryOne($sql);
    }

    public function getActif(int $offset = 0, int $limit = 20): array
    {
        $sql = <<<SQL
SELECT * FROM worker
LEFT JOIN job_queue ON job_queue.id_job = worker.id_job
WHERE termine = 0
ORDER BY date_begin
LIMIT $offset, $limit
SQL;
        return $this->query($sql);
    }

    public function getJobListWithWorker(int $offset = 0, int $limit = 20, string $filtre = ''): array
    {
        $filter_condition = $this->getJobFilterCondition($filtre);
        $sql = <<<SQL
SELECT *, job_queue.id_job AS id_job FROM job_queue
LEFT JOIN worker ON job_queue.id_job = worker.id_job
WHERE 1 = 1 $filter_condition
ORDER BY job_queue.is_lock, job_queue.next_try
LIMIT $offset, $limit
SQL;
        $job_list = $this->query($sql);
        foreach ($job_list as $i => $job_info) {
            $job_list[$i]['time_since_next_try'] = time() - strtotime($job_info['next_try']);
        }
        return $job_list;
    }

    public function menage(int $id_job): void
    {
        $sql = <<<SQL
DELETE FROM worker WHERE id_job = ? AND termine = 1
SQL;
        $this->query($sql, $id_job);
    }

    public function menageAll(): void
    {
        $sql = <<<SQL
DELETE FROM worker WHERE termine = 1
SQL;
        $this->query($sql);
    }

    public function getNbJob(string $filtre): int
    {
        $filter_condition = $this->getJobFilterCondition($filtre);
        $sql = <<<SQL
SELECT count(*) FROM job_queue
LEFT JOIN worker ON job_queue.id_job = worker.id_job
WHERE 1 = 1 $filter_condition
SQL;
        return (int)$this->queryOne($sql);
    }

    public function getJobListWithWorkerForConnecteur(int $id_ce): array
    {
        $sql = <<<SQL
SELECT *, job_queue.id_job AS id_job FROM job_queue
LEFT JOIN worker ON job_queue.id_job = worker.id_job
WHERE id_ce = ?
SQL;
        return $this->query($sql, $id_ce);
    }

    public function getJobListWithWorkerForDocument(int $id_e, string $id_d): array
    {
        $sql = <<<SQL
SELECT *, job_queue.id_job AS id_job FROM job_queue
LEFT JOIN worker ON job_queue.id_job = worker.id_job
WHERE id_e = ? AND id_d = ?
SQL;
        return $this->query($sql, $id_e, $id_d);
    }

    public function getActionEnCours(int $id_e, string $id_d): int|false
    {
        $sql = <<<SQL
SELECT id_worker FROM job_queue
JOIN worker ON job_queue.id_job = worker.id_job
WHERE id_e = ? AND id_d = ? AND termine = 0
SQL;
        return $this->queryOne($sql, $id_e, $id_d);
    }

    public function getActionEnCoursForConnecteur(int $id_ce, string $action_name): int|false
    {
        $sql = <<<SQL
SELECT id_worker FROM job_queue
JOIN worker ON job_queue.id_job = worker.id_job
WHERE id_ce = ? AND etat_cible = ? AND termine = 0
SQL;
        return $this->queryOne($sql, $id_ce, $action_name);
    }

    public function getUnlockedJobsToLaunch(int $limit): array
    {
        $sql = <<<SQL
SELECT job_queue.id_job, next_try FROM job_queue
LEFT JOIN worker ON job_queue.id_job = worker.id_job AND worker.termine = 0
WHERE worker.id_worker IS NULL
AND next_try <= now()
AND is_lock = 0
AND id_verrou = ''
ORDER BY next_try
LIMIT $limit
SQL;
        return $this->query($sql);
    }

    public function countRunningJobsByLock(string $id_verrou): int
    {
        $sql = <<<SQL
SELECT count(*) FROM job_queue
JOIN worker ON worker.id_job = job_queue.id_job
WHERE termine = 0 AND id_verrou = ?
SQL;
        return (int)$this->queryOne($sql, $id_verrou);
    }

    public function getPendingJobsByLock(string $id_verrou, int $limit): array
    {
        $sql = <<<SQL
SELECT job_queue.id_job, next_try FROM job_queue
LEFT JOIN worker ON job_queue.id_job = worker.id_job AND worker.termine = 0
WHERE worker.id_worker IS NULL
AND next_try < now()
AND is_lock = 0
AND id_verrou = ?
ORDER BY next_try
LIMIT $limit
SQL;
        return $this->query($sql, $id_verrou);
    }

    private function getJobFilterCondition(string $filtre): string
    {
        return match ($filtre) {
            'lock' => 'AND is_lock = 1',
            'wait' => 'AND next_try < now()',
            'actif' => 'AND worker.termine = 0',
            default => '',
        };
    }
}
