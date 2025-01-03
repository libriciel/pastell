<?php

class JobQueueSQL extends SQL
{
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
        return $job;
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

    public function unlockAll()
    {
        $sql = "UPDATE job_queue SET is_lock=0";
        $this->query($sql);
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

    public function getNbLockSinceOneHour(): ?int
    {
        $last_hour = date("Y-m-d H:i:s", strtotime("-1 hour"));
        $sql = "SELECT count(*) FROM job_queue WHERE is_lock=1 AND lock_since < ?";
        return $this->queryOne($sql, $last_hour);
    }

    public function getMaxLastTryOneHourLate(): ?string
    {
        $last_hour = date("Y-m-d H:i:s", strtotime("-1hour"));
        $sql = "SELECT MAX(last_try) FROM job_queue WHERE next_try < ? AND nb_try > 0 AND is_lock=0";
        return $this->queryOne($sql, $last_hour);
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
}
