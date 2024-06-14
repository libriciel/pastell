<?php

abstract class SignatureConnecteur extends Connecteur
{
    public const PARAPHEUR_NB_JOUR_MAX_DEFAULT = 30;

    abstract public function getNbJourMaxInConnecteur();

    abstract public function getSousType();

    abstract public function getDossierID($id, $name);

    /**
     * @throws SignatureException
     */
    abstract public function sendDossier(FileToSign $dossier);

    abstract public function getSignature($dossierID, $archive = true);

    abstract public function getAllHistoriqueInfo($dossierID);

    abstract public function getLastHistorique($history): string;

    abstract public function getRefusalMessage($dossierID);

    abstract public function getDateSignature(stdClass|array $history): string;

    abstract public function effacerDossierRejete($dossierID);

    abstract public function exercerDroitRemordDossier($dossierID);

    public function hasTypeSousType()
    {
        return true;
    }

    /**
    * Indique si le connecteur est un connecteur de signature "locale",
    * c'est à dire par applet sur le navigateur et sans appel à un serveur de signature externe
    * @return boolean
    */
    public function isLocalSignature()
    {
        return false;
    }

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

    public function getOutputAnnexe($info_from_get_signature, int $ignore_count)
    {
        return [];
    }

    abstract public function isFinalState(string $lastState): bool;
    abstract public function isRejected(string $lastState): bool;
    abstract public function isDetached($signature): bool;

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $file
     * @return mixed
     */
    abstract public function getDetachedSignature($file);

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $file
     * @return mixed
     */
    abstract public function getSignedFile($file);

    /**
     * Workaround because it is embedded in IParapheur::getSignature()
     *
     * @param $signature
     * @param string $documentId
     * @return Fichier|null
     */
    abstract public function getBordereauFromSignature($signature, string $documentId = ''): ?Fichier;

    /**
     * @param $info_from_get_signature
     * @return bool
     */
    public function hasMultiDocumentSigne($info_from_get_signature): bool
    {
        return false;
    }

    /**
     * @param array $info_from_get_signature output of IParapheur::getSignature()
     * @return array $all_document_signe
     */
    public function getAllDocumentSigne(array $info_from_get_signature): array
    {
        return [];
    }
}
