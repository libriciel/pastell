<?php

declare(strict_types=1);

namespace Pastell\Service\Daemon;

use WorkerSQL;

final class JobLaunchService
{
    public function __construct(
        private readonly WorkerSQL $workerSQL,
        private readonly int $nb_job_par_verrou,
    ) {
    }

    public function getJobsToLaunch(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $job_list = $this->workerSQL->getUnlockedJobsToLaunch($limit);
        foreach ($this->workerSQL->getAllVerrou() as $id_verrou) {
            foreach ($this->getJobsToLaunchByLock($id_verrou) as $job_info) {
                $job_list[] = $job_info;
            }
        }

        usort($job_list, $this->compareNextTry(...));
        $id_job_list = array_column($job_list, 'id_job');

        return array_slice($id_job_list, 0, $limit);
    }

    public function getJobsToLaunchByLock(string $id_verrou): array
    {
        $nb_running_jobs = $this->workerSQL->countRunningJobsByLock($id_verrou);
        $nb_available_slots = $this->nb_job_par_verrou - $nb_running_jobs;
        if ($nb_available_slots <= 0) {
            return [];
        }

        return $this->workerSQL->getPendingJobsByLock($id_verrou, $nb_available_slots);
    }

    private function compareNextTry(array $job_info_a, array $job_info_b): int
    {
        return strtotime($job_info_a['next_try']) <=> strtotime($job_info_b['next_try']);
    }
}
