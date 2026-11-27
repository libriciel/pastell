<?php

declare(strict_types=1);

namespace Pastell\Service\Daemon;

use WorkerSQL;

final class JobConsultationService
{
    public function __construct(
        private readonly WorkerSQL $workerSQL,
    ) {
    }

    public function getJobsForDocument(int $id_e, string $id_d): array
    {
        return $this->workerSQL->getJobListWithWorkerForDocument($id_e, $id_d);
    }
}
