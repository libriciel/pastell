<?php

class SignatureEnvoie extends ConnecteurTypeActionExecutor
{
    /**
     * @var string
     */
    public const SEND_SIGNATURE_ERROR_STATE = 'send-signature-error';

    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function go()
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');

        $donneesFormulaire = $this->getDonneesFormulaire();

        $document_element = $this->getMappingValue('document');
        $objet_element = $this->getMappingValue('objet');
        $visuel_pdf_element = $this->getMappingValue('visuel_pdf');
        $iparapheur_type_element = $this->getMappingValue('iparapheur_type');
        $iparapheur_sous_type_element = $this->getMappingValue('iparapheur_sous_type');
        $fast_parapheur_circuit = $this->getMappingValue('fast_parapheur_circuit');
        $fast_parapheur_circuit_configuration = $this->getMappingValue('fast_parapheur_circuit_configuration');
        $fastParapheurEmailRecipients = $this->getMappingValue('fast_parapheur_email_destinataire');
        $fastParapheurEmailCc = $this->getMappingValue('fast_parapheur_email_cc');
        $fastParapheurAgents = $this->getMappingValue('fast_parapheur_agents');
        $has_date_limite = $this->getMappingValue('iparapheur_has_date_limite');
        $iparapheur_date_limite = $this->getMappingValue('iparapheur_date_limite');
        $annexe_element = $this->getMappingValue('autre_document_attache');
        $autre_document_a_signer_element = $this->getMappingValue('autre_document_a_signer');
        $primo_signature_detachee = $this->getMappingValue('primo_signature_detachee');
        $json_metadata = $this->getMappingValue('json_metadata');
        $iparapheur_dossier_id = $this->getMappingValue('iparapheur_dossier_id');
        $iparapheur_annotation_publique = $this->getMappingValue('iparapheur_annotation_publique');
        $iparapheur_annotation_privee = $this->getMappingValue('iparapheur_annotation_privee');

        $fileToSign = new FileToSign();
        $fileToSign->type = $donneesFormulaire->get($iparapheur_type_element);
        $fileToSign->sousType = $donneesFormulaire->get($iparapheur_sous_type_element);
        $fileToSign->circuit = $donneesFormulaire->get($fast_parapheur_circuit);

        $fileToSign->circuit_configuration = $donneesFormulaire->get($fast_parapheur_circuit_configuration)
            ? $this->createFichier($donneesFormulaire, $fast_parapheur_circuit_configuration)
            : new Fichier();
        $fileToSign->emailRecipients = $donneesFormulaire->get($fastParapheurEmailRecipients);
        $fileToSign->emailCc = $donneesFormulaire->get($fastParapheurEmailCc);
        $fileToSign->agents = $donneesFormulaire->get($fastParapheurAgents);

        $fileToSign->document = $this->createFichier($donneesFormulaire, $document_element);

        $fileToSign->visualPdf = $donneesFormulaire->get($visuel_pdf_element)
            ? $this->createFichier($donneesFormulaire, $visuel_pdf_element)
            : new Fichier();

        if ($donneesFormulaire->get($annexe_element)) {
            foreach (array_keys($donneesFormulaire->get($annexe_element)) as $num) {
                $fileToSign->annexes[] = $this->createFichier($donneesFormulaire, $annexe_element, $num);
            }
        }

        if ($autre_document_a_signer_element && $donneesFormulaire->get($autre_document_a_signer_element)) {
            foreach (array_keys($donneesFormulaire->get($autre_document_a_signer_element)) as $num) {
                $fileToSign->multiDocuments[] = $this->createFichier(
                    $donneesFormulaire,
                    $autre_document_a_signer_element,
                    $num
                );
            }
        }

        if ($donneesFormulaire->get($primo_signature_detachee)) {
            $fileToSign->signature_content = $donneesFormulaire->getFileContent($primo_signature_detachee);
            $fileToSign->signature_type = $donneesFormulaire->getContentType($primo_signature_detachee);
            if ($fileToSign->signature_type !== 'application/xml') {
                $fileToSign->signature_type = 'application/pkcs7-signature';
            }
        }

        $fileToSign->dossierTitre = $donneesFormulaire->get($objet_element);

        $fileToSign->metadata = json_decode(
            $donneesFormulaire->getFileContent($json_metadata),
            true
        ) ?? [];

        $fileToSign->dossierId = date('YmdHis') . random_int(0, mt_getrandmax());

        $signature->setSendingMetadata($donneesFormulaire);

        if ($donneesFormulaire->get($has_date_limite)) {
            $fileToSign->date_limite = $donneesFormulaire->get($iparapheur_date_limite);
        }

        if ($donneesFormulaire->get($iparapheur_annotation_publique)) {
            $fileToSign->annotationPublic = $donneesFormulaire->get($iparapheur_annotation_publique);
        }

        if ($donneesFormulaire->get($iparapheur_annotation_privee)) {
            $fileToSign->annotationPrivee = $donneesFormulaire->get($iparapheur_annotation_privee);
        }
        try {
            $result = $signature->sendDossier($fileToSign);
        } catch (SignatureException $e) {
            $sendSignatureError = $this->getMappingValue(self::SEND_SIGNATURE_ERROR_STATE);
            $this->changeAction($sendSignatureError, $e->getMessage());
            $this->notify($sendSignatureError, $this->type, $e->getMessage());
            return false;
        }
        if (!$result) {
            $this->setLastMessage("La connexion avec le parapheur a échoué : " . $signature->getLastError());
            return false;
        }

        $donneesFormulaire->setData($iparapheur_dossier_id, $result);
        $this->addActionOK("Le document a été envoyé au parapheur électronique");
        $this->notify($this->action, $this->type, "Le document a été envoyé au parapheur électronique");
        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    private function createFichier(DonneesFormulaire $donneesFormulaire, string $element, int $num = 0): Fichier
    {
        $fichier = new Fichier();
        $fichier->filename = $donneesFormulaire->getFileName($element, $num);
        $fichier->filepath = $donneesFormulaire->getFilePath($element, $num);
        $fichier->content = $donneesFormulaire->getFileContent($element, $num);
        $fichier->contentType = $donneesFormulaire->getContentType($element, $num);
        return $fichier;
    }
}
