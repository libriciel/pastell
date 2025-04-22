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

    public function __construct(
        int $id_daemon,
        ?int $id_e = null,
        int $state = self::STATE_INACTIVE,
        int $nb_workers = 0
    ) {
        $this->id_daemon = $id_daemon;
        $this->id_e = $id_e;
        $this->state = $state;
        $this->nb_workers = $nb_workers;
    }
}
