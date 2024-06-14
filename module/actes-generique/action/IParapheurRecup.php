<?php

class IParapheurRecup extends ActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');

        return $signature->isFastSignature()
            ? $this->goFast()
            : $this->goIparapheur();
    }


    /**
     * @throws Exception
     */
    private function getDossierID()
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');

        $actes = $this->getDonneesFormulaire();
        if ($actes->get('iparapheur_dossier_id')) {
            $dossierID = $actes->get('iparapheur_dossier_id');
        } else {
            $dossierID = $signature->getDossierID($actes->get('numero_de_lacte'), $actes->get('objet'));
        }
        return $dossierID;
    }

    /**
     * @param $dossierID
     * @param $result
     * @return bool
     * @throws Exception
     */
    public function rejeteDossier($dossierID, $result)
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');
        $donneesFormulaire = $this->getDonneesFormulaire();

        $info = $signature->getSignature($dossierID);
        if (! $info) {
            $this->setLastMessage("Le bordereau n'a pas pu être récupéré : " . $signature->getLastError());
            return false;
        }
        $donneesFormulaire->addFileFromData('document_signe', $info['nom_document'], $info['document']);

        $signature->effacerDossierRejete($dossierID);

        $this->notify('rejet-iparapheur', $this->type, "Le document a été rejeté dans le parapheur : $result");
        $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'rejet-iparapheur', "Le document a été rejeté dans le parapheur : $result");
        return true;
    }

    /**
     * @return bool
     * @throws Exception
     * @throws RecoverableException
     */
    public function retrieveDossier()
    {
        /** @var IParapheur $signature */
        $signature = $this->getConnecteur('signature');

        $actes = $this->getDonneesFormulaire();
        $dossierID = $this->getDossierID();
        $info = $signature->getSignature($dossierID, false);
        if (! $info) {
            $this->setLastMessage("La signature n'a pas pu être récupérée : " . $signature->getLastError());
            return false;
        }

        $actes->setData('has_signature', true);
        if ($info['signature']) {
            $actes->addFileFromData('signature', "signature.zip", $info['signature']);
        } elseif ($info['document_signe']) {
            $actes->setData('is_pades', true);
            $filename = substr($actes->getFileName('arrete'), 0, -4);
            $filename_signe = $filename . "_signe.pdf";
            $actes->addFileFromData('signature', $filename_signe, $info['document_signe']['document']);
        }

        $output_annexe = $signature->getOutputAnnexe($info, $actes->getFileNumber('autre_document_attache'));

        foreach ($output_annexe as $i => $annexe) {
            $actes->addFileFromData('iparapheur_annexe_sortie', $annexe['nom_document'], $annexe['document'], $i);
        }

        // Bordereau de signature
        $actes->addFileFromData('document_signe', $info['nom_document'], $info['document']);

        if (! $signature->archiver($dossierID)) {
            throw new RecoverableException(
                "Impossible d'archiver la transaction sur le parapheur : " . $signature->getLastError()
            );
        }

        $this->setLastMessage("La signature a été récupérée");
        $this->notify('recu-iparapheur', $this->type, "La signature a été récupérée");
        $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'recu-iparapheur', "La signature a été récupérée sur le parapheur électronique");
        return true;
    }

    /**
     * @param SignatureConnecteur $signature
     * @param $message
     * @throws Exception
     */
    public function throwError(SignatureConnecteur $signature, $message)
    {
        $nb_jour_max = $signature->getNbJourMaxInConnecteur();
        $lastAction = $this->getDocumentActionEntite()->getLastActionInfo($this->id_e, $this->id_d);
        $time_action = strtotime($lastAction['date']);

        if (time() - $time_action > $nb_jour_max * 86400) {
            $message = "Aucune réponse disponible sur le parapheur depuis $nb_jour_max jours !";
            $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'erreur-verif-iparapheur', $message);
            $this->notify($this->action, $this->type, $message);
        }

        throw new Exception($message);
    }

    /**
     * @return bool
     * @throws RecoverableException
     */
    private function goIparapheur()
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');

        $actes = $this->getDonneesFormulaire();

        $dossierID = $this->getDossierID();
        $erreur = false;
        $all_historique = false;
        try {
            $all_historique = $signature->getAllHistoriqueInfo($dossierID);
        } catch (Exception $e) {
            $this->throwError($signature, $e->getMessage());
        }

        if (! $all_historique) {
            $message = "La connexion avec le iParapheur a échoué : " . $signature->getLastError();
            $this->throwError($signature, $message);
        }

        $array2XML = new Array2XML();
        $historique_xml = $array2XML->getXML('iparapheur_historique', json_decode(json_encode($all_historique), true));


        $actes->setData('has_historique', true);
        $actes->addFileFromData('iparapheur_historique', "iparapheur_historique.xml", $historique_xml);

        $result = $signature->getLastHistorique($all_historique);
        $actes->setData('parapheur_last_message', $result);

        if (strstr($result, "[Archive]")) {
            return $this->retrieveDossier();
        } elseif ($signature->isRejected($result)) {
            $this->rejeteDossier($dossierID, $result);
            $this->setLastMessage($result);
            return true;
        }
        $nb_jour_max = $signature->getNbJourMaxInConnecteur();
        $lastAction = $this->getDocumentActionEntite()->getLastActionInfo($this->id_e, $this->id_d);
        $time_action = strtotime($lastAction['date']);
        if (time() - $time_action > $nb_jour_max * 86400) {
            $erreur = "Aucune réponse disponible sur le parapheur depuis $nb_jour_max jours !";
            $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'erreur-verif-iparapheur', $erreur);
            $this->notify('erreur-verif-iparapheur', $this->type, $erreur);
        }

        if (! $erreur) {
            $this->setLastMessage($result);
            return true;
        }

        $this->setLastMessage($erreur);
        return false;
    }

    private function goFast()
    {
        /** @var FastParapheur $signature */
        $signature = $this->getConnecteur('signature');

        $acte = $this->getDonneesFormulaire();

        $documentId = $acte->get('iparapheur_dossier_id');

        $history = $this->getFileHistory($signature, $documentId);

        $error = false;

        $array2XML = new Array2XML();
        $xmlHistory = $array2XML->getXML('iparapheur_historique', json_decode(json_encode($history), true));
        $acte->setData('has_historique', true);
        $acte->addFileFromData('iparapheur_historique', "history.xml", $xmlHistory);

        $lastDocumentHistory = $signature->getLastHistorique($history);
        if ($signature->isFinalState($lastDocumentHistory)) {
            $this->retrieveFile($signature, $acte, $documentId);
        }
        if ($signature->isRejected($lastDocumentHistory)) {
            $signature->effacerDossierRejete($documentId);
            $this->notify('rejet-iparapheur', $this->type, "Le document a été rejeté dans le parapheur");
            $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'rejet-iparapheur', "Le document a été rejeté dans le parapheur");
        }

        $nb_jour_max = $signature->getNbJourMaxInConnecteur();
        $lastAction = $this->getDocumentActionEntite()->getLastActionInfo($this->id_e, $this->id_d);
        $time_action = strtotime($lastAction['date']);
        if (time() - $time_action > $nb_jour_max * 86400) {
            $error = "Aucune réponse disponible sur le parapheur depuis $nb_jour_max jours !";
            $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'erreur-verif-signature-fast', $error);
            $this->notify('erreur-verif-signature-fast', $this->type, $error);
        }

        if (!$error) {
            $this->setLastMessage($lastDocumentHistory);
            return true;
        }

        $this->setLastMessage($error);
        return false;
    }

    /**
     * @param FastParapheur $signature
     * @param DonneesFormulaire $acte
     * @param $documentId
     * @return bool
     * @throws Exception
     */
    private function retrieveFile(FastParapheur $signature, DonneesFormulaire $acte, $documentId)
    {
        $signedFile = $signature->getSignature($documentId);
        if (!$signedFile) {
            $this->setLastMessage("La signature n'a pas pu être récupérée : " . $signature->getLastError());
            return false;
        }

        $acte->setData('has_signature', true);
        $acte->addFileFromData('signature', $acte->getFileName('arrete'), $signedFile);
        $acte->setData('is_pades', true);
        $this->setLastMessage("La signature a été récupérée");
        $this->notify('recu-iparapheur', $this->type, "La signature a été récupérée");
        $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'recu-iparapheur', "La signature a été récupérée sur le parapheur électronique");
        return true;
    }


    /**
     * @param FastParapheur $signature
     * @param $documentId
     * @return bool
     * @throws Exception
     */
    private function getFileHistory(FastParapheur $signature, $documentId)
    {
        $history = [];
        try {
            $history = $signature->getAllHistoriqueInfo($documentId);
        } catch (Exception $e) {
            $this->throwError($signature, $e->getMessage());
        }

        if (!$history) {
            $message = "La connexion avec le parapheur a échouée : " . $signature->getLastError();
            throw new Exception($message);
        }
        return $history;
    }
}
