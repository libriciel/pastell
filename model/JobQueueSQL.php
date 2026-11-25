<?php

class JobQueueSQL extends SQL
{
    private function mapToJob(array $job_info): Job
    {
        $job = new Job();
        $job->id_e = $job_info['id_e'];
        $job->id_d = $job_info['id_d'];
        $job->id_u = $job_info['id_u'];
        $job->id_ce = $job_info['id_ce'];
        $job->etat_source = $job_info['etat_source'];
        $job->etat_cible = $job_info['etat_cible'];
        $job->type = $job_info['type'];
        $job->last_message = $job_info['last_message'];
        $job->is_lock = $job_info['is_lock'];
        $job->id_verrou = $job_info['id_verrou'];
        $job->nb_try = $job_info['nb_try'];
        $job->first_try = $job_info['first_try'];
        $job->last_try = $job_info['last_try'];
        $job->next_try = $job_info['next_try'];
        $job->id_job = $job_info['id_job'];
        return $job;
    }

    public function deleteConnecteur($id_ce)
    {
        if ($id_ce == 0) {
            return;
        }
        $sql = <<<SQL
DELETE FROM job_queue WHERE id_ce = ?
SQL;
        $this->query($sql, $id_ce);
    }

    public function deleteDocument($id_e, $id_d)
    {
        $sql = <<<SQL
DELETE FROM job_queue WHERE id_e = ? AND id_d = ?
SQL;
        $this->query($sql, $id_e, $id_d);
    }

    public function deleteDocumentForAllEntities(string $id_d): void
    {
        $sql = <<<SQL
DELETE FROM job_queue WHERE id_d = ?
SQL;
        $this->query($sql, $id_d);
    }

    public function deleteJob($id_job): void
    {
        $sql = <<<SQL
DELETE FROM worker WHERE id_job = ?
SQL;
        $this->query($sql, $id_job);

        $sql = <<<SQL
DELETE FROM job_queue WHERE id_job = ?
SQL;
        $this->query($sql, $id_job);
    }

    public function getJobIdForConnecteur($id_ce, $etat_source)
    {
        $sql = <<<SQL
SELECT id_job FROM job_queue WHERE id_ce = ? AND etat_source = ?
SQL;
        return $this->queryOne($sql, $id_ce, $etat_source);
    }

    public function getJobIdForDocument($id_e, $id_d)
    {
        $sql = <<<SQL
SELECT id_job FROM job_queue WHERE id_e = ? AND id_d = ?
SQL;
        return $this->queryOne($sql, $id_e, $id_d);
    }

    public function getJobIdForDocumentAndAction(int $id_e, string $id_d, string $action)
    {
        $sql = <<<SQL
SELECT id_job FROM job_queue WHERE id_e = ? AND id_d = ? AND etat_cible = ?
SQL;
        return $this->queryOne($sql, $id_e, $id_d, $action);
    }

    /**
     * @param Job $job
     * @return string
     * @throws Exception
     * @deprecated 4.1.26 Use JobCreationService::create() instead
     */
    public function createJob(Job $job)
    {
        if (! $job->isTypeOK()) {
            throw new Exception("Type de job non pris en charge");
        }

        $sql = "INSERT INTO job_queue(type,id_e,id_d,id_u,etat_source,etat_cible,id_ce,id_verrou,next_try) VALUES (?,?,?,?,?,?,?,?,?)";
        $this->query($sql, $job->type, $job->id_e, $job->id_d, $job->id_u, $job->etat_source, $job->etat_cible, $job->id_ce, $job->id_verrou, $job->next_try);

        $id_job = $this->lastInsertId();
        return $id_job;
    }

    public function insert(Job $job): int
    {
        $sql = <<<SQL
INSERT INTO job_queue (type, id_e, id_d, id_u, etat_source, etat_cible, id_ce, id_verrou, next_try)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
        $this->query(
            $sql,
            $job->type,
            $job->id_e,
            $job->id_d,
            $job->id_u,
            $job->etat_source,
            $job->etat_cible,
            $job->id_ce,
            $job->id_verrou,
            $job->next_try
        );
        return (int)$this->lastInsertId();
    }

    public function updateJob(Job $job)
    {
        $sql = <<<SQL
UPDATE job_queue
SET first_try = ?, last_try = ?, nb_try = ?, next_try = ?, last_message = ?, id_verrou = ?
WHERE id_job = ?
SQL;
        $this->query(
            $sql,
            $job->first_try,
            $job->last_try,
            $job->nb_try,
            $job->next_try,
            $job->getLastMessage(),
            $job->id_verrou,
            $job->id_job
        );
    }

    /**
     * @return Job|null
     */
    public function getJob($id_job)
    {
        $sql = <<<SQL
SELECT * FROM job_queue WHERE id_job = ?
SQL;
        $job_info = $this->queryOne($sql, $id_job);
        if (! $job_info) {
            return null;
        }
        return $this->mapToJob($job_info);
    }

    public function lock($id_job)
    {
        $sql = <<<SQL
UPDATE job_queue SET is_lock = 1, lock_since = now() WHERE id_job = ?
SQL;
        $this->query($sql, $id_job);
    }

    public function lockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible)
    {
        $sql = <<<SQL
UPDATE job_queue SET is_lock = 1, lock_since = now() WHERE id_verrou = ? AND etat_source = ? AND etat_cible = ?
SQL;
        $this->query($sql, $id_verrou, $etat_source, $etat_cible);
    }

    public function unlockAll()
    {
        $sql = <<<SQL
UPDATE job_queue SET is_lock = 0
SQL;
        $this->query($sql);
    }

    public function unlock($id_job)
    {
        $sql = <<<SQL
UPDATE job_queue SET is_lock = 0 WHERE id_job = ?
SQL;
        $this->query($sql, $id_job);
    }

    public function unlockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible)
    {
        $sql = <<<SQL
UPDATE job_queue SET is_lock = 0 WHERE id_verrou = ? AND etat_source = ? AND etat_cible = ?
SQL;
        $this->query($sql, $id_verrou, $etat_source, $etat_cible);
    }

    public function getStatInfo()
    {
        $sql = <<<SQL
SELECT count(*) FROM job_queue
SQL;
        $stat_info['nb_job'] = $this->queryOne($sql);

        $sql = <<<SQL
SELECT count(*) FROM job_queue WHERE is_lock = 1
SQL;
        $stat_info['nb_lock'] = $this->queryOne($sql);

        $sql = <<<SQL
SELECT count(*) FROM job_queue WHERE next_try < now()
SQL;
        $stat_info['nb_wait'] = $this->queryOne($sql);

        $stat_info['nb_lock_one_hour'] = $this->getNbLockSinceOneHour();

        return $stat_info;
    }

    private function getOneHourBefore(string $date): string
    {
        return date(Date::DATE_ISO, strtotime($date . ' -1 hour'));
    }

    public function getNbLockSinceOneHour(): ?int
    {
        $sql = <<<SQL
SELECT count(*) FROM job_queue WHERE is_lock = 1 AND lock_since < ?
SQL;
        return $this->queryOne($sql, $this->getOneHourBefore(date(Date::DATE_ISO)));
    }

    public function getMaxLastTryOneHourLate(): ?string
    {
        $sql = <<<SQL
SELECT MAX(last_try) FROM job_queue WHERE next_try < ? AND nb_try > 0 AND is_lock = 0
SQL;
        return $this->queryOne($sql, $this->getOneHourBefore(date(Date::DATE_ISO)));
    }

    /**
     * @deprecated 4.1.26 no longer used, to be deleted in 6.0.0
     */
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
        $sql = <<<SQL
SELECT count(*) FROM job_queue WHERE id_e = ? AND id_d = ?
SQL;
        return (bool)$this->queryOne($sql, $id_e, $id_d);
    }

    public function getJobInfo($id_job)
    {
        $sql = <<<SQL
SELECT * FROM job_queue WHERE id_job = ?
SQL;
        return $this->queryOne($sql, $id_job);
    }

    public function getCountJobByVerrouAndEtat()
    {
        $sql = <<<SQL
SELECT count(*) AS count, sum(is_lock) AS nb_lock, id_verrou, etat_source, etat_cible,
       max(last_try) AS last_try, sum(next_try < now()) AS nb_late
FROM job_queue
GROUP BY id_verrou, etat_source, etat_cible
ORDER BY count DESC, last_try ASC
SQL;
        return $this->query($sql);
    }
}
