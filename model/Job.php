<?php

class Job
{
    public const TYPE_DOCUMENT = 1;
    public const TYPE_CONNECTEUR = 2;
    public const TYPE_TRAITEMENT_LOT = 3;

    public const MAX_LAST_MESSAGE_LENGTH = 1024;

    public const WAITING           = 0; // unlock, en attente, non suspendu
    public const LAUNCHING         = 1; // lancement worker
    public const ERROR_DAEMON      = 2; // daemon a détecté un worker mort
    public const ERROR_ACTION      = 3; // UnrecoverableException d'exécution de l'action
    public const SUSPENDED_TRY_MAX = 4; // fréquence nb_try max atteint
    public const SUSPENDED_BY_USER = 5; // suspendu manuellement par un utilisateur
    public const KILLED_BY_USER    = 6; // processus tué manuellement

    public const ETAT_LABEL = [
        self::WAITING           => 'En attente',
        self::LAUNCHING         => 'Lancement du processus',
        self::ERROR_DAEMON      => 'Erreur de lancement du processus',
        self::ERROR_ACTION      => 'Erreur d\'exécution de l\'action',
        self::SUSPENDED_TRY_MAX => 'Maximum d\'essais atteint',
        self::SUSPENDED_BY_USER => 'Suspendu manuellement',
        self::KILLED_BY_USER    => 'Processus tué manuellement',
    ];

    public function getEtatLabel(): string
    {
        return self::ETAT_LABEL[(int) $this->job_status] ?? "État inconnu ({$this->job_status})";
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
    public $lock;
    public string $lock_since;
    public $id_verrou;
    public $job_status;
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
        $this->id_d = "";
        $this->id_e = 0;
        $this->id_ce = 0;

        $this->etat_cible = false;
        $this->id_verrou = "";
        $this->id_daemon = 1;
        $this->next_try = date("Y-m-d H:i:s");
    }

    public function asString()
    {
        if ($this->type == self::TYPE_DOCUMENT) {
            return "id_e: {$this->id_e} - id_d: {$this->id_d} - id_u: {$this->id_u} - source: {$this->etat_source} - cible: {$this->etat_cible}";
        }
        if ($this->type == self::TYPE_CONNECTEUR) {
            return "id_e: {$this->id_e} - id_ce: {$this->id_ce} - id_u: {$this->id_u} - source: {$this->etat_source} - cible: {$this->etat_cible}";
        }
        return false;
    }

    public function isTypeOK()
    {
        return in_array($this->type, [Job::TYPE_CONNECTEUR,Job::TYPE_DOCUMENT,self::TYPE_TRAITEMENT_LOT]);
    }

    public function getLastMessage()
    {
        return substr($this->last_message, 0, self::MAX_LAST_MESSAGE_LENGTH);
    }
}
