<?php

declare(strict_types=1);

class WorkerSQL extends SQL
{
    private function mapToWorkerObject(array $info): WorkerObject
    {
        return new WorkerObject(
            $info['id_worker'],
            $info['pid'],
            $info['date_begin'],
            $info['id_job'],
            $info['date_end'],
            $info['message'],
            $info['termine'],
            $info['success']
        );
    }

    public function create($pid)
    {
        $sql = "INSERT INTO worker (pid,date_begin) VALUES (?,now())";
        $this->query($sql, $pid);
        return $this->lastInsertId();
    }

    public function getWorker($id_worker): ?WorkerObject
    {
        $sql = 'SELECT * FROM worker WHERE id_worker=?';
        $info = $this->queryOne($sql, $id_worker);
        if (! $info) {
            return null;
        }
        return $this->mapToWorkerObject($info);
    }

    public function error($id_worker, $message)
    {
        $sql = "UPDATE worker SET message=?,date_end=now(),termine=1 WHERE id_worker=?";
        $this->query($sql, $message, $id_worker);
    }

    public function getRunningWorker(int $id_job): ?WorkerObject
    {
        $sql = 'SELECT * FROM worker WHERE id_job=? AND termine=0';
        $info = $this->queryOne($sql, $id_job);
        if (!$info) {
            return null;
        }
        return $this->mapToWorkerObject($info);
    }

    public function attachJob($id_worker, $id_job)
    {
        $sql = "UPDATE worker SET id_job=? WHERE id_worker=?";
        $this->query($sql, $id_job, $id_worker);
    }

    public function success($id_worker)
    {
        $sql = "DELETE FROM worker WHERE id_worker=?";
        $this->query($sql, $id_worker);
    }

    /**
     * @return WorkerObject[]
     */
    public function getAllRunningWorker(): array
    {
        $sql = 'SELECT * FROM worker WHERE termine=0';
        $result = [];
        foreach ($this->query($sql) as $info) {
            $result[] = $this->mapToWorkerObject($info);
        }
        return $result;
    }

    /**
     * @return WorkerObject[]
     */
    public function getRunningWorkersForDaemon(int $id_daemon): array
    {
        $sql = 'SELECT * FROM worker
         JOIN job_queue jq ON worker.id_job=jq.id_job
         WHERE termine=0 AND jq.id_daemon=?';
        $result = [];
        foreach ($this->query($sql, $id_daemon) as $info) {
            $result[] = $this->mapToWorkerObject($info);
        }
        return $result;
    }

    public function getJobsToLaunch(int $limit, int $id_daemon): array
    {
        $sql = "SELECT jq.id_job,next_try FROM job_queue jq
            LEFT JOIN worker ON jq.id_job=worker.id_job AND worker.termine=0
            WHERE worker.id_worker IS NULL
            AND next_try<=now()
            AND is_lock=0
            AND id_verrou = ''
            AND jq.id_daemon = ?
            ORDER BY next_try
            LIMIT $limit";
        $job_list = $this->query($sql, $id_daemon);
        foreach ($this->getAllVerrou() as $verrou_id) {
            foreach ($this->getJobsToLaunchByLock($verrou_id, $id_daemon) as $job) {
                $job_list[] = $job;
            }
        }

        usort($job_list, static fn($a, $b) => strtotime($a['next_try']) - strtotime($b['next_try']));
        return array_slice(array_column($job_list, 'id_job'), 0, $limit);
    }


    public function getJobsToLaunchByLock(string $verrou_id, int $id_daemon): array
    {
        $sql = 'SELECT count(*) FROM job_queue jq
            JOIN worker ON worker.id_job=jq.id_job
            WHERE termine=0
            AND id_verrou = ?
            AND jq.id_daemon = ?';
        $nb_job_par_verrou_en_cours = $this->queryOne($sql, $verrou_id, $id_daemon);
        if ($nb_job_par_verrou_en_cours >= NB_JOB_PAR_VERROU) {
            return [];
        }
        $nb_job_par_verrou = NB_JOB_PAR_VERROU - $nb_job_par_verrou_en_cours;
        $sql = "SELECT jq.id_job,next_try FROM job_queue jq
            LEFT JOIN worker ON jq.id_job=worker.id_job AND worker.termine=0
            WHERE worker.id_worker IS NULL
            AND next_try<now()
            AND is_lock=0
            AND id_verrou = ?
            AND jq.id_daemon = ?
            ORDER BY next_try
            LIMIT $nb_job_par_verrou";
        return $this->query($sql, $verrou_id, $id_daemon);
    }

    public function getAllVerrou()
    {
        $sql = "SELECT DISTINCT id_verrou FROM job_queue WHERE id_verrou != ''";
        return $this->queryOneCol($sql);
    }

    public function getVerrou()
    {
        $sql = "SELECT id_verrou FROM job_queue " .
            " JOIN worker ON worker.id_job=job_queue.id_job " .
            " WHERE termine=0 AND id_verrou != ''";
        return $this->queryOneCol($sql);
    }

    public function getNbActif()
    {
        $sql = "SELECT count(*) FROM worker WHERE termine=0";
        return $this->queryOne($sql);
    }

    public function getNbActifForDaemon(int $id_daemon)
    {
        $sql = 'SELECT count(*) 
            FROM worker w 
            JOIN job_queue jq ON jq.id_job = w.id_job 
            WHERE termine=0 AND jq.id_daemon = ?';
        return $this->queryOne($sql, $id_daemon);
    }

    public function getActif($offset = 0, $limit = 20)
    {
        $offset = intval($offset);
        $limit = intval($limit);
        $sql = "SELECT * FROM worker" .
                " LEFT JOIN job_queue ON job_queue.id_job = worker.id_job " .
                " WHERE termine=0 " .
                " ORDER BY date_begin" .
                " LIMIT $offset,$limit";
        return $this->query($sql);
    }

    public function menage($id_job)
    {
        $sql = "DELETE FROM worker WHERE id_job=? AND termine=1";
        $this->query($sql, $id_job);
    }

    public function menageAll()
    {
        $sql = "DELETE FROM worker WHERE termine=1";
        $this->query($sql);
    }

    public function getJobListWithWorkerForConnecteur($id_ce)
    {
        $sql = "SELECT *, job_queue.id_job as id_job FROM job_queue " .
                " LEFT JOIN worker ON job_queue.id_job = worker.id_job " .
                " WHERE id_ce=? ";
        return $this->query($sql, $id_ce);
    }

    public function getJobListWithWorkerForDocument($id_e, $id_d)
    {
        $sql = "SELECT *, job_queue.id_job as id_job FROM job_queue " .
                " LEFT JOIN worker ON job_queue.id_job = worker.id_job " .
                " WHERE id_e=? AND id_d=?";
        return $this->query($sql, $id_e, $id_d);
    }

    public function getActionEnCours($id_e, $id_d)
    {
        $sql = "SELECT id_worker FROM job_queue " .
                " JOIN worker ON job_queue.id_job = worker.id_job " .
                " WHERE id_e=? AND id_d=? AND termine=0";
        return $this->queryOne($sql, $id_e, $id_d);
    }

    public function getActionEnCoursForConnecteur($id_ce, $action_name)
    {
        $sql = "SELECT id_worker FROM job_queue " .
            " JOIN worker ON job_queue.id_job = worker.id_job " .
            " WHERE id_ce=? AND etat_cible =? AND termine=0";
        return $this->queryOne($sql, $id_ce, $action_name);
    }
}
