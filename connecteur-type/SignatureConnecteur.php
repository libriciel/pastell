<?php

abstract class SignatureConnecteur extends Connecteur
{
    public const PARAPHEUR_NB_JOUR_MAX_DEFAULT = 30;

    abstract public function getNbJourMaxInConnecteur();

    abstract public function getSousType();

    /**
     * @throws SignatureException
     */
    abstract public function sendDossier(FileToSign $dossier);

    abstract public function getSignature($dossierID);

    abstract public function getAllHistoriqueInfo($dossierID);

    /**
     * @param $history - output of SignatureConnecteur::getAllHistoriqueInfo()
     */
    abstract public function getLastHistorique($history): string;

    /**
     * workaround because IparapheurRestConnector::getLastHistorique() returns the ongoing state
     */
    public function getLastCompletedHistorique($history): string
    {
        return $this->getLastHistorique($history);
    }

    abstract public function getRefusalMessage($dossierID);

    /**
     * @param $history - output of SignatureConnecteur::getAllHistoriqueInfo()
     */
    abstract public function getDateSignature(stdClass|array $history): string;

    abstract public function effacerDossierRejete($dossierID);

    abstract public function exercerDroitRemordDossier($dossierID);

    public function isFastSignature()
    {
        return false;
    }

    public function setSendingMetadata(DonneesFormulaire $donneesFormulaire)
    {
        /*Nothing to do*/
    }

    public function archiver($dossierID)
    {
        return true;
    }

    /**
     * @param $info - output of SignatureConnecteur::getSignature()
     */
    public function getOutputAnnexe($info, int $ignore_count)
    {
        return [];
    }

    /**
     * @param $lastHistorique - output of SignatureConnecteur::getLastHistorique()
     */
    abstract public function isFinalState(string $lastHistorique): bool;

    /**
     * @param $lastHistorique - output of SignatureConnecteur::getLastHistorique()
     */
    abstract public function isRejected(string $lastHistorique): bool;

    /**
     * @param $info - output of SignatureConnecteur::getSignature()
     */
    abstract public function isDetached($info): bool;

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $info - output of SignatureConnecteur::getSignature()
     * @return mixed
     */
    abstract public function getDetachedSignature($info);

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     */
    abstract public function getSignedFile($info_from_get_signature): Fichier;

    /**
     * Workaround because it is embedded in IParapheur::getSignature()
     *
     * @param $info - output of SignatureConnecteur::getSignature()
     * @param string $documentId
     * @return Fichier|null
     */
    abstract public function getBordereauFromSignature($info, string $documentId = ''): ?Fichier;

    /**
     * @param $info - output of SignatureConnecteur::getSignature()
     */
    abstract public function getMetadataSortie($info): ?Fichier;

    /**
     * @param $info - output of SignatureConnecteur::getSignature()
     * @return bool
     */
    public function hasMultiDocumentSigne($info): bool
    {
        return false;
    }

    public function supportsMultiDocument(): bool
    {
        return false;
    }

    /**
     * @param array $info output of SignatureConnecteur::getSignature()
     * @return array $all_document_signe
     */
    public function getAllDocumentSigne(array $info): array
    {
        return [];
    }
}
