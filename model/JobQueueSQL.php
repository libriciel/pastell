<?php

use Pastell\Configuration\JobStatus;
use Pastell\Model\Daemon\JobAdvancedFilters;
use Pastell\Model\Daemon\JobSort;

class JobQueueSQL extends SQL
{
    /** Cible l'absence de file (id_verrou vide) dans la recherche avancée de jobs */
    public const string VERROU_NONE = '__none__';

    public function __construct(
        SQLQuery $sqlQuery,
        private readonly WorkerSQL $workerSQL,
        private readonly DaemonSQL $daemonSQL,
        private readonly DocumentSQL $documentSQL,
        private readonly EntiteSQL $entiteSQL,
        private readonly ConnecteurEntiteSQL $connecteurSQL
    ) {
        parent::__construct($sqlQuery);
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
        $job->job_status = JobStatus::from($info['job_status']);
        $job->lock_since = $info['lock_since'];
        $job->id_verrou = $info['id_verrou'];
        $job->nb_try = $info['nb_try'];
        $job->first_try = $info['first_try'];
        $job->last_try = $info['last_try'];
        $job->next_try = $info['next_try'];
        $job->id_job = $info['id_job'];
        $job->id_daemon = $info['id_daemon'];
        $job->daemon = $this->daemonSQL->getDaemon($job->id_daemon);
        $job->worker = $this->workerSQL->getRunningWorker($job->id_job);
        $job->entite_denomination = $this->entiteSQL->getDenomination($job->id_e);
        if ($job->type === Job::TYPE_DOCUMENT) {
            $document_info = $this->documentSQL->getInfo($job->id_d);
            if ($document_info) {
                $job->document_titre = $document_info['titre'];
            }
        } elseif ($job->type === Job::TYPE_CONNECTEUR) {
            $connecteur_info = $this->connecteurSQL->getInfo($job->id_ce);
            if ($connecteur_info) {
                $job->connecteur_libelle = $connecteur_info['libelle'];
            }
        }
        return $job;
    }

    private function mapResultToJobList(array $result): array
    {
        $job_list = [];
        foreach ($result as $job_info) {
            $job_list[] = $this->mapToJob($job_info);
        }
        return $job_list;
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

    public function deleteJob($id_job): void
    {
        $sql = <<<SQL
DELETE FROM worker WHERE id_job=?;
SQL;
        $this->query($sql, $id_job);

        $sql = <<<SQL
DELETE FROM job_queue WHERE id_job=?;
SQL;
        $this->query($sql, $id_job);
    }

    public function getJobIdForConnecteur($id_ce, $etat_source)
    {
        $sql = "SELECT id_job FROM job_queue WHERE id_ce=? AND etat_source=?";
        return $this->queryOne($sql, $id_ce, $etat_source);
    }

    public function getJobsForConnector(int $id_ce): array
    {
        $sql = <<<SQL
SELECT * FROM job_queue
WHERE id_ce=?;
SQL;
        $result = $this->query($sql, $id_ce);
        return $this->mapResultToJobList($result);
    }

    public function getJobIdForDocument($id_e, $id_d)
    {
        $sql = "SELECT id_job FROM job_queue WHERE id_e=? AND id_d=?";
        return $this->queryOne($sql, $id_e, $id_d);
    }

    public function getJobsForDocument(string $id_d): array
    {
        $sql = <<<SQL
SELECT * FROM job_queue
WHERE id_d=?;
SQL;
        $result = $this->query($sql, $id_d);
        return $this->mapResultToJobList($result);
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

    public function lock(int $id_job, JobStatus $state): void
    {
        $sql = <<<SQL
UPDATE job_queue
SET job_status = ?, lock_since = ?
WHERE id_job = ?
SQL;
        $this->query($sql, $state->value, $this->getNow(), $id_job);
    }

    public function lockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible)
    {
        $sql = "UPDATE job_queue SET job_status=?,lock_since=? WHERE id_verrou=? AND etat_source=? AND etat_cible=?";
        $this->query($sql, JobStatus::SUSPENDED_BY_USER->value, $this->getNow(), $id_verrou, $etat_source, $etat_cible);
    }

    public function unlockAll(?int $id_daemon = null): void
    {
        $sql = 'UPDATE job_queue SET job_status=0';
        $params = [];
        if ($id_daemon !== null) {
            $sql .= ' WHERE id_daemon=?';
            $params[] = $id_daemon;
        }
        $this->query($sql, $params);
    }

    public function unlock($id_job)
    {
        $sql = "UPDATE job_queue SET job_status=0 WHERE id_job=?";
        $this->query($sql, $id_job);
    }

    public function unlockByVerrouAndEtat($id_verrou, $etat_source, $etat_cible)
    {
        $sql = "UPDATE job_queue SET job_status=0 WHERE id_verrou=? AND etat_source=? AND etat_cible=?";
        $this->query($sql, $id_verrou, $etat_source, $etat_cible);
    }

    public function getStatInfo()
    {
        $sql = <<<SQL
SELECT count(*) FROM job_queue
SQL;
        $info['nb_job'] = $this->queryOne($sql);

        $sql = <<<SQL
SELECT count(*) FROM job_queue WHERE job_status != 0
SQL;
        $info['nb_lock'] = $this->queryOne($sql);

        $sql = <<<SQL
SELECT count(*) 
FROM job_queue
WHERE next_try < ?
AND job_status = 0
SQL;
        $info['nb_wait'] = $this->queryOne($sql, $this->getNow());

        $info['nb_lock_one_hour'] = $this->getNbLockSinceOneHour();

        return $info;
    }

    public function getStatInfoForDaemon(int $id_daemon): array
    {
        $sql = <<<SQL
SELECT count(*) 
FROM job_queue 
WHERE id_daemon = ?
SQL;
        $info['nb_job'] = $this->queryOne($sql, $id_daemon);

        $sql = <<<SQL
SELECT count(*)
FROM job_queue
WHERE job_status != 0
AND id_daemon = ?
SQL;
        $info['nb_lock'] = $this->queryOne($sql, $id_daemon);

        $sql = <<<SQL
SELECT count(*) 
FROM job_queue 
WHERE next_try < ?
AND job_status = 0
AND id_daemon = ?
SQL;
        $info['nb_wait'] = $this->queryOne($sql, $this->getNow(), $id_daemon);
        $info['nb_lock_one_hour'] = $this->getNbLockSinceOneHourForDaemon($id_daemon);
        return $info;
    }

    public function getNbLockSinceOneHour(): ?int
    {
        $last_hour = date("Y-m-d H:i:s", strtotime("-1 hour"));
        $sql = "SELECT count(*) FROM job_queue WHERE job_status != 0 AND lock_since < ?";
        return $this->queryOne($sql, $last_hour);
    }

    public function getNbLockSinceOneHourForDaemon(int $id_daemon): ?int
    {
        $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $sql = 'SELECT count(*) FROM job_queue WHERE job_status != 0 AND lock_since < ? AND id_daemon = ?';
        return $this->queryOne($sql, [$last_hour, $id_daemon]);
    }

    public function getMaxLastTryOneHourLate(int $id_daemon): ?string
    {
        $last_hour = date('Y-m-d H:i:s', strtotime('-1hour'));
        $sql = 'SELECT MAX(last_try) FROM job_queue WHERE next_try < ? AND nb_try > 0 AND job_status=0 AND id_daemon = ?';
        return $this->queryOne($sql, [$last_hour, $id_daemon]);
    }

    public function getLateJobs(int $id_daemon): array
    {
        $last_hour = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $sql = 'SELECT * FROM job_queue WHERE next_try < ? AND nb_try > 0 AND job_status=0 AND id_daemon = ? ORDER BY next_try DESC';
        $result = $this->query($sql, [$last_hour, $id_daemon]);
        return $this->mapResultToJobList($result);
    }

    public function getJobLock()
    {
        $sql = "SELECT * FROM job_queue " .
                " WHERE job_status != 0" .
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

    public function getCountJobByVerrouAndEtat()
    {
        $sql = "SELECT count(*) as count,sum(job_status != 0) as nb_lock, id_verrou,etat_source,etat_cible, max(last_try) as last_try, sum(next_try < ?) as nb_late FROM job_queue " .
            " GROUP BY id_verrou,etat_source,etat_cible ORDER BY count DESC,last_try ASC";
        return $this->query($sql, $this->getNow());
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

        $result = $this->query($sql, $id_daemon);
        return $this->mapResultToJobList($result);
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
        return $this->mapResultToJobList($results);
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
        return $this->mapResultToJobList($results);
    }

    /**
     * @return Job[]
     */
    public function getFilteredJobList(
        int $limit = 20,
        int $offset = 0,
        string $filtre = '',
        ?int $id_daemon = null,
        ?JobAdvancedFilters $advancedFilters = null,
        ?JobSort $sort = null
    ): array {
        $sql = 'SELECT *, job_queue.id_job as id_job
            FROM job_queue
            LEFT JOIN worker ON job_queue.id_job = worker.id_job
            WHERE 1=1';

        $params = [];
        if ($id_daemon !== null) {
            $sql .= ' AND job_queue.id_daemon=?';
            $params[] = $id_daemon;
        }

        if ($filtre === 'actif') {
            $sql .= ' AND worker.termine = 0';
        }

        $this->appendAdvancedFilters($advancedFilters, $sql, $params);

        $sql .= $this->buildOrderByClause($sort);
        $sql .= " LIMIT $offset, $limit";

        $result = $this->query($sql, $params);
        return $this->mapResultToJobList($result);
    }

    private function buildOrderByClause(?JobSort $sort): string
    {
        if ($sort === null) {
            return ' ORDER BY job_queue.job_status, job_queue.next_try';
        }

        $direction = $sort->direction->sql();
        return " ORDER BY {$sort->column->sqlColumn()} $direction, job_queue.id_job $direction";
    }

    public function getNbJob($filtre, ?int $id_daemon = null, ?JobAdvancedFilters $advancedFilters = null): int
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
        if ($filtre === 'actif') {
            $sql .= ' AND worker.termine = 0';
        }

        $this->appendAdvancedFilters($advancedFilters, $sql, $params);

        return $this->queryOne($sql, $params);
    }

    /**
     * @param array<int,mixed> $params
     */
    private function appendAdvancedFilters(?JobAdvancedFilters $filters, string &$sql, array &$params): void
    {
        if ($filters === null) {
            return;
        }

        $typeList = array_filter($filters->type, static fn ($v) => $v !== '');
        if ($typeList !== []) {
            $placeholders = implode(', ', array_fill(0, count($typeList), '?'));
            $sql .= " AND job_queue.type IN ($placeholders)";
            foreach ($typeList as $type) {
                $params[] = (int)$type;
            }
        }

        $statusList = array_filter($filters->job_status, static fn ($v) => $v !== '');
        if ($statusList !== []) {
            $placeholders = implode(', ', array_fill(0, count($statusList), '?'));
            $sql .= " AND job_queue.job_status IN ($placeholders)";
            foreach ($statusList as $status) {
                $params[] = (int)$status;
            }
        }

        if ($filters->late !== '') {
            $sql .= ' AND job_queue.next_try < ?';
            $params[] = $this->getNow();
        }

        if ($filters->id_e !== '') {
            if ($filters->include_children !== '') {
                $sql .= ' AND job_queue.id_e IN'
                    . ' (SELECT id_e FROM entite_ancetre WHERE id_e_ancetre = ?)';
            } else {
                $sql .= ' AND job_queue.id_e = ?';
            }
            $params[] = (int)$filters->id_e;
        }

        $verrouList = array_filter($filters->id_verrou, static fn ($v) => $v !== '');
        if ($verrouList !== []) {
            $conditions = [];
            $namedVerrou = [];
            $includeNone = false;
            foreach ($verrouList as $verrou) {
                if ($verrou === self::VERROU_NONE) {
                    $includeNone = true;
                } else {
                    $namedVerrou[] = $verrou;
                }
            }
            if ($namedVerrou !== []) {
                $placeholders = implode(', ', array_fill(0, count($namedVerrou), '?'));
                $conditions[] = "job_queue.id_verrou IN ($placeholders)";
                foreach ($namedVerrou as $verrou) {
                    $params[] = $verrou;
                }
            }
            if ($includeNone) {
                $conditions[] = "job_queue.id_verrou = ''";
            }
            $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
        }
    }
}
