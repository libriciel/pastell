<?php

use Sabre\HTTP\ClientHttpException;

class FastTdt extends TdtConnecteur
{
    public const ACTES_FLUX_TRANSMISSION = '1-1';
    public const ACTES_FLUX_ACKNOWLEDGMENT = '1-2';
    public const ACTES_FLUX_ANOMALY = '1-3';
    public const ACTES_FLUX_CANCELLATION = '6-1';
    public const ACTES_FLUX_CANCELLATION_ACKNOWLEDGMENT = '6-2';
    public const ACTES_FLUX_CLASSIFICATION = '7-2';

    public const ACTE_FIELD = 'arrete';
    public const SIGNATURE_FIELD = 'signature';
    public const ANNEXES_FIELD = 'autre_document_attache';
    public const CIRCUIT_ID_FIELD = 'circuit';

    /** @var  WebdavWrapper */
    private $webDavWrapper;

    /** @var SoapClientFactory */
    private $soapClientFactory;

    /** @var Journal */
    private $journal;

    private $url = '';
    private $department;
    private $subscriberNumber;
    private $publisherPrefix;
    private $userDn;
    private $classification;
    private $classificationDate;
    private $circuit;

    private $connectionCertificatePassword;

    private $connectionCertificateCertOnly;
    private $connectionCertificateKeyOnly;
    private $connectionCertificateKeyCert;

    private $arActeDate;
    private string $typeTraitement;

    /**
     * FastTdt constructor.
     *
     * @param WebdavWrapper $webdavWrapper
     * @param SoapClientFactory $soapClientFactory
     * @param Journal $journal
     */
    public function __construct(WebdavWrapper $webdavWrapper, SoapClientFactory $soapClientFactory, Journal $journal)
    {
        $this->webDavWrapper = $webdavWrapper;
        $this->soapClientFactory = $soapClientFactory;
        $this->journal = $journal;
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->url = $donneesFormulaire->get('url');
        $this->department = $donneesFormulaire->get('departement');
        $this->subscriberNumber = $donneesFormulaire->get('numero_abonnement');
        $this->publisherPrefix = $donneesFormulaire->get('prefixe_editeur');
        $this->userDn = $donneesFormulaire->get('dn_user');
        $this->classification = $donneesFormulaire->getFilePath('classification_file');
        $this->classificationDate = $donneesFormulaire->get('classification_date');
        $this->circuit = $donneesFormulaire->get(self::CIRCUIT_ID_FIELD);
        $this->typeTraitement = $donneesFormulaire->get('type_traitement') ?: 'TELETRANSMISSION';

        $this->connectionCertificatePassword = $donneesFormulaire->get('certificat_password');

        $this->connectionCertificateCertOnly = $donneesFormulaire->getFilePath('certificat_connexion_cert_pem');
        $this->connectionCertificateKeyOnly = $donneesFormulaire->getFilePath('certificat_connexion_key_pem');
        $this->connectionCertificateKeyCert = $donneesFormulaire->getFilePath('certificat_connexion_key_cert_pem');

        $this->webDavWrapper->setDataConnexion($this->getWebdavUrl(), "", "");

        $this->webDavWrapper->allowInsecureConnection();

        $this->webDavWrapper->setAuthenticationByCertificate(
            $this->connectionCertificateCertOnly,
            $this->connectionCertificateKeyOnly,
            $this->connectionCertificatePassword
        );
    }

    /**
     * @return string
     */
    public function getWebdavUrl(): string
    {
        return "$this->url/webdav/$this->subscriberNumber/$this->publisherPrefix/";
    }

    /**
     * @return string
     */
    public function getSoapUrl(): string
    {
        return "$this->url/services/FASTConnecteur";
    }

    /**
     * @return NotBuggySoapClient
     * @throws Exception
     */
    protected function getActesClient()
    {
        $stream_context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        return $this->soapClientFactory->getInstance(
            $this->getSoapUrl() . '?wsdl',
            [
                'login' => '',
                'local_cert' => $this->connectionCertificateKeyCert,
                'passphrase' => $this->connectionCertificatePassword,
                'trace' => 1,
                'exceptions' => 1,
                'userKeyOnly' => $this->connectionCertificateKeyOnly,
                'userCertOnly' => $this->connectionCertificateCertOnly,
                'stream_context' => $stream_context,
                'location' => $this->getSoapUrl(),
            ]
        );
    }

    /**
     * @return DocapostParapheurSoapClient
     * @throws Exception
     */
    public function getHeliosClient(): DocapostParapheurSoapClient
    {
        $stream_context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $client = $this->soapClientFactory->getInstance(
            $this->url,
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
     * @return string
     */
    private function getRegexClassificationFiles(): string
    {
        return sprintf(
            '/%s-%s-(.*)-%s_\d+.xml/',
            $this->department,
            $this->subscriberNumber,
            self::ACTES_FLUX_CLASSIFICATION
        );
    }

    public function getLogicielName()
    {
        // TODO: Implement getLogicielName() method.
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function testConnexion()
    {
        return strstr($this->url, '?wsdl')
            ? $this->getHeliosClient()->listRemainingAcknowledgements($this->subscriberNumber)
            : $this->webDavWrapper->isConnected();
    }

    /**
     * @return resource|string
     * @throws Exception
     */
    public function getClassification()
    {
        if (strstr($this->url, '?wsdl')) {
            throw new BadMethodCallException(
                "La classification n'est récupérable qu'avec le connecteur configuré pour les actes"
            );
        }
        $files = $this->webDavWrapper->listFolder('');
        $latestClassificationFile = '';
        $latestClassificationDate = 0;
        $filesToRemove = [];

        $regexClassificationFiles = $this->getRegexClassificationFiles();
        foreach ($files as $file) {
            if (preg_match($regexClassificationFiles, $file)) {
                $currentClassificationFile = mb_convert_encoding($this->webDavWrapper->get($file), 'ISO-8859-1');
                $simpleXMLWrapper = new SimpleXMLWrapper();
                $xmlDocument = $simpleXMLWrapper->loadString($currentClassificationFile);
                $currentClassificationDate = (string)$xmlDocument->xpath('//actes:DateClassification')[0];

                if (strtotime($currentClassificationDate) > strtotime($latestClassificationDate)) {
                    $latestClassificationDate = $currentClassificationDate;
                    $latestClassificationFile = $currentClassificationFile;
                } else {
                    $filesToRemove[] = $file;
                }
            }
        }

        $this->purgeClassificationFiles($filesToRemove);

        return $latestClassificationFile;
    }

    public function demandeClassification()
    {
        // TODO: Implement demandeClassification() method.
    }

    /**
     * @param $id_transaction
     * @throws ClientHttpException
     * @throws FastTdtException
     */
    public function annulationActes($id_transaction)
    {
        $acte = new Acte();
        $acte->idActe = $id_transaction;
        $actesMessageMetier = new ActesMessageMetier($acte);
        $cancellationFileContent = $actesMessageMetier->generateXmlCancellationFile();

        $cancellationFileName = sprintf(
            '%s-%s_%s.%s',
            $acte->idActe,
            self::ACTES_FLUX_CANCELLATION,
            0,
            'xml'
        );
        $this->uploadMessageMetier($cancellationFileName, $cancellationFileContent);

        $this->processActe($cancellationFileName);
        return $id_transaction;
    }

    public function verifClassif()
    {
        // TODO: Implement verifClassif() method.
    }

    /**
     * @param Fichier $fichierHelios
     * @return mixed
     * @throws DocapostParapheurSoapClientException
     * @throws Exception
     */
    public function sendHelios(Fichier $fichierHelios)
    {
        return $this->getHeliosClient()->upload(
            $this->subscriberNumber,
            $this->circuit,
            $fichierHelios->filename,
            $fichierHelios->content
        );
    }

    /**
     * @param TdtActes $tdtActes
     * @return string
     * @throws ClientHttpException
     * @throws FastTdtException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function sendActes(TdtActes $tdtActes)
    {
        $acte = new Acte();
        $acte->date = $tdtActes->date_de_lacte;
        $acte->numero = $tdtActes->numero_de_lacte;
        $acte->codeNature = $tdtActes->acte_nature;
        $acte->classification = $tdtActes->classification;
        $acte->classificationDate = $this->classificationDate;
        $acte->object = $tdtActes->objet;
        $acte->documentPapier = $tdtActes->document_papier ? 'O' : 'N';

        $shortenedNatureActe = $this->getShortenedNatureActe($acte->codeNature);
        $messageMetierFilename = $this->getNormalizedActeName(
            $acte->date,
            $acte->numero,
            $shortenedNatureActe,
            '0',
            'xml'
        );

        $typologieActe = $tdtActes->type_acte ?: $this->getDefaultTypology($acte->codeNature, $this->classification);
        $acteExtension = pathinfo($tdtActes->arrete->filename, PATHINFO_EXTENSION);
        $acteFileName = $this->getNormalizedDocumentActeName(
            $typologieActe,
            $acte->date,
            $acte->numero,
            $shortenedNatureActe,
            '1',
            $acteExtension
        );
        $annexes = array_map(
            function (Fichier $file) {
                return $file->filename;
            },
            $tdtActes->autre_document_attache
        );

        $annexesFileNames = $this->getAnnexesFileNames(
            $tdtActes->type_pj,
            $annexes,
            $typologieActe,
            $acte->date,
            $acte->numero,
            $shortenedNatureActe
        );
        $acte->acte = $acteFileName;
        $acte->annexes = $annexesFileNames;

        $actesMessageMetier = new ActesMessageMetier($acte);
        $messageMetierFileContent = $actesMessageMetier->generateXmlTransmissionFile();

        $this->uploadMessageMetier($messageMetierFilename, $messageMetierFileContent);
        $this->uploadActe($tdtActes->arrete, $acteFileName);
        $this->uploadAnnexes($tdtActes->autre_document_attache, $annexesFileNames);

        $this->processActe($messageMetierFilename);

        return $this->getActeBaseName($acte->date, $acte->numero, $shortenedNatureActe);
    }

    /**
     * @param $id_transaction
     * @return bool|int
     * @throws DocapostParapheurSoapClientException
     * @throws Exception
     */
    public function getStatusHelios($id_transaction)
    {
        try {
            $remainingAcknowledgments = $this
                ->getHeliosClient()
                ->listRemainingAcknowledgements($this->subscriberNumber)
                ->return ?? [];
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
        if (is_string($remainingAcknowledgments)) {
            $remainingAcknowledgments = [$remainingAcknowledgments];
        }
        if (!in_array($id_transaction, $remainingAcknowledgments)) {
            $history = $this->getHeliosClient()->history($id_transaction);
            $lastHistory = end($history);

            if ($lastHistory->stateName === 'Échec du traitement FAST') {
                return TdtConnecteur::STATUS_ERREUR;
            }
            $finalStates = [
                'Acquittement Hélios',
                'Classé',
                'Archivé'
            ];
            if (!in_array($lastHistory->stateName, $finalStates, true)) {
                return TdtConnecteur::STATUS_HELIOS_TRAITEMENT;
            }
        }
        return TdtConnecteur::STATUS_HELIOS_INFO;
    }

    /**
     * @param $id_transaction
     * @return int
     * @throws ClientHttpException
     * @throws Exception
     */
    public function getStatus($id_transaction)
    {
        $files = $this->webDavWrapper->propfind(
            '',
            [
                '{DAV:}getlastmodified',
                '{DAV:}getcontentlength',
                '{DAV:}getcontenttype'
            ],
            1
        );
        $transactionId = [];
        preg_match(
            '/(.*)-(.*)-(\d{8})-(.*)-(.*)/',
            $id_transaction,
            $transactionId
        );
        $filesMatchingTransactionId = [];
        foreach ($files as $file => $properties) {
            $matches = [];
            $pattern = sprintf(
                '/%s-%s-(\d{8})-%s-%s-(%s|%s|%s)_(.*).xml/',
                $transactionId[1], // department
                $transactionId[2], // subscriber number
                $transactionId[4], // acte number
                $transactionId[5], // shortened acte nature
                self::ACTES_FLUX_ACKNOWLEDGMENT,
                self::ACTES_FLUX_ANOMALY,
                self::ACTES_FLUX_CANCELLATION_ACKNOWLEDGMENT
            );
            if (preg_match($pattern, $file, $matches)) {
                $filesMatchingTransactionId[$file] = $matches[2];
            }
        }

        if (count($filesMatchingTransactionId) === 0) {
            return TdtConnecteur::STATUS_TRANSMIS;
        }
        if (count($filesMatchingTransactionId) > 1) {
            foreach ($filesMatchingTransactionId as $filename => $acteFlux) {
                $filecontent = $this->webDavWrapper->get($filename);
                $properties = $files[$filename];
                $message = [
                    'filename' => $filename,
                    'mtime' => $properties['{DAV:}getlastmodified'],
                    'content_length' => $properties['{DAV:}getcontentlength'],
                    'content_type' => $properties['{DAV:}getcontenttype'],
                    'md5sum' => md5($filecontent)
                ];
                $this->journal->addSQL(
                    Journal::DOCUMENT_ACTION_ERROR,
                    $this->getConnecteurInfo()['id_e'] ?? 0,
                    0,
                    $this->getDocDonneesFormulaire()->id_d,
                    'tdt-error',
                    json_encode($message)
                );
                $this->webDavWrapper->delete('', $filename);
            }
            return TdtConnecteur::STATUS_ERREUR;
        }

        $acteFlux = current($filesMatchingTransactionId);
        $filename = key($filesMatchingTransactionId);

        $fileContent = $this->webDavWrapper->get($filename);
        $simpleXMLWrapper = new SimpleXMLWrapper();
        $xmlDocument = $simpleXMLWrapper->loadString($fileContent);

        $status = TdtConnecteur::STATUS_TRANSMIS;
        if ($acteFlux === self::ACTES_FLUX_ACKNOWLEDGMENT) {
            $this->setArActes($fileContent);
            $this->arActeDate = (string)$xmlDocument->xpath('//actes:ARActe/@actes:DateReception')[0];
            $this->getDocDonneesFormulaire()->setData('has_bordereau', true);
            $status = TdtConnecteur::STATUS_ACQUITTEMENT_RECU;
        }
        if ($acteFlux === self::ACTES_FLUX_ANOMALY) {
            $this->lastError = (string)$xmlDocument->xpath('//actes:Detail')[0];
            $status = TdtConnecteur::STATUS_ERREUR;
        }
        if ($acteFlux === self::ACTES_FLUX_CANCELLATION_ACKNOWLEDGMENT) {
            $this->setArActes($fileContent);
            $this->arActeDate = (string)$xmlDocument->xpath('//actes:ARAnnulation/@actes:DateReception')[0];
            $status = TdtConnecteur::STATUS_ACQUITTEMENT_RECU;
        }

        $this->webDavWrapper->delete('', $filename);

        return $status;
    }

    public function getLastReponseFile()
    {
        // TODO: Implement getLastReponseFile() method.
    }

    /**
     * @param $id_transaction
     * @return mixed
     */
    public function getDateAR($id_transaction)
    {
        return $this->arActeDate;
    }

    /**
     * @param $id_transaction
     * @return bool
     */
    public function getBordereau($id_transaction)
    {
        return false;
    }

    /**
     * @param string $id_transaction
     * @param string|null $date_affichage
     * @return ?string
     * @throws Exception
     */
    public function getActeTamponne(string $id_transaction, ?string $date_affichage = null): ?string
    {
        $pdf_file = null;
        foreach ($this->webDavWrapper->listFolder('') as $doc) {
            if (preg_match("/$id_transaction.*\.pdf$/i", $doc)) {
                $pdf_file = $this->webDavWrapper->get($doc);
                $this->webDavWrapper->delete('', $doc);
                break;
            }
        }
        return $pdf_file;
    }

    /**
     * @param $transaction_id
     * @return mixed
     * @throws DocapostParapheurSoapClientException
     * @throws Exception
     */
    public function getFichierRetour($transaction_id)
    {
        return $this->getHeliosClient()->downloadAcknowledgement($transaction_id);
    }

    public function getListReponsePrefecture($transaction_id)
    {
        // TODO: Implement getListReponsePrefecture() method.
    }

    public function getReponsePrefecture($transaction_id)
    {
        // TODO: Implement getReponsePrefecture() method.
    }

    public function sendResponse(DonneesFormulaire $donneesFormulaire)
    {
        // TODO: Implement sendResponse() method.
    }

    public function getAnnexesTamponnees(string $transaction_id, ?string $date_affichage = null): array
    {
        return [];
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getFilenameTransformation(string $filename): string
    {
        return $filename;
    }

    /**
     * Remove all classification files except the latest one
     *
     * @param array $filesToRemove The classification file to be removed
     * @return bool
     * @throws ClientHttpException
     * @throws FastTdtException
     */
    public function purgeClassificationFiles(array $filesToRemove): bool
    {
        foreach ($filesToRemove as $file) {
            $response = $this->webDavWrapper->delete('', $file);
            if ($response['statusCode'] !== 204) {
                throw new FastTdtException(
                    "Impossible de supprimer le fichier de classification $file : Code : "
                    . $response['statusCode'] . ' ' . $response['body']
                );
            }
        }

        return true;
    }

    /**
     * @param string $date
     * @param string $numeroActe
     * @param $shortenedNatureActe
     * @return string
     */
    private function getActeBaseName(string $date, string $numeroActe, $shortenedNatureActe): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            $this->department,
            $this->subscriberNumber,
            date('Ymd', strtotime($date)),
            $numeroActe,
            $shortenedNatureActe
        );
    }

    /**
     * @param $date
     * @param $numeroActe
     * @param string $shortenedNatureActe
     * @param $counter
     * @param $extension
     * @return string
     */
    private function getNormalizedActeName(
        $date,
        $numeroActe,
        string $shortenedNatureActe,
        $counter,
        $extension
    ): string {
        return sprintf(
            '%s-%s_%s.%s',
            $this->getActeBaseName($date, $numeroActe, $shortenedNatureActe),
            self::ACTES_FLUX_TRANSMISSION,
            $counter,
            $extension
        );
    }

    /**
     * @param $typolgy
     * @param $date
     * @param $numeroActe
     * @param string $shortenedNatureActe
     * @param $counter
     * @param $extension
     * @return string
     */
    private function getNormalizedDocumentActeName(
        $typolgy,
        $date,
        $numeroActe,
        string $shortenedNatureActe,
        $counter,
        $extension
    ): string {
        return sprintf(
            '%s-%s',
            $typolgy,
            $this->getNormalizedActeName($date, $numeroActe, $shortenedNatureActe, $counter, $extension)
        );
    }

    /**
     * @param string $type_pj
     * @param $annexes
     * @param $typologieActe
     * @param $date
     * @param $numeroActe
     * @param string $shortenedNatureActe
     * @return array
     */
    private function getAnnexesFileNames(
        string $type_pj,
        $annexes,
        $typologieActe,
        $date,
        $numeroActe,
        string $shortenedNatureActe
    ): array {
        if (!$annexes) {
            return [];
        }
        $annexesFileNames = [];
        $annexesWithTypology = [];
        $type_annexes = json_decode($type_pj);
        if ($type_annexes) {
            $annexesWithTypology = array_combine($annexes, $type_annexes);
        } else {
            foreach ($annexes as $annexe) {
                $annexesWithTypology[$annexe] = $typologieActe;
            }
        }

        $index = 2;
        foreach ($annexesWithTypology as $annexe => $typology) {
            $counter = $this->getFileCounter(count($annexes), $index);
            $annexesFileNames[$annexe] = $this->getNormalizedDocumentActeName(
                $typology,
                $date,
                $numeroActe,
                $shortenedNatureActe,
                $counter,
                pathinfo($annexe, PATHINFO_EXTENSION)
            );
            ++$index;
        }
        return $annexesFileNames;
    }

    /**
     * @param string $messageMetierFilename
     * @param string $messageMetierFileContent
     * @throws ClientHttpException
     * @throws Exception
     */
    private function uploadMessageMetier(string $messageMetierFilename, string $messageMetierFileContent)
    {
        if ($this->webDavWrapper->exists($messageMetierFilename)) {
            $this->webDavWrapper->delete('', $messageMetierFilename);
        }
        $this->webDavWrapper->addDocument(
            '',
            $messageMetierFilename,
            $messageMetierFileContent
        );
    }

    /**
     * @param Fichier $acte
     * @param string $acteFileName
     * @throws ClientHttpException
     * @throws Exception
     */
    private function uploadActe(Fichier $acte, string $acteFileName)
    {
        if ($this->webDavWrapper->exists($acteFileName)) {
            $this->webDavWrapper->delete('', $acteFileName);
        }
        $this->webDavWrapper->addDocument(
            '',
            $acteFileName,
            $acte->content,
            [
                'Content-Type' => $acte->contentType,
            ]
        );
    }

    /**
     * @param Fichier[] $annexes
     * @param array $annexesFileNames
     * @throws ClientHttpException
     * @throws Exception
     */
    private function uploadAnnexes(array $annexes, array $annexesFileNames)
    {
        $index = 0;
        foreach ($annexesFileNames as $normalizedName) {
            if ($this->webDavWrapper->exists($normalizedName)) {
                $this->webDavWrapper->delete('', $normalizedName);
            }
            $this->webDavWrapper->addDocument(
                '',
                $normalizedName,
                $annexes[$index]->content,
                [
                    'Content-Type' => $annexes[$index]->contentType,
                ]
            );
            ++$index;
        }
    }

    /**
     * @param string $messageMetierFilename
     * @throws FastTdtException
     * @throws Exception
     */
    private function processActe(string $messageMetierFilename)
    {
        $result = $this->getActesClient()->traiterACTES(
            [
                'typeTraitement' => $this->typeTraitement,
                'DNUtilisateur' => $this->userDn,
                'SIREN' => $this->subscriberNumber,
                'fichierACTES' => $messageMetierFilename,
            ]
        );

        if ($result->code) {
            throw new FastTdtException(
                "Erreur lors du traitement de l'acte : " . $result->code . " : " . $result->detail
            );
        }
    }

    /**
     * If there is more than 8 annexes, we need to add a leading 0 to the files < 10
     *
     * @param int $numberOfAnnexes
     * @param int $index
     * @return int|string
     */
    private function getFileCounter(int $numberOfAnnexes, int $index)
    {
        return $numberOfAnnexes > 8
            ? sprintf("%02d", $index)
            : $index;
    }

    public function getSiren(): string
    {
        return $this->subscriberNumber;
    }
}
