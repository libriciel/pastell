<?php

class FileToSign
{
    /** @var Fichier $document */
    public $document;

    /** @var Fichier[] $annexes */
    public $annexes = [];

    /**
     * @var Fichier[] $multiDocuments
     */
    public array $multiDocuments = [];

    /** @var string */
    public $type;

    /** @var string */
    public $sousType;

    /** @var string */
    public $circuit;

    /** @var Fichier $circuit_configuration */
    public $circuit_configuration;

    /** @var string */
    public $emailRecipients;

    /** @var string */
    public $emailCc;

    /** @var string */
    public $agents;

    public string $dossierId;

    /** @var Fichier $visualPdf */
    public $visualPdf;

    public $date_limite = false;

    public $xPathPourSignatureXML = false;
    public $annotationPublic = "";
    public $annotationPrivee = "";
    public $emailEmetteur = "";
    public $metadata = [];
    public $dossierTitre = "";
    public $signature_content = "";
    public $signature_type = "";
}
