<?php

class FournisseurCommandeReceptionParapheur extends ActionExecutor
{
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

        $all_historique = false;
        try {
            $all_historique = $signature->getAllHistoriqueInfo($dossierID);
        } catch (Exception $e) {
            $this->traitementErreur($signature, $e->getMessage());
        }

        if (! $all_historique) {
            $message = 'La connexion avec le iParapheur a échoué : ' . $signature->getLastError();
            $this->traitementErreur($signature, $message);
            return false;
        }

        $array2XML = new Array2XML();
        $historique_xml = $array2XML->getXML(
            'iparapheur_historique',
            json_decode(json_encode($all_historique), true)
        );

        $donneesFormulaire->setData('has_historique', true);
        $donneesFormulaire->addFileFromData('iparapheur_historique', 'iparapheur_historique.xml', $historique_xml);

        $lastHistorique = $signature->getLastHistorique($all_historique);
        $lastCompletedHistorique = $signature->getLastCompletedHistorique($all_historique);
        $donneesFormulaire->setData('parapheur_last_message', $lastCompletedHistorique);

        if ($signature->isFinalState($lastHistorique)) {
            return $this->retrieveDossier($dossierID);
        }
        if ($signature->isRejected($lastHistorique)) {
            $this->rejeteDossier($dossierID, $lastHistorique);
        } else {
            $this->traitementErreur($signature, $lastHistorique);
        }
        $this->setLastMessage($lastHistorique);
        return true;
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
            $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'erreur-verif-iparapheur', $message);
            $this->notify($this->action, $this->type, $message);
            throw new Exception($message);
        }
        $this->setLastMessage($message);
        return true;
    }

    /**
     * @throws Exception
     */
    public function rejeteDossier($dossierID, $result): bool
    {
        /** @var SignatureConnecteur $signature */
        $signature = $this->getConnecteur('signature');
        $donneesFormulaire = $this->getDonneesFormulaire();

        $info = $signature->getSignature($dossierID);
        if (! $info) {
            $this->setLastMessage("Le bordereau n'a pas pu être récupéré : " . $signature->getLastError());
            return false;
        }
        $donneesFormulaire->addFileFromData('bordereau', $info['nom_document'], $info['document']);

        $signature->effacerDossierRejete($dossierID);

        $this->notify('rejet-iparapheur', $this->type, "Le document a été rejeté dans le parapheur : $result");
        $this->getActionCreator()->addAction(
            $this->id_e,
            $this->id_u,
            'rejet-iparapheur',
            "Le document a été rejeté dans le parapheur : $result"
        );
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

        $info = $signature->getSignature($dossierID, false);
        if (! $info) {
            $this->setLastMessage("La signature n'a pas pu être récupérée : " . $signature->getLastError());
            return false;
        }

        $donneesFormulaire->setData('has_signature', true);
        if ($info['signature']) {
            $donneesFormulaire->addFileFromData('signature', "signature.zip", $info['signature']);
        }

        $originalDocumentName = $donneesFormulaire->getFileName('document_orignal');
        if (!$originalDocumentName) {
            $document_original_name = $donneesFormulaire->getFileName('commande');
            $document_original_data = $donneesFormulaire->getFileContent('commande');
            $donneesFormulaire->addFileFromData('document_orignal', $document_original_name, $document_original_data);
        }
        if ($info['document_signe']['document'] && !$originalDocumentName) {
            $filename = substr($donneesFormulaire->getFileName('commande'), 0, -4);
            $file_extension =  substr($donneesFormulaire->getFileName('commande'), -3);
            $filename_signe = preg_replace("#[^a-zA-Z0-9_]#", "_", $filename) . "_signe." . $file_extension;
            $donneesFormulaire->addFileFromData('commande', $filename_signe, $info['document_signe']['document']);
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

        $this->setLastMessage('La signature a été récupérée');

        $this->getActionCreator()->addAction(
            $this->id_e,
            $this->id_u,
            'recu-iparapheur',
            'La signature a été récupérée sur le parapheur électronique'
        );

        return true;
    }
}
