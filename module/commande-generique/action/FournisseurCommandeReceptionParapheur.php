<?php

class FournisseurCommandeReceptionParapheur extends ActionExecutor
{
    private const string ACTION_NAME_REJECT = 'rejet-iparapheur';

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

        $dossierID = $donneesFormulaire->get('iparapheur_dossier_id');

        $error = false;
        $all_historique = false;
        try {
            $all_historique = $signature->getAllHistoriqueInfo($dossierID);
            if (! $all_historique) {
                $error = 'La connexion avec le iParapheur a échoué : ' . $signature->getLastError();
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        $lastHistorique = false;
        $lastCompletedHistorique = false;
        if (!$error) {
            $array2XML = new Array2XML();
            $historique_xml = $array2XML->getXML(
                'iparapheur_historique',
                json_decode(
                    json_encode($all_historique, JSON_THROW_ON_ERROR),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );
            $donneesFormulaire->setData('has_historique', true);
            $donneesFormulaire->addFileFromData(
                'iparapheur_historique',
                'iparapheur_historique.xml',
                $historique_xml
            );
            $lastHistorique = $signature->getLastHistorique($all_historique);
            $lastCompletedHistorique = $signature->getLastCompletedHistorique($all_historique);
            $donneesFormulaire->setData('parapheur_last_message', $lastCompletedHistorique);
        }

        if ($signature->isFinalState($lastHistorique)) {
            return $this->retrieveDossier($dossierID);
        }
        if ($signature->isRejected($lastHistorique)) {
            return $this->rejeteDossier($dossierID, $lastCompletedHistorique);
        }

        $this->traitementErreur($signature, $lastHistorique);


        if (!$error) {
            $this->setLastMessage($lastCompletedHistorique);
            return true;
        }
        $this->setLastMessage($error);
        return false;
    }

    /**
     * @throws Exception
     */
    public function traitementErreur(SignatureConnecteur $signature, $message): bool
    {
        $nb_jour_max = $signature->getNbJourMaxInConnecteur();
        $lastAction = $this->getDocumentActionEntite()->getLastActionInfo($this->id_e, $this->id_d);
        $time_action = strtotime($lastAction['date']);
        if (time() - $time_action > $nb_jour_max * 86400) {
            $message = "Aucune réponse disponible sur le parapheur depuis $nb_jour_max !";
            $this->changeOrUpdateAction('erreur-verif-iparapheur', $message);
            $this->notify($this->action, $this->type, $message);
            throw new Exception($message);
        }
        $this->setLastMessage($message);
        return true;
    }

    /**
     * @throws Exception
     */
    public function rejeteDossier($dossierID, $lastState): bool
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');
        $donneesFormulaire = $this->getDonneesFormulaire();

        $info = $signature->getSignature($dossierID);
        if (! $info) {
            $this->setLastMessage("Le bordereau n'a pas pu être récupéré : " . $signature->getLastError());
            return false;
        }

        $bordereau = $signature->getBordereauFromSignature($info, $dossierID);
        if ($bordereau) {
            $donneesFormulaire->addFileFromData('bordereau', $bordereau->filename, $bordereau->content);
        }

        $signature->effacerDossierRejete($dossierID);

        $message = 'Le document a été rejeté dans le parapheur : ' . $lastState;
        $this->changeAction(self::ACTION_NAME_REJECT, $message,);
        $this->notify(self::ACTION_NAME_REJECT, $this->type, $message);

        return true;
    }

    /**
     * @throws Exception
     * @throws RecoverableException
     */
    public function retrieveDossier($dossierID): bool
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');
        $donneesFormulaire = $this->getDonneesFormulaire();

        $document_element = 'commande';
        $document_orignal_element = 'document_orignal';

        $info = $signature->getSignature($dossierID);
        if (! $info) {
            $this->setLastMessage("La signature n'a pas pu être récupérée : " . $signature->getLastError());
            return false;
        }

        $donneesFormulaire->setData('has_signature', true);
        if ($signature->isDetached($info)) {
            $donneesFormulaire->addFileFromData(
                'signature',
                'signature.zip',
                $signature->getDetachedSignature($info)
            );
        } else {
            $document_original_name = $donneesFormulaire->getFileName($document_element);
            $document_original_data = $donneesFormulaire->getFileContent($document_element);
            $filename = pathinfo($document_original_name, PATHINFO_FILENAME);
            $extension = pathinfo($document_original_name, PATHINFO_EXTENSION);

            if (!$donneesFormulaire->getFileName($document_orignal_element)) {
                $donneesFormulaire->addFileFromData(
                    $document_orignal_element,
                    $document_original_name,
                    $document_original_data
                );
            }

            $filename_signe = preg_replace('#[^a-zA-Z0-9_]#', '_', $filename) . '_signe.' . $extension;
            $donneesFormulaire->addFileFromData(
                $document_element,
                $filename_signe,
                $signature->getSignedFile($info)
            );
        }

        $output_annexe = $signature->getOutputAnnexe(
            $info,
            $donneesFormulaire->getFileNumber('autre_document_attache')
        );

        foreach ($output_annexe as $i => $annexe) {
            $donneesFormulaire->addFileFromData(
                'iparapheur_annexe_sortie',
                $annexe['nom_document'],
                $annexe['document'],
                $i
            );
        }

        $bordereau = $signature->getBordereauFromSignature($info, $dossierID);
        if ($bordereau) {
            $donneesFormulaire->addFileFromData('bordereau', $bordereau->filename, $bordereau->content);
        }

        if (! $signature->archiver($dossierID)) {
            throw new RecoverableException(
                "Impossible d'archiver la transaction sur le parapheur : " . $signature->getLastError()
            );
        }

        $this->changeAction('recu-iparapheur', 'La signature a été récupérée sur le parapheur électronique');
        $this->setLastMessage('La signature a été récupérée');
        return true;
    }
}
