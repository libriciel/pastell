<?php

use Pastell\Utilities\TextTruncator;

class IParapheur extends SignatureConnecteur
{
    public const IPARAPHEUR_NB_JOUR_MAX_DEFAULT = SignatureConnecteur::PARAPHEUR_NB_JOUR_MAX_DEFAULT;

    public const ARCHIVAGE_ACTION_EFFACER = "EFFACER";

    private const REJECTED_STATE = ['RejetVisa', 'RejetSignataire','RejetCachet', 'RejetMailSecPastell', 'RejetSignataireExterne'];
    private const MAX_TITLE_LENGTH = 255;

    private $wsdl;
    private $login_http;
    private $password_http;

    private $iparapheur_type;
    private $iparapheur_nb_jour_max;
    private $visibilite;
    private $xPathPourSignatureXML;

    private $soapClientFactory;

    /** @var NotBuggySoapClient */
    private $last_client;

    private $iparapheur_metadata;
    private $sending_metadata;
    private $iparapheur_multi_doc;

    /** @var DonneesFormulaire */
    private $collectiviteProperties;

    public function __construct(SoapClientFactory $soapClientFactory)
    {
        $this->soapClientFactory = $soapClientFactory;
    }

    public function setConnecteurConfig(DonneesFormulaire $collectiviteProperties)
    {

        $this->collectiviteProperties = $collectiviteProperties;

        $this->wsdl = $collectiviteProperties->get("iparapheur_wsdl");
        $this->login_http = $collectiviteProperties->get("iparapheur_login");
        $this->password_http = $collectiviteProperties->get("iparapheur_password");

        $this->iparapheur_type = $collectiviteProperties->get("iparapheur_type");
        $this->iparapheur_nb_jour_max = $collectiviteProperties->get("iparapheur_nb_jour_max");

        $this->visibilite = $collectiviteProperties->get('iparapheur_visibilite') ?: "SERVICE";

        $this->xPathPourSignatureXML =  $collectiviteProperties->get('XPathPourSignatureXML');
        $this->iparapheur_metadata =  $collectiviteProperties->get('iparapheur_metadata');

        $this->iparapheur_multi_doc =  $collectiviteProperties->get('iparapheur_multi_doc');
    }

    public function getNbJourMaxInConnecteur()
    {
        if ($this->iparapheur_nb_jour_max) {
            return $this->iparapheur_nb_jour_max;
        }
        return self::IPARAPHEUR_NB_JOUR_MAX_DEFAULT;
    }


    public function getDossierID($id, $name)
    {
        $name = preg_replace("#[^A-Za-z0-9éèçàêîâôûùüÉÈÇÀÊÎÂÔÛÙÜ_]#u", "_", $name);
        $name = mb_substr($name, 0, 100);
        return "$id $name";
    }

    /**
     * @param $dossierID
     * @return mixed
     * @throws Exception
     */
    public function getDossier($dossierID)
    {
        return  $this->getClient()->GetDossier($dossierID);
    }

    public function getBordereau($result)
    {
        $info = [];
        if (! isset($result->DocumentsAnnexes)) {
            $info['document'] = false;
            $info['nom_document'] = false;
            return $info;
        }

        if (isset($result->DocumentsAnnexes->DocAnnexe->fichier)) {
            $info['document'] = $result->DocumentsAnnexes->DocAnnexe->fichier->_;
            $info['nom_document'] = trim($result->DocumentsAnnexes->DocAnnexe->nom, '"');
            return $info;
        }

        $bordereau = null;
        foreach ($result->DocumentsAnnexes->DocAnnexe as $bordereau) {
        }
        $info['document'] = $bordereau->fichier->_;
        $info['nom_document'] = trim($bordereau->nom, '"');
        return $info;
    }

    private function getMultiDocumentSigne($result): array
    {
        if (! isset($result->DocumentsSupplementaires->DocAnnexe)) {
            return [];
        }

        $all_multi_document = $result->DocumentsSupplementaires->DocAnnexe ;
        $result = [];

        if (isset($all_multi_document->fichier)) {
            $result[] = [
                'nom_document' => trim($all_multi_document->nom, '"'),
                'document' => $all_multi_document->fichier->_
            ];
        } elseif (is_array($all_multi_document)) {
            foreach ($all_multi_document as $multi_document) {
                $result[] = [
                    'nom_document' => trim($multi_document->nom, '"'),
                    'document' => $multi_document->fichier->_
                ];
            }
        }
        return $result;
    }

    public function getAnnexe($result)
    {

        if (! isset($result->DocumentsAnnexes->DocAnnexe)) {
            return [];
        }

        $all_doc_annexe = $result->DocumentsAnnexes->DocAnnexe ;

        if (! is_array($all_doc_annexe)) {
            return [];
        }
        if (count($all_doc_annexe) < 2) {
            return [];
        }

        $result = [];

        // Le dernier document est forcément le bordereau
        array_pop($all_doc_annexe);

        foreach ($all_doc_annexe as $annexe) {
            $result[] = [
                'nom_document' => trim($annexe->nom, '"'),
                'document' => $annexe->fichier->_
                ];
        }
        return $result;
    }

    /**
     * @param array $info_from_get_signature output of IParapheur::getSignature()
     * @param int $ignore_count Ignore the $ignore_count first annexe (i-Parapheur send back the annexes created initialy)
     * @return array output annexe
     */
    public function getOutputAnnexe($info_from_get_signature, int $ignore_count)
    {
        if (empty($info_from_get_signature['annexe'])) {
            return [];
        }
        return array_slice($info_from_get_signature['annexe'], $ignore_count);
    }

    private function getDocumentSigne($result)
    {
        $info = [];
        if (! isset($result->DocPrincipal)) {
            $info['document'] = false;
            $info['nom_document'] = false;
            return $info;
        }
        $info['document'] = $result->DocPrincipal->_;
        $info['nom_document'] = $result->NomDocPrincipal;
        return $info;
    }

    public function getAllMetaDonnees($result)
    {
        $info = [];
        if (! isset($result->MetaDonnees)) {
            return false;
        }

        $array_metadonnees = json_decode(json_encode($result->MetaDonnees), true);

        foreach ($array_metadonnees as $metadonnee) {
            if (isset($metadonnee['nom'])) {
                $info[] = [
                    "nom" => $metadonnee["nom"],
                    "valeur" => $metadonnee["valeur"],
                ];
            } else {
                foreach ($metadonnee as $value) {
                    if (isset($value['nom'])) {
                        $info[] = [
                            "nom" => $value["nom"],
                            "valeur" => $value["valeur"],
                        ];
                    }
                }
            }
        }
        return $info;
    }

    public function getMetaDonnee($metaDonnees, $nom)
    {
        if ($metaDonnees) {
            foreach ($metaDonnees as $metaDonnee) {
                if (($metaDonnee["nom"]) == $nom) {
                    return $metaDonnee["valeur"];
                }
            }
        }
        return false;
    }

    /**
     * @param $dossierID
     * @param bool $archiver => Il faut toujours mettre false et appellé archiver() après avoir enregistré la signature
     *                  Sinon, en cas de fulldisk, on perd la signature et le parapheur l'a effacé !
     *                  Il faudrait refaire cette fonction...
     * @return array|bool
     */
    public function getSignature($dossierID, $archiver = true)
    {
        try {
            $result =  $this->getClient()->GetDossier($dossierID);
            if ($result->MessageRetour->codeRetour != 'OK') {
                $message = "[{$result->MessageRetour->severite}] {$result->MessageRetour->message}";
                $this->lastError = $message;
                return false;
            }
            $info = $this->getBordereau($result);
            $info['meta_donnees'] = $this->getAllMetaDonnees($result);
            $info['is_pes'] = false;
            if (isset($result->SignatureDocPrincipal)) {
                $info['signature'] = $result->SignatureDocPrincipal->_;
            } elseif (isset($result->FichierPES)) {
                $info['signature'] = $result->FichierPES->_;
                $info['is_pes'] = true;
            } else {
                $info['signature'] = false;
            }

            $info['document_signe'] = $this->getDocumentSigne($result);
            $info['multi_document_signe'] = $this->getMultiDocumentSigne($result);
            $info['annexe'] = $this->getAnnexe($result);

            if ($archiver) {
                //TODO BUG ! Si on fait ca et qu'on arrive pas à écrire sur le FS, alors... on est mal...
                $this->archiver($dossierID);
            }
            return $info;
        } catch (Exception $e) {
            $this->lastError = "Erreur sur la récupération de la signature : " . $e->getMessage();
            return false;
        }
    }

    public function archiver($dossierID)
    {
        try {
            $this->getLogger()->debug(
                "Archivage" . self::ARCHIVAGE_ACTION_EFFACER . " du dossier $dossierID sur le i-parapheur"
            );

            $result = $this->getClient()->ArchiverDossier([
                "DossierID" => $dossierID,
                "ArchivageAction" => self::ARCHIVAGE_ACTION_EFFACER
            ]);
            $this->getLogger()->debug("Réponse de l'archivage du dossier $dossierID: " . json_encode($result));
            if (empty($result->MessageRetour->codeRetour) || $result->MessageRetour->codeRetour != 'OK') {
                $this->lastError = "Impossible d'archiver le dossier $dossierID sur le iparapheur : " . json_encode($result);
                $this->getLogger()->notice($this->lastError);
                return false;
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
        return $result;
    }

    public function effacerDossierRejete($dossierID)
    {
        try {
            $this->getLogger()->debug("Effacement du dossier $dossierID rejeté");
            $result = $this->getClient()->EffacerDossierRejete($dossierID);
            $this->getLogger()->debug("Résultat de l'effacement du dossier $dossierID : " . json_encode($result));
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->getLogger()->notice("Impossible d'effacer le dossier $dossierID : " . $e->getMessage());
            return false;
        }
        return $result;
    }

    /**
     * @param $dossierID
     * @return bool
     * @throws Exception
     */
    public function exercerDroitRemordDossier($dossierID): bool
    {
        $result =  $this->getClient()->ExercerDroitRemordDossier($dossierID);
        $messageRetour = $result->MessageRetour;
        $message = "[{$messageRetour->severite}] {$messageRetour->message}";
        if ($messageRetour->codeRetour == 'KO') {
            $this->lastError = $message;
            return false;
        } elseif ($messageRetour->codeRetour == 'OK') {
            return true;
        } else {
            $this->lastError = "Le iparapheur n'a pas retourné de code de retour : $message";
            return false;
        }
    }

    public function getAllHistoriqueInfo($dossierID)
    {
        try {
            $result =  $this->getClient()->GetHistoDossier($dossierID);
            if (empty($result->LogDossier)) {
                $this->lastError = "Le dossier n'a pas été trouvé";
                return false;
            }
            return $result;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function getLastHistorique($all_historique)
    {
        if (isset($all_historique->LogDossier->timestamp)) {
            $lastLog = $all_historique->LogDossier;
        } else {
            $lastLog = end($all_historique->LogDossier);
        }
        $date = date("d/m/Y H:i:s", strtotime($lastLog->timestamp));
        return $date . " : [" . $lastLog->status . "] " . $lastLog->annotation;
    }

    public function getHistorique($dossierID)
    {
        try {
            $result =  $this->getClient()->GetHistoDossier($dossierID);

            if (empty($result->LogDossier)) {
                $this->lastError = "Le dossier n'a pas été trouvé";
                return false;
            }
            return $this->getLastHistorique($result);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function setSendingMetadata(DonneesFormulaire $donneesFormulaire)
    {
        $all_metadata = explode(",", $this->iparapheur_metadata);
        $result = [];
        foreach ($all_metadata as $metadata_association) {
            $data = explode(":", $metadata_association);
            if (count($data) < 2) {
                continue;
            }
            $element_pastell = $data[0];
            $metadata_parapheur = $data[1];
            if ($element_pastell && $metadata_parapheur) {
                $result[$metadata_parapheur] = $donneesFormulaire->get($element_pastell);
            }
        }

        $this->sending_metadata = $result;
    }

    public function getSendingMetadata()
    {
        return $this->sending_metadata;
    }

    /**
     * @param FileToSign $fileToSign
     * @return bool|string
     * @throws SignatureException
     * @throws Exception
     */
    public function sendDossier(FileToSign $fileToSign)
    {
        $client = $this->getClient();
        $data = [
            'TypeTechnique' => $fileToSign->type,
            'SousType' => $fileToSign->sousType,
            'DossierID' => $fileToSign->dossierId,
            'DocumentPrincipal' => [
                '_' => $fileToSign->document->content,
                'contentType' => $fileToSign->document->contentType
            ],
            'Visibilite' => $this->visibilite
        ];

        if ($fileToSign->document->filename) {
            $data['NomDocPrincipal'] = $fileToSign->document->filename;
        }
        if ($fileToSign->signature_content) {
            $data['SignatureDocPrincipal'] = [
                "_" => $fileToSign->signature_content,
                "contentType" => $fileToSign->signature_type
            ];
        }

        if ($fileToSign->dossierTitre) {
            $data['DossierTitre'] = TextTruncator::truncate($fileToSign->dossierTitre, self::MAX_TITLE_LENGTH);
        }

        if ($fileToSign->date_limite) {
            $data['DateLimite'] = $fileToSign->date_limite;
        }

        if ($fileToSign->document->contentType == 'application/xml' && !$fileToSign->visualPdf->content) {
            $fileToSign->visualPdf->content = $this->collectiviteProperties->getFileContent('visuel_pdf_default');
        }

        if ($fileToSign->visualPdf->content) {
            $data['VisuelPDF'] = [
                '_' => $fileToSign->visualPdf->content,
                'contentType' => 'application/pdf'
            ];
        }

        if ($fileToSign->document->contentType == 'application/xml' && !$fileToSign->xPathPourSignatureXML) {
            $fileToSign->xPathPourSignatureXML = $this->getXPathPourSignatureXML($fileToSign->document->content);
        }

        if ($fileToSign->xPathPourSignatureXML) {
            $data['XPathPourSignatureXML'] = $fileToSign->xPathPourSignatureXML;
        }

        if ($fileToSign->annotationPublic) {
            $data['AnnotationPublique'] = $fileToSign->annotationPublic;
        }

        if ($fileToSign->annotationPrivee) {
            $data['AnnotationPrivee'] = $fileToSign->annotationPrivee;
        }

        if ($fileToSign->emailEmetteur) {
            $data['EmailEmetteur'] = $fileToSign->emailEmetteur;
        }

        $balise_annexes = ($this->iparapheur_multi_doc) ? 'DocumentsSupplementaires' : 'DocumentsAnnexes';
        foreach ($fileToSign->annexes as $annexe) {
            $data[$balise_annexes][] = [
                'nom' => $annexe->filename,
                'fichier' => [
                    '_' => $annexe->content,
                    'contentType' => $annexe->contentType
                ],
                'mimetype' => $annexe->contentType,
                'encoding' => 'UTF-8'
            ];
        }

        if ($this->sending_metadata) {
            $fileToSign->metadata = $this->sending_metadata;
        }

        if ($fileToSign->metadata) {
            $data['MetaData'] = [
                'MetaDonnee' => []
            ];

            foreach ($fileToSign->metadata as $nom => $valeur) {
                $data['MetaData']['MetaDonnee'][] = [
                    'nom' => $nom,
                    'valeur' => $valeur
                ];
            }
        }
        $this->getLogger()->debug(
            "Appel à la méthode CreerDossier (DossierID = $fileToSign->dossierId)"
        );

        $result = $client->CreerDossier($data);

        $this->getLogger()->debug(
            "Réponse à la méthode CreerDossier $fileToSign->dossierId : " . json_encode($result)
        );

        $messageRetour = $result->MessageRetour;
        $message = sprintf(
            "[%s] %s",
            $messageRetour->severite,
            $messageRetour->message
        );

        if ($messageRetour->codeRetour == 'KO') {
            throw new SignatureException($message);
        } elseif ($messageRetour->codeRetour == 'OK') {
            return $fileToSign->dossierId;
        } else {
            $this->lastError = "Le iparapheur n'a pas retourné de code de retour : $message";
            return false;
        }
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function sendDocumentTest(): ?string
    {
        if (! $this->iparapheur_type) {
            throw new UnrecoverableException("Il faut d'abord choisir un type avant de procéder à un test d'envoi.");
        }

        $sous_type = $this->getSousType();
        if (! $sous_type) {
            throw new UnrecoverableException(
                "Le type {$this->iparapheur_type} ne contient aucun sous-type. Impossible de faire un test d'envoi."
            );
        }

        $fileToSign = new FileToSign();

        $fileToSign->document = new Fichier();
        $fileToSign->document->filename = 'test.pdf';
        $fileToSign->document->filepath = $this->getDataDir() . '/connector/iparapheur/test.pdf';
        $fileToSign->document->content = file_get_contents($fileToSign->document->filepath);
        $fileToSign->document->contentType = 'application/pdf';

        $fileToSign->visualPdf = new Fichier();

        $fileToSign->type = $this->iparapheur_type;
        $fileToSign->sousType = $sous_type[0];
        $fileToSign->dossierId = date('YmdHis') . mt_rand(0, mt_getrandmax());
        $fileToSign->dossierTitre = 'Test de dépôt pastell ' . date(DATE_ISO8601);

        $result = $this->sendDossier($fileToSign);
        if ($result) {
            return "Envoi OK : " . $this->getStringFromFileToSign($fileToSign);
        }

        $pdf_last_error = $this->getLastError();

        /*
         * On a aucun moyen de savoir s'il faut envoyer du PDF ou du XML dans le circuit,
         * du coup, on teste d'abord avec du PDF et si ca marche pas, on envoie du XML
         */
        $fileToSign->document->filename = 'test-pastell-i-parapheur.xml';
        $fileToSign->document->filepath = $this->getDataDir() . '/connector/iparapheur/PES_ex.xml';
        $fileToSign->document->content = file_get_contents($fileToSign->document->filepath);
        $fileToSign->document->contentType = 'application/xml';

        $result = $this->sendDossier($fileToSign);
        if ($result) {
            return "Envoi OK : " . $this->getStringFromFileToSign($fileToSign);
        }

        $this->lastError = "Erreur lors de l'envoi d'un fichier PDF : $pdf_last_error\n" .
            "Erreur lors de l'envoi d'un fichier XML {$this->lastError}\n" .
            $this->getStringFromFileToSign($fileToSign);
        return null;
    }

    private function getStringFromFileToSign(FileToSign $fileToSign)
    {
        return sprintf(
            "Type : %s - Sous-type: %s - DossierID : %s - Titre : %s",
            $fileToSign->type,
            $fileToSign->sousType,
            $fileToSign->dossierId,
            $fileToSign->dossierTitre
        );
    }



        /**
     * @return NotBuggySoapClient
     * @throws Exception
     */
    protected function getClient()
    {
//      static $client;
// var_dump($client);
//      if ($client) {
//          return $client;
//      }
        if (! $this->wsdl) {
            $this->lastError = "Le WSDL n'a pas été fourni";
            throw new Exception("Le WSDL n'a pas été fourni");
        }

        /*
         * En PHP 5.6, SoapClient vérifie forcément le peer lors de la récupération du WDSL
         */
        $stream_context = stream_context_create(
            [
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false
                ]
            ]
        );


        $client = $this->soapClientFactory->getInstance(
            $this->wsdl,
            [
                    'login' => $this->login_http,
                    'password' => $this->password_http,
                    'trace' => 1,
                    'exceptions' => 1,
                    'use_curl' => 1,
                    "stream_context" => $stream_context
                ]
        );

// echo '<pre>';
// var_dump($client);
// // die();
        $this->last_client = $client;
        return $client;
    }

    public function getType(): array
    {
        $result = [];
        try {
            $listeType = $this->getClient()->GetListeTypes();
            if (!isset($listeType->TypeTechnique)) {
                throw new Exception("Aucun type trouvé");
            }
            $type = $listeType->TypeTechnique;
            if (is_array($type)) {
                foreach ($type as $n => $v) {
                    $result[$n] = $v;
                }
            } else {
                $result[0] = $type;
            }
            sort($result);
            return $result;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return $result;
        }
    }

    /**
     * @return array|bool
     */
    public function getSousType()
    {
        $type = $this->iparapheur_type;
        try {
            $listeSousType = $this->getClient()->GetListeSousTypes($type);
            if (empty($listeSousType->SousType)) {
                throw new Exception("Aucun sous-type trouvé pour le type $type");
            }
            $sousType = $listeSousType->SousType;

            $result = [];
            if (is_array($sousType)) {
                foreach ($sousType as $n => $v) {
                    $result[$n] = $v;
                }
            } else {
                $result[0] = $sousType;
            }
            sort($result);
            return $result;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function getSousTypeAPI(): array
    {
        $result = $this->getSousType();
        return is_array($result) ? $result : [];
    }


    public function testConnexion()
    {
        $client = $this->getClient();
        return $client->echo(
            "Dès Noël où un zéphyr haï me vêt de glaçons würmiens je dîne d’exquis rôtis de bœuf au kir à l’aÿ d’âge mûr & cætera"
        );
    }

    public function getLogin()
    {
        return $this->login_http;
    }

    /**
     * @param $pes_content
     * @return string
     * @throws Exception
     */
    public function getXPathPourSignatureXML($pes_content)
    {
        if ($this->xPathPourSignatureXML == 2) {
            return "//Bordereau";
        }
        if ($this->xPathPourSignatureXML == 3) {
            return ".";
        }
        return $this->getXPathPourSignatureXMLBestMethod($pes_content);
    }

    /**
     * @param $pes_content
     * @return string
     * @throws Exception
     */
    public function getXPathPourSignatureXMLBestMethod($pes_content)
    {
        $xml = simplexml_load_string($pes_content, 'SimpleXMLElement', LIBXML_PARSEHUGE);

        if ($this->allBordereauHasId($xml)) {
            return "//Bordereau";
        }
        if (! empty($xml['Id'])) {
            return ".";
        }

        throw new Exception(
            "Le bordereau du fichier PES ne contient pas d'identifiant valide, ni la balise PESAller : signature impossible"
        );
    }

    /**
     * @param $simple_xml_pes_content
     * @return bool
     * @throws Exception
     */
    private function allBordereauHasId($simple_xml_pes_content)
    {
        if ($simple_xml_pes_content->PES_DepenseAller) {
            $root = $simple_xml_pes_content->PES_DepenseAller;
        } elseif ($simple_xml_pes_content->PES_RecetteAller) {
            $root = $simple_xml_pes_content->PES_RecetteAller;
        } else {
            throw new Exception("Le bordereau ne contient ni Depense ni Recette");
        }

        foreach ($root->Bordereau as $bordereau) {
            $attr = $bordereau->attributes();
            if (empty($attr['Id'])) {
                return false;
            }
        }
        return true;
    }

    public function getLastRequest()
    {
        $dom = new DOMDocument();
        $dom->loadXML($this->last_client->__getLastRequest());
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    public function isFinalState(string $lastState): bool
    {
        return strstr($lastState, '[Archive]');
    }

    public function isRejected(string $lastState): bool
    {
        preg_match("/\[([^]]*)]/", $lastState, $matches);
        if (! $matches) {
            return false;
        }
        return (in_array($matches[1], self::REJECTED_STATE, true));
    }

    public function isDetached($signature): bool
    {
        return $signature['signature'] && !$signature['is_pes'];
    }

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $file
     * @return mixed
     */
    public function getDetachedSignature($file)
    {
        return $file['signature'];
    }

    /**
     * Workaround because IParapheur::getSignature() does not return only the signature
     *
     * @param $file
     * @return mixed
     */
    public function getSignedFile($file)
    {
        return $file['signature'] ?: $file['document_signe']['document'];
    }

    /**
     * Workaround because it is embedded in IParapheur::getSignature()
     *
     * @param $signature
     * @return Fichier
     */
    public function getBordereauFromSignature($signature): ?Fichier
    {
        $file = new Fichier();
        $file->filename = $signature['nom_document'];
        $file->content = $signature['document'];
        return $file;
    }

    /**
     * @param array $info_from_get_signature output of IParapheur::getSignature()
     * @return bool
     */
    public function hasMultiDocumentSigne($info_from_get_signature): bool
    {
        return (($this->iparapheur_multi_doc) && (!empty($info_from_get_signature['multi_document_signe'])));
    }

    /**
     * @param array $info_from_get_signature output of IParapheur::getSignature()
     * @return array $all_document_signe
     * Au retour du i-parapheur les fichiers DocPrincipal et DocumentsSupplementaires peuvent être inversés
     */
    public function getAllDocumentSigne(array $info_from_get_signature): array
    {
        $all_document_signe = $info_from_get_signature['multi_document_signe'];
        $all_document_signe[] = $info_from_get_signature['document_signe'];
        return $all_document_signe;
    }
}
