<?php

class SignatureRecuperation extends ConnecteurTypeActionExecutor
{
    public const ACTION_NAME_RECU = 'recu-iparapheur';
    public const ACTION_NAME_REJET = 'rejet-iparapheur';
    public const ACTION_NAME_ERROR = 'erreur-verif-iparapheur';

    private $action_name;

    /** @deprecated Since 4.1.3, Unused, Use file 'iparapheur_metadata_sortie' instead */
    private $iparapheur_metadata_sortie;

    /**
     * @return bool
     * @throws Exception
     * @throws RecoverableException
     */
    public function go()
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteurOrFail('signature');

        $donneesFormulaire = $this->getDonneesFormulaire();

        $document_element = $this->getMappingValue('document');
        $titre_element = $this->getMappingValue('titre');
        $has_historique_element = $this->getMappingValue('has_historique');
        $iparapheur_historique_element = $this->getMappingValue('iparapheur_historique');
        $parapheur_last_message_element = $this->getMappingValue('parapheur_last_message');
        $parapheur_date_signature_element = $this->getMappingValue('parapheur_date_signature');
        $has_signature_element = $this->getMappingValue('has_signature');
        $signature_element = $this->getMappingValue('signature');
        $document_orignal_element = $this->getMappingValue('document_original');
        $bordereau_element = $this->getMappingValue('bordereau');
        $annexe_element = $this->getMappingValue('autre_document_attache');
        $multi_document_original_element = $this->getMappingValue('multi_document_original');
        $iparapheur_annexe_sortie_element = $this->getMappingValue('iparapheur_annexe_sortie');
        $iparapheur_metadata_sortie_element = $this->getMappingValue('iparapheur_metadata_sortie');
        $iparapheur_dossier_id = $this->getMappingValue('iparapheur_dossier_id');


        if (
            $donneesFormulaire->getFormulaire()->getField($iparapheur_dossier_id) &&
            $donneesFormulaire->get($iparapheur_dossier_id)
        ) {
            $dossierID = $donneesFormulaire->get($iparapheur_dossier_id);
        } else { // conservé pour compatibilité
            $filename = $donneesFormulaire->getFileName($document_element);
            $dossierID = $signature->getDossierID($donneesFormulaire->get($titre_element), $filename);
        }

        $erreur = false;
        $all_historique = [];
        try {
            $all_historique = $signature->getAllHistoriqueInfo($dossierID);
            if (! $all_historique) {
                $erreur = 'La connexion avec le iParapheur a échoué : ' . $signature->getLastError();
            }
        } catch (Exception $e) {
            $erreur = $e->getMessage();
        }
        if (! $erreur) {
            $array2XML = new Array2XML();
            $historique_xml = $array2XML->getXML(
                $iparapheur_historique_element,
                json_decode(
                    json_encode($all_historique, JSON_THROW_ON_ERROR),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );
            $donneesFormulaire->setData($has_historique_element, true);
            $donneesFormulaire->setData($has_signature_element, true); // conservé pour compatibilité
            $donneesFormulaire->addFileFromData(
                $iparapheur_historique_element,
                $this->getComputedFileName('iparapheur_historique.xml'),
                $historique_xml
            );
            $lastState = $signature->getLastHistorique($all_historique);
            $donneesFormulaire->setData($parapheur_last_message_element, $lastState);
        } else {
            $lastState = false;
        }

        $return = false;
        if ($signature->isFinalState($lastState)) {
            $donneesFormulaire->setData(
                $parapheur_date_signature_element,
                $signature->getDateSignature($all_historique)
            );
            //Traitement des $iparapheur_annexe_sortie_element
            //avant addMultiDocumentSigne (modification de $annexe_element)
            $return = $this->addFinalElements(
                $dossierID,
                $bordereau_element,
                $annexe_element,
                $iparapheur_annexe_sortie_element,
                $iparapheur_metadata_sortie_element
            );
            if ($return === true) {
                $return = $this->retrieveDossier(
                    $dossierID,
                    $has_signature_element,
                    $signature_element,
                    $document_element,
                    $document_orignal_element,
                    $multi_document_original_element,
                    $annexe_element
                );
            }
            return $return;
        }
        if ($signature->isRejected($lastState)) {
            $refusal_message = $signature->getRefusalMessage($dossierID);
            $lastState = trim("$lastState $refusal_message");
            $this->setLastMessage($lastState);
            $donneesFormulaire->setData($parapheur_last_message_element, $lastState);
            $return = $this->addFinalElements(
                $dossierID,
                $bordereau_element,
                $annexe_element,
                $iparapheur_annexe_sortie_element,
                $iparapheur_metadata_sortie_element
            );
            if ($return === true) {
                $return = $this->rejeteDossier($dossierID, $lastState);
            }
            return $return;
        }

        $nb_jour_max = $signature->getNbJourMaxInConnecteur();
        $lastAction = $this->getDocumentActionEntite()->getLastActionInfo($this->id_e, $this->id_d);
        $time_action = strtotime($lastAction['date']);
        if (time() - $time_action > $nb_jour_max * 86400) {
            $erreur = sprintf(
                'Aucune réponse disponible sur le parapheur depuis %s jours !',
                $nb_jour_max
            );
            $this->changeOrUpdateAction(
                $this->getMappingValue(self::ACTION_NAME_ERROR),
                $erreur
            );
            $this->notify($this->getMappingValue(self::ACTION_NAME_ERROR), $this->type, $erreur);
        }

        if (! $erreur) {
            $this->setLastMessage($lastState);
            return true;
        }

        $this->setLastMessage($erreur);
        return false;
    }

    /**
     * @throws Exception
     */
    public function rejeteDossier(
        $dossierID,
        $lastState
    ): bool {

        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');
        $signature->effacerDossierRejete($dossierID);

        $message = 'Le document a été rejeté dans le parapheur : ' . $lastState;
        $this->getActionCreator()->addAction(
            $this->id_e,
            $this->id_u,
            $this->getMappingValue(self::ACTION_NAME_REJET),
            $message
        );
        $this->notify($this->getMappingValue(self::ACTION_NAME_REJET), $this->type, $message);
        $this->action_name = self::ACTION_NAME_REJET;

        return true;
    }

    /**
     * @throws RecoverableException
     * @throws Exception
     */
    public function retrieveDossier(
        $dossierID,
        $has_signature_element,
        $signature_element,
        $document_element,
        $document_orignal_element,
        $multi_document_original_element,
        $annexe_element
    ): bool {

        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');
        $donneesFormulaire = $this->getDonneesFormulaire();

        $info = $signature->getSignature($dossierID, false);

        if (!$info) {
            $this->setLastMessage("La signature n'a pas pu être récupérée : " . $signature->getLastError());
            return false;
        }

        $donneesFormulaire->setData($has_signature_element, true);
        if ($signature->isDetached($info)) {
            $donneesFormulaire->addFileFromData(
                $signature_element,
                $this->getComputedFileName('signature.zip'),
                $signature->getDetachedSignature($info)
            );
        } else {
            $document_original_name = $donneesFormulaire->getFileName($document_element);
            $document_original_data = $donneesFormulaire->getFileContent($document_element);
            $filename = pathinfo($document_original_name, PATHINFO_FILENAME);
            $extension = pathinfo($document_original_name, PATHINFO_EXTENSION);
            $filename_orig = sprintf('%s_orig.%s', $filename, $extension);
            $filename_orig = $this->getComputedFileName($filename_orig);

            if (!$donneesFormulaire->getFileName($document_orignal_element)) {
                $donneesFormulaire->addFileFromData($document_orignal_element, $filename_orig, $document_original_data);
            }

            if ($signature->hasMultiDocumentSigne($info)) {
                $this->addMultiDocumentSigne(
                    $signature->getAllDocumentSigne($info),
                    $multi_document_original_element,
                    $document_element,
                    $annexe_element
                );
            } else {
                $this->addMainSignedFile($signature->getSignedFile($info), $document_element);
            }
        }

        if (!$signature->archiver($dossierID)) {
            throw new RecoverableException(
                "Impossible d'archiver la transaction sur le parapheur : " . $signature->getLastError()
            );
        }

        $this->setLastMessage('La signature a été récupérée');
        $this->notify($this->getMappingValue(self::ACTION_NAME_RECU), $this->type, 'La signature a été récupérée');
        $this->getActionCreator()->addAction(
            $this->id_e,
            $this->id_u,
            $this->getMappingValue(self::ACTION_NAME_RECU),
            'La signature a été récupérée sur le parapheur électronique'
        );

        $this->action_name = self::ACTION_NAME_RECU;
        return true;
    }

    private function getComputedFileName(string $file): string
    {
        $matches = [];
        if (preg_match('/(.*)_(\d+)/', $this->action, $matches)) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $extension = pathinfo($file, PATHINFO_EXTENSION);

            $file = sprintf('%s_%s.%s', $filename, $matches[2], $extension);
        }
        return $file;
    }

    /**
     * @return mixed|string
     */
    public function getActionName()
    {
        return $this->action_name;
    }

    /**
     * @param $nomMetaDonnee
     * @return bool|string
     */
    /** @deprecated Since 4.1.3, Unused, Use file 'iparapheur_metadata_sortie' instead */
    public function getMetaDonnee($nomMetaDonnee)
    {
        if ($this->iparapheur_metadata_sortie) {
            foreach ($this->iparapheur_metadata_sortie as $metaDonnee) {
                if (($metaDonnee['nom']) === $nomMetaDonnee) {
                    return $metaDonnee['valeur'];
                }
            }
        }
        return false;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws Exception
     */
    private function addFinalElements(
        $dossierID,
        $bordereau_element,
        $annexe_element,
        $iparapheur_annexe_sortie_element,
        $iparapheur_metadata_sortie_element
    ): bool {

        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');
        $donneesFormulaire = $this->getDonneesFormulaire();

        $info = $signature->getSignature($dossierID, false);
        if (!$info) {
            $this->setLastMessage('Le bordereau n\'a pas pu être récupéré : ' . $signature->getLastError());
            return false;
        }
        $bordereau = $signature->getBordereauFromSignature($info, $dossierID);
        if ($bordereau) {
            $donneesFormulaire->addFileFromData($bordereau_element, $bordereau->filename, $bordereau->content);
        }

        $annexeElementHash = [];
        if ($donneesFormulaire->get($annexe_element)) {
            foreach ($donneesFormulaire->get($annexe_element) as $i => $name) {
                $annexeElementHash[] = hash('sha256', $donneesFormulaire->getFileContent($annexe_element, $i));
            }
        }
        $i = 0;
        foreach ($signature->getOutputAnnexe($info, 0) as $annexe) {
            if (!in_array(hash('sha256', $annexe['document']), $annexeElementHash, true)) {
                $donneesFormulaire->addFileFromData(
                    $iparapheur_annexe_sortie_element,
                    $annexe['nom_document'],
                    $annexe['document'],
                    $i++
                );
            }
        }

        $metadataSortie = $signature->getMetadataSortie($info);
        if ($metadataSortie !== null) {
            $this->getDonneesFormulaire()->addFileFromData(
                $iparapheur_metadata_sortie_element,
                $metadataSortie->filename,
                $metadataSortie->content
            );
        }
        /** @deprecated Since 4.1.3, Unused */
        if (isset($info['meta_donnees'])) {
            $this->iparapheur_metadata_sortie = $info['meta_donnees'];
        }

        return true;
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    private function addMultiDocumentSigne(
        array $allSignedFiles,
        string $multi_document_original_element,
        string $document_element,
        string $annexe_element
    ): void {
        $donneesFormulaire = $this->getDonneesFormulaire();
        // Copie des $annexe_element dans $multi_document_original_element
        if ($donneesFormulaire->get($annexe_element)) {
            foreach ($donneesFormulaire->get($annexe_element) as $num => $fileName) {
                $annexe_original_name = $donneesFormulaire->getFileName($annexe_element, $num);
                $annexe_original_data = $donneesFormulaire->getFileContent($annexe_element, $num);
                $filename = pathinfo($annexe_original_name, PATHINFO_FILENAME);
                $extension = pathinfo($annexe_original_name, PATHINFO_EXTENSION);
                $filename_orig = sprintf('%s_orig.%s', $filename, $extension);
                $filename_orig = $this->getComputedFileName($filename_orig);
                $donneesFormulaire->addFileFromData(
                    $multi_document_original_element,
                    $filename_orig,
                    $annexe_original_data,
                    $num
                );
            }
        }

        $i = 0;
        foreach ($allSignedFiles as $file) {
            /** @var Fichier $file */
            $fileBasename = pathinfo($file->filename, PATHINFO_FILENAME);
            $originalFileBasename = pathinfo($donneesFormulaire->getFileName($document_element), PATHINFO_FILENAME);
            if ($fileBasename === $originalFileBasename) {
                $this->addMainSignedFile($file, $document_element);
                continue;
            }
            $donneesFormulaire->addFileFromData(
                $annexe_element,
                $file->filename,
                $file->content,
                $i
            );
            $i++;
        }
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    private function addMainSignedFile(
        Fichier $main_file,
        string $document_element
    ): void {
        $donneesFormulaire = $this->getDonneesFormulaire();
        $document_original_name = $donneesFormulaire->getFileName($document_element);
        $donneesFormulaire->addFileFromData(
            $document_element,
            $main_file->filename ?: $document_original_name,
            $main_file->content
        );
    }
}
