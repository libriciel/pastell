<?php

use Pastell\Configuration\JobStatus;

class Job
{
    public const int TYPE_DOCUMENT = 1;
    public const int TYPE_CONNECTEUR = 2;
    public const int TYPE_TRAITEMENT_LOT = 3;

    public const int MAX_LAST_MESSAGE_LENGTH = 1024;

    public function getEtatLabel(): string
    {
        return $this->job_status->label();
    }

    public $type;
    public $id_e;
    public string $entite_denomination;
    public $id_d;
    public ?string $document_titre;
    public $id_u;
    public int $id_ce;
    public ?string $connecteur_libelle;
    public $etat_source;
    public $etat_cible;
    public $last_message;
    public string $lock_since;
    public $id_verrou;
    public JobStatus $job_status;
    public int $id_daemon;
    public ?Daemon $daemon;

    public $nb_try;
    public $first_try;
    public $last_try;
    public $next_try;

    public $id_job;
    public ?WorkerObject $worker;

    public function __construct()
    {
        $this->id_u = 0;
        $this->id_d = '';
        $this->id_e = 0;
        $this->id_ce = 0;

        $this->etat_cible = false;
        $this->id_verrou = '';
        $this->id_daemon = 1;
        $this->next_try = date('Y-m-d H:i:s');
    }

    public function asString()
    {
        if ($this->type === self::TYPE_DOCUMENT) {
            return "id_e: {$this->id_e} - id_d: {$this->id_d} - id_u: {$this->id_u} - source: {$this->etat_source} - cible: {$this->etat_cible}";
        }
        if ($this->type === self::TYPE_CONNECTEUR) {
            return "id_e: {$this->id_e} - id_ce: {$this->id_ce} - id_u: {$this->id_u} - source: {$this->etat_source} - cible: {$this->etat_cible}";
        }
        return false;
    }

    public function isTypeOK(): bool
    {
        return in_array(
            $this->type,
            [self::TYPE_CONNECTEUR, self::TYPE_DOCUMENT, self::TYPE_TRAITEMENT_LOT],
            true
        );
    }

    public function getLastMessage(): string
    {
        return substr($this->last_message, 0, self::MAX_LAST_MESSAGE_LENGTH);
    }
}
