<?php

declare(strict_types=1);

class Daemon
{
    public const STATE_INACTIVE = 0;
    public const STATE_ACTIVE = 1;

    public int $id_daemon;
    public ?int $id_e;
    public int $state;
    public int $nb_workers;
    public string $admin_emails;
    public int $late_jobs_treshold;

    public function __construct(
        int $id_daemon,
        ?int $id_e = null,
        int $state = self::STATE_INACTIVE,
        int $nb_workers = 0,
        string $admin_email = '',
        int $late_jobs_treshold = 1
    ) {
        $this->id_daemon = $id_daemon;
        $this->id_e = $id_e;
        $this->state = $state;
        $this->nb_workers = $nb_workers;
        $this->admin_emails = $admin_email;
        $this->late_jobs_treshold = $late_jobs_treshold;
    }

    public function toArray(): array
    {
        return [
            'id_daemon' => $this->id_daemon,
            'id_e' => $this->id_e,
            'state' => $this->state,
            'nb_workers' => $this->nb_workers,
        ];
    }
}
