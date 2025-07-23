<?php

use Monolog\Logger;

abstract class Connecteur
{
    protected string $lastError = '';
    private DonneesFormulaire $docDonneesFormulaire;
    private array $connecteurInfo = [];
    private Logger $logger;
    private string $dataDir;

    abstract public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire);

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Retourne les données du flux en cours de traitement.
     * Le connecteur ne doit accéder qu'aux seuls attributs à sa portée :
     * - attributs publics : déclarés dans le flux
     * - attributs privés : déclarés par le connecteur lui-même
     * Il ne doit pas accéder aux attributs déclarés par d'autres connecteurs.
     */
    public function getDocDonneesFormulaire(): DonneesFormulaire
    {
        return $this->docDonneesFormulaire;
    }

    public function setDocDonneesFormulaire(DonneesFormulaire $docDonneesFormulaire): void
    {
        $this->docDonneesFormulaire = $docDonneesFormulaire;
    }

    public function hasDocDonneesFormulaire(): bool
    {
        return !empty($this->docDonneesFormulaire);
    }

    /**
     * @return array information sur le connecteur (id_ce, id_e,...)
     */
    public function getConnecteurInfo(): array
    {
        return $this->connecteurInfo;
    }

    public function setConnecteurInfo(array $connecteur_info): void
    {
        $this->connecteurInfo = $connecteur_info;
    }

    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function setDataDir(string $dataDir): void
    {
        $this->dataDir = $dataDir;
    }
}
