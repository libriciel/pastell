<?php

declare(strict_types=1);

class JournalEntry
{
    public int $id_j;
    public int $type;
    public int $id_e;
    public int $id_u;
    public string $id_d;
    public string $action;
    public string $message;
    public string $date;
    public string $preuve;
    public string $date_horodatage;
    public string $message_horodate;
    public string $document_type;

    public function __construct(
        int $id_j,
        int $type,
        int $id_e,
        int $id_u,
        string $id_d,
        string $action,
        string $message,
        string $date,
        string $preuve,
        string $date_horodatage,
        string $message_horodate,
        string $document_type
    ) {
        $this->id_j = $id_j;
        $this->type = $type;
        $this->id_e = $id_e;
        $this->id_u = $id_u;
        $this->id_d = $id_d;
        $this->action = $action;
        $this->message = $message;
        $this->date = $date;
        $this->preuve = $preuve;
        $this->date_horodatage = $date_horodatage;
        $this->message_horodate = $message_horodate;
        $this->document_type = $document_type;
    }
}
