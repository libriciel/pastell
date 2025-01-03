<?php

declare(strict_types=1);

use Model\Worker;

class WorkerSQL extends SQL
{
    public function create($pid)
    {
        $sql = "INSERT INTO worker (pid,date_begin) VALUES (?,now())";
        $this->query($sql, $pid);
        return $this->lastInsertId();
    }

    public function getWorker($id_worker): ?Worker
    {
        $sql = 'SELECT * FROM worker WHERE id_worker=?';
        $info = $this->queryOne($sql, $id_worker);
        if (! $info) {
            return null;
        }
        return new Worker(
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

    public function error($id_worker, $message)
    {
        $sql = "UPDATE worker SET message=?,date_end=now(),termine=1 WHERE id_worker=?";
        $this->query($sql, $message, $id_worker);
    }

    public function getRunningWorkerInfo($id_job)
    {
        $sql = "SELECT * FROM worker WHERE id_job=? AND termine=0";
        return $this->queryOne($sql, $id_job);
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

    public function getAllRunningWorker()
    {
        $sql = "SELECT * FROM worker WHERE termine=0";
        return $this->query($sql);
    }

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

    public function getJobListWithWorker($offset = 0, $limit = 20, $filtre = "")
    {
        if (! in_array($filtre, ["lock","actif","wait"])) {
            $filtre = "";
        }

        $sql = "SELECT *, job_queue.id_job as id_job FROM job_queue " .
                " LEFT JOIN worker ON job_queue.id_job = worker.id_job " .
                " WHERE 1=1 ";

        if ($filtre == 'lock') {
            $sql .= " AND is_lock=1 ";
        }
        if ($filtre == 'wait') {
            $sql .= " AND next_try < now() ";
        }
        if ($filtre == 'actif') {
            $sql .= " AND worker.termine=0 ";
        }

        $sql .= " ORDER BY job_queue.is_lock,job_queue.next_try " .
                " LIMIT $offset,$limit " ;
        $result = $this->query($sql);
        foreach ($result as $i => $line) {
            $result[$i]['time_since_next_try'] = time() - strtotime($line['next_try']);
        }
        return $result;
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


    public function getNbJob($filtre)
    {
        $sql = "SELECT count(*) " .
                " FROM job_queue" .
                " LEFT JOIN worker ON job_queue.id_job = worker.id_job " .
                " WHERE 1=1 ";

        if ($filtre == 'lock') {
            $sql .= " AND is_lock=1 ";
        }
        if ($filtre == 'wait') {
            $sql .= " AND next_try < now() ";
        }
        if ($filtre == 'actif') {
            $sql .= " AND worker.termine=0 ";
        }

        return $this->queryOne($sql);
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
