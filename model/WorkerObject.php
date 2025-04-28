<?php

declare(strict_types=1);

class WorkerObject
{
    public int $id_worker;
    public int $pid;
    public string $date_begin;
    public int $id_job;
    public string $date_end;
    public string $message;
    public int $termine;
    public int $success;

    public function __construct(
        int $id_worker,
        int $pid,
        string $date_begin,
        int $id_job,
        string $date_end,
        string $message,
        int $termine,
        int $success
    ) {
        $this->id_worker = $id_worker;
        $this->pid = $pid;
        $this->date_begin = $date_begin;
        $this->id_job = $id_job;
        $this->date_end = $date_end;
        $this->message = $message;
        $this->termine = $termine;
        $this->success = $success;
    }

    public function toArray(): array
    {
        return [
            'id_worker' => $this->id_worker,
            'pid' => $this->pid,
            'date_begin' => $this->date_begin,
            'id_job' => $this->id_job,
            'date_end' => $this->date_end,
            'message' => $this->message,
            'termine' => $this->termine,
            'success' => $this->success,
        ];
    }
}
