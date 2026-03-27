<?php

class FakeIparapheur extends SignatureConnecteur
{
    private $retour;
    private $iparapheur_type;
    /** @var string $iparapheur_sous_type */
    private $iparapheur_sous_type;
    private $iparapheur_envoi_status;
    private $iparapheur_temps_reponse;
    /** @var string $signatureField */
    private $signatureField;
    /** @var string $bordereauField */
    private $bordereauField;
    private $is_fast;

    public function setConnecteurConfig(DonneesFormulaire $collectiviteProperties)
    {
        $this->retour = $collectiviteProperties->get('iparapheur_retour');
        $this->iparapheur_type = $collectiviteProperties->get('iparapheur_type');
        $this->iparapheur_sous_type = $collectiviteProperties->get('iparapheur_sous_type');
        $this->iparapheur_envoi_status = $collectiviteProperties->get('iparapheur_envoi_status');
        $this->iparapheur_temps_reponse = (int)$collectiviteProperties->get('iparapheur_temps_reponse');
        $this->signatureField = $collectiviteProperties->get('field_signature', 'signature');
        $this->bordereauField = $collectiviteProperties->get('field_bordereau', 'document_signe');
        $this->is_fast = $collectiviteProperties->get('is_fast', false);
    }

    public function getNbJourMaxInConnecteur()
    {
        return self::PARAPHEUR_NB_JOUR_MAX_DEFAULT;
    }

    public function getSousType()
    {
        switch ($this->iparapheur_type) {
            case 'Actes':
                return ["Arrêté individuel", "Arrêté réglementaire", "Contrat et convention", "Délibération"];
            case 'PES':
                return ["BJ", "Bordereau depense"];
            case 'Document':
                return ["Courrier", "Commande", "Facture"];
            case 'Custom':
                return explode(';', $this->iparapheur_sous_type);
            default:
                return [];
        }
    }

    /**
     * @param FileToSign $dossier
     * @return string
     * @throws Exception
     */
    public function sendDossier(FileToSign $dossier)
    {
        if ($this->iparapheur_envoi_status == 'error') {
            throw new Exception(
                "Erreur déclenchée par le connecteur fake Iparapheur (iparapheur_envoi_status configuré à 'error')"
            );
        }
        return "Dossier déposé pour signature";
    }

    public function getSignature($dossierID)
    {
        $info['document'] = "Document";
        $info['nom_document'] = "document.txt";
        $info['is_pes'] = false;

        $document = $this->getDocDonneesFormulaire();
        if ($document->get($this->signatureField)) {
            $info['document_signe'] = [
                'document' => $document->getFileContent($this->signatureField),
                'nom_document' => $document->getFileName($this->signatureField)
            ];
        } else {
            $info['signature'] = "Test Signature";
            $info['document_signe'] = [
                'document' => "content",
                'nom_document' => "document_signe.txt"
            ];
        }
        if ($document->get($this->bordereauField)) {
            $info['nom_document'] = $document->getFileName($this->bordereauField);
            $info['document'] = $document->getFileContent($this->bordereauField);
        }

        return $info;
    }

    /**
     * @throws JsonException
     * @throws UnrecoverableException
     */
    public function getAllHistoriqueInfo($dossierID)
    {
        sleep($this->iparapheur_temps_reponse);
        $timestamp = date(DATE_ATOM);
        $user = 'simulation de parapheur!';
        if ($this->retour === 'Archive') {
            return json_decode(json_encode([
                'LogDossier' => [
                    0 => [
                        'timestamp' => $timestamp,
                        'nom' => $user,
                        'status' => 'Signe',
                    ],
                    1 => [
                        'timestamp' => $timestamp,
                        'nom' => $user,
                        'status' => 'Archive',
                        'annotation' => sprintf('Circuit terminé, dossier archivable (%s)', $user),
                    ],
                ],
                'MessageRetour' => [
                    'codeRetour' => 'OK',
                    'message' => '',
                    'severite' => 'INFO'
                ]
            ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        }
        if ($this->retour === 'Rejet') {
            return json_decode(json_encode([
                'LogDossier' => [
                    0 => [
                        'timestamp' => $timestamp,
                        'nom' => $user,
                        'status' => 'RejetVisa',
                        'annotation' => sprintf('(%s)', $user),
                    ],
                ],
                'MessageRetour' => [
                    'codeRetour' => 'OK',
                    'message' => '',
                    'severite' => 'INFO'
                ]
            ], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        }
        throw new Exception('Erreur provoquée par le simulateur du iParapheur');
    }

    /**
     * @param $history - output of FakeIparapheur::getAllHistoriqueInfo()
     */
    public function getLastHistorique($history): string
    {
        $lastLog = end($history->LogDossier);
        return sprintf(
            '%s : [%s] %s',
            date('d/m/Y H:i:s', strtotime($lastLog->timestamp)),
            $lastLog->status,
            $lastLog->annotation
        );
    }

    /**
     * @param $history - output of FakeIparapheur::getAllHistoriqueInfo()
     */
    public function getDateSignature(stdClass|array $history): string
    {
        foreach (array_reverse($history->LogDossier) as $log) {
            if ($log->status === 'Signe') {
                $logSignature = $log;
                break;
            }
        }
        return isset($logSignature) ? date('Y-m-d', strtotime($logSignature->timestamp)) : '';
    }

    public function effacerDossierRejete($dossierID)
    {
        return true;
    }

    public function getLogin()
    {
        return "ok";
    }

    /**
     * @param $lastHistorique - output of FakeIparapheur::getLastHistorique()
     */
    public function isFinalState(string $lastHistorique): bool
    {
        return strstr($lastHistorique, '[Archive]');
    }

    /**
     * @param $lastHistorique - output of FakeIparapheur::getLastHistorique()
     */
    public function isRejected(string $lastHistorique): bool
    {
        return strstr($lastHistorique, '[RejetVisa]') || strstr($lastHistorique, '[RejetSignataire]');
    }

    /**
     * @param $info - output of FakeIparapheur::getSignature()
     */
    public function isDetached($info): bool
    {
        return $info['signature'] && !$info['is_pes'];
    }

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $info - output of FakeIparapheur::getSignature()
     * @return mixed
     */
    public function getDetachedSignature($info)
    {
        return $info['signature'];
    }

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     */
    public function getSignedFile($info_from_get_signature): Fichier
    {
        $signedFile = new Fichier();
        $signedFile->content = $info_from_get_signature['signature'] ?: $info_from_get_signature['document_signe']['document'];
        $signedFile->filename = $info_from_get_signature['signature'] ? null : $info_from_get_signature['document_signe']['nom_document'];
        return $signedFile;
    }


    /**
     * Workaround because it is embedded in IParapheur::getSignature()
     *
     * @param $info - output of FakeIparapheur::getSignature()
     * @param string $documentId
     * @return Fichier|null
     */
    public function getBordereauFromSignature($info, string $documentId = ''): ?Fichier
    {
        $file = new Fichier();
        $file->filename = $info['nom_document'];
        $file->content = $info['document'];
        return $file;
    }

    /**
     * @param $info - output of FakeIparapheur::getSignature()
     */
    public function getMetadataSortie($info): ?Fichier
    {
        return null;
    }


    public function isFastSignature()
    {
        return $this->is_fast;
    }

    /**
     * @param $dossierID
     * @return bool
     */
    public function exercerDroitRemordDossier($dossierID): bool
    {
        return true;
    }

    public function getRefusalMessage($dossierID)
    {
        return '';
    }
}
