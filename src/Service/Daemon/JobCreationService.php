<?php

declare(strict_types=1);

namespace Pastell\Service\Daemon;

use Job;
use JobQueueSQL;
use UnrecoverableException;

final class JobCreationService
{
    public function __construct(
        private readonly JobQueueSQL $jobQueueSQL,
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function create(Job $job): int
    {
        if (!$job->isTypeOK()) {
            throw new UnrecoverableException('Type de job non pris en charge');
        }
        return $this->jobQueueSQL->insert($job);
    }
}
