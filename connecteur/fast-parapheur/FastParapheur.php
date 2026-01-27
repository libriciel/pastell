<?php

class FastParapheur extends SignatureConnecteur
{
    public const PARAPHEUR_NB_JOUR_MAX_DEFAULT = 30;
    public const WSDL_URI = '/parapheur-soap/soap/v1/Documents?wsdl';
    public const REST_URI = '/parapheur-ws/rest/v1/';
    public const CIRCUIT_ON_THE_FLY_URI = self::REST_URI . '/documents/ondemand/%s/upload';
    public const REFUSAL_MESSAGE_URI = self::REST_URI . '/documents/v2/%s/comments/refusal';

    private const SIGNED_STATE = ['Signé'];

    private $url;

    private $subscriberNumber;

    private $connectionCertificatePassword;

    private $connectionCertificateCertOnly;
    private $connectionCertificateKeyOnly;
    private $connectionCertificateKeyCert;

    private $maxNumberOfDaysInParapheur;
    /** @var bool $doNotDeleteAfterRejection */
    private $doNotDeleteAfterRejection;

    /**
     * @var SoapClientFactory
     */
    private $soapClientFactory;

    /**
     * @var TmpFolder
     */
    private $tmpFolder;

    /**
     * @var ZipArchive
     */
    private $zipArchive;

    /**
     * @var CurlWrapperFactory
     */
    private $curlWrapperFactory;

    /**
     * @var CurlWrapper
     */
    private $curlWrapper;

    public function __construct(
        SoapClientFactory $soapClientFactory,
        CurlWrapperFactory $curlWrapperFactory,
        ?TmpFolder $tmpFolder = null,
        ?ZipArchive $zipArchive = null
    ) {
        $this->soapClientFactory = $soapClientFactory;
        $this->curlWrapperFactory = $curlWrapperFactory;
        $this->setTmpFolder($tmpFolder ?? new TmpFolder());
        $this->setZipArchive($zipArchive ?? new ZipArchive());
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->url = $donneesFormulaire->get('wsdl');
        $this->subscriberNumber = $donneesFormulaire->get('numero_abonnement');
        $this->maxNumberOfDaysInParapheur = $donneesFormulaire->get("parapheur_nb_jour_max");
        $this->doNotDeleteAfterRejection = (bool)$donneesFormulaire->get('parapheur_do_not_delete_on_rejection');

        $this->connectionCertificatePassword = $donneesFormulaire->get('certificat_password');

        $this->connectionCertificateCertOnly = $donneesFormulaire->getFilePath('certificat_connexion_cert_pem');
        $this->connectionCertificateKeyOnly = $donneesFormulaire->getFilePath('certificat_connexion_key_pem');
        $this->connectionCertificateKeyCert = $donneesFormulaire->getFilePath('certificat_connexion_key_cert_pem');

        $this->curlWrapper = $this->curlWrapperFactory->getInstance();
        $this->curlWrapper->setClientCertificate(
            $this->connectionCertificateCertOnly,
            $this->connectionCertificateKeyOnly,
            $this->connectionCertificatePassword
        );
    }

    /**
     * @param ZipArchive $zipArchive
     */
    public function setZipArchive(ZipArchive $zipArchive)
    {
        $this->zipArchive = $zipArchive;
    }

    /**
     * @param TmpFolder $tmpFolder
     */
    public function setTmpFolder(TmpFolder $tmpFolder)
    {
        $this->tmpFolder = $tmpFolder;
    }

    /**
     * @return DocapostParapheurSoapClient
     * @throws Exception
     */
    protected function getClient()
    {
        $stream_context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $client = $this->soapClientFactory->getInstance(
            $this->url . self::WSDL_URI,
            [
                'login' => '',
                'local_cert' => $this->connectionCertificateKeyCert,
                'passphrase' => $this->connectionCertificatePassword,
                'trace' => 1,
                'exceptions' => 1,
                'use_curl' => 1,
                'userKeyOnly' => $this->connectionCertificateKeyOnly,
                'userCertOnly' => $this->connectionCertificateCertOnly,
                'stream_context' => $stream_context
            ]
        );

        return new DocapostParapheurSoapClient($client);
    }

    /**
     * @throws Exception
     */
    public function testConnection()
    {
        return $this->getClient()->listRemainingAcknowledgements($this->subscriberNumber);
    }

    public function getNbJourMaxInConnecteur()
    {
        return $this->maxNumberOfDaysInParapheur ?: self::PARAPHEUR_NB_JOUR_MAX_DEFAULT;
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function getSousType(): array
    {
        return array_column($this->getClient()->getCircuits($this->subscriberNumber), 'circuitId') ;
    }

    /**
     * @param FileToSign $file
     * @return bool
     * @throws SignatureException
     * @throws Exception
     */
    public function sendDossier(FileToSign $file)
    {
        if (!$file->circuit && $file->circuit_configuration->content === null) {
            throw new SignatureException(
                "Le formulaire est incomplet : le champ 'Circuit sur le parapheur'" .
                " ou le champ 'Configuration du circuit à la volée' est obligatoire"
            );
        }
        $temporaryDirectory = $this->tmpFolder->create();
        try {
            return $this->sendDossierInternal($file, $temporaryDirectory);
        } finally {
            $this->tmpFolder->delete($temporaryDirectory);
        }
    }

    /**
     * @throws SignatureException
     */
    private function sendDossierInternal(FileToSign $file, string $temporaryDirectory)
    {
        if ($file->annexes) {
            try {
                $archive = $this->generateArchive(
                    $temporaryDirectory,
                    $file->document->filename,
                    $file->document->filepath,
                    $file->annexes
                );
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
            $file->document->filepath = $archive;
            $file->document->filename = basename($archive);
            $file->document->content = file_get_contents($archive);
            $file->document->contentType = mime_content_type($archive);
        }

        $this->curlWrapper->addPostFile(
            'doc',
            $file->document->filepath,
            $file->document->filename,
            $file->document->contentType
        );

        if (!empty($file->circuit_configuration->content)) {
            if ($file->emailRecipients) {
                $this->curlWrapper->addPostData('email_destinataire', $file->emailRecipients);
            }
            if ($file->emailCc) {
                $this->curlWrapper->addPostData('email_cc', $file->emailCc);
            }
            if ($file->agents) {
                $this->curlWrapper->addPostData('agents', $file->agents);
            }
            $this->curlWrapper->addPostData('circuit', $file->circuit_configuration->content);
            $result_from_curl = $this->curlWrapper->get(
                $this->url . sprintf(self::CIRCUIT_ON_THE_FLY_URI, $this->subscriberNumber)
            );

            if ($this->curlWrapper->getLastError()) {
                $this->lastError = $this->curlWrapper->getLastError();
                return false;
            }
            $result = json_decode($result_from_curl, true);
            if ($result === null) {
                $this->lastError = "unable to decode json : $result_from_curl";
                return false;
            }

            if (isset($result['errorCode'])) {
                throw new SignatureException(
                    sprintf(
                        "Erreur %s : %s (%s)",
                        $result['errorCode'],
                        $result['userFriendlyMessage'],
                        $result['developerMessage']
                    )
                );
            }
            return $result;
        }

        try {
            return $this->getClient()->upload(
                $this->subscriberNumber,
                $file->circuit,
                $file->document->filename,
                $file->document->content,
                $file->dossierTitre
            );
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function getSignature($documentId)
    {
        try {
            return $this->getClient()->download($documentId);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function getAllHistoriqueInfo($dossierID): bool|array
    {
        try {
            $history = $this->getClient()->history($dossierID);
            if (!is_array($history)) {
                $history = [$history];
            }
            return $history;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @param $history - output of FastParapheur::getAllHistoriqueInfo()
     */
    public function getLastHistorique($history): string
    {
        $lastLog = end($history);
        return sprintf(
            '%s : [%s]',
            date('d/m/Y H:i:s', strtotime($lastLog->date)),
            $lastLog->stateName
        );
    }

    /**
     * @param $history - output of FastParapheur::getAllHistoriqueInfo()
     */
    public function getDateSignature(stdClass|array $history): string
    {
        foreach (array_reverse($history) as $log) {
            if (in_array($log->stateName, self::SIGNED_STATE, true)) {
                $logSignature = $log;
                break;
            }
        }
        return isset($logSignature) ? date('Y-m-d', strtotime($logSignature->date)) : '';
    }

    public function effacerDossierRejete($documentId)
    {
        if ($this->doNotDeleteAfterRejection) {
            return true;
        }
        try {
            $this->getLogger()->debug("Effacement du dossier $documentId rejeté");
            $result = $this->getClient()->delete($documentId);
            $this->getLogger()->debug("Résultat de l'effacement du dossier $documentId: " . json_encode($result));
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->getLogger()->error("Impossible d'effacer le dossier $documentId: " . $e->getMessage());
            return false;
        }
        return $result;
    }

    /**
     * @codeCoverageIgnore
     * @return bool
     */
    public function isFastSignature()
    {
        return true;
    }

    /**
     * Generates an archive containing the document and its annexes
     * The name of the archive will be the name of the document (with its extension).zip
     * Example : main_document.pdf.zip
     *
     * @param $temporaryDirectory
     * @param $filename
     * @param $document
     * @param Fichier[] $annexes
     * @return string
     * @throws Exception
     */
    private function generateArchive(string $temporaryDirectory, $filename, $document, array $annexes)
    {
        $zipPath = $temporaryDirectory . DIRECTORY_SEPARATOR . basename($filename) . '.zip';
        if (!$this->zipArchive->open($zipPath, ZipArchive::CREATE)) {
            throw new Exception("Impossible de créer le fichier d'archive : $zipPath");
        }
        $this->zipArchive->addFile($document, $filename);

        foreach ($annexes as $annexe) {
            $this->zipArchive->addFile($annexe->filepath, $annexe->filename);
        }
        $this->zipArchive->close();

        return $zipPath;
    }

    public function isFinalState(string $lastHistorique): bool
    {
        return str_contains($lastHistorique, '[Classé]')
            || str_contains($lastHistorique, '[Signé]')
            || str_contains($lastHistorique, '[Archivé]');
    }

    public function isRejected(string $lastHistorique): bool
    {
        return str_contains($lastHistorique, '[Refusé]')
            || str_contains($lastHistorique, '[Visa désapprouvé]');
    }

    /**
     * @param $info - output of FastParapheur::getSignature()
     */
    public function isDetached($info): bool
    {
        return false;
    }

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $info - output of FastParapheur::getSignature()
     * @return mixed
     */
    public function getDetachedSignature($info)
    {
        return $info;
    }

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $info - output of FastParapheur::getSignature()
     * @return mixed
     */
    public function getSignedFile($info)
    {
        return $info;
    }


    /**
     * Workaround because it is embedded in IParapheur::getSignature()
     * @param $info - output of FastParapheur::getSignature()
     * @param string $documentId
     * @return ?Fichier
     */
    public function getBordereauFromSignature($info, string $documentId = ''): ?Fichier
    {
        try {
            $return = new Fichier();
            $return->filename = 'fichier_de_circulation.pdf';
            $return->content = $this->getClient()->getFdc($documentId);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
        return $return;
    }

    /**
     * @param $info - output of FastParapheur::getSignature()
     */
    public function getMetadataSortie($info): ?Fichier
    {
        return null;
    }

    /**
     * @param $dossierID
     */
    public function exercerDroitRemordDossier($dossierID)
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * @throws Exception
     */
    public function getRefusalMessage($dossierID): string
    {
        $result_from_curl = $this->curlWrapper->get(
            $this->url . sprintf(self::REFUSAL_MESSAGE_URI, $dossierID)
        );

        if ($this->curlWrapper->getLastError()) {
            throw new RuntimeException($this->curlWrapper->getLastError());
        }
        $result = json_decode($result_from_curl, true, 512, JSON_THROW_ON_ERROR);
        if ($result === null) {
            throw new RuntimeException("unable to decode json : $result_from_curl");
        }
        if (isset($result['errorCode']) && $result['errorCode'] !== 102) {
            throw new SignatureException(
                sprintf(
                    'Erreur %s : %s (%s)',
                    $result['errorCode'],
                    $result['userFriendlyMessage'],
                    $result['developerMessage']
                )
            );
        }

        return $result['comment'] ?? '';
    }
}
