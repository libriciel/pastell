<?php

class ChorusProFournisseurSyncrhoniser extends ActionExecutor
{
    /**
     * @return array
     */
    public function getStatutTerminalFournisseur()
    {
        return [
            PortailFactureConnecteur::STATUT_MISE_EN_PAIEMENT,
            PortailFactureConnecteur::STATUT_REJETEE,
        ];
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        /** @var PortailFactureConnecteur $portailFacture */
        $portailFacture = $this->getConnecteur("PortailFacture");

        // récupération du pivot (pour le cas de versement SAE)
        $donneesFormulaire = $this->getDonneesFormulaire();
        $id_facture_cpp = $donneesFormulaire->get('identifiant_facture_cpp');
        try {
            $fichier_facture = $portailFacture->getFichierFacture($id_facture_cpp, "PIVOT");
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la synchronisation : ' . $e->getMessage());
        }

        $donneesFormulaire->addFileFromData('fichier_facture', $id_facture_cpp . ".xml", $fichier_facture, 0);
        //Extraction des donnees pivot
        $this->objectInstancier->getInstance(ExtraireDonneesPivot::class)->getAllPJ($donneesFormulaire);
        $this->getDonneesFormulaire()->setData('has_fichier_chorus', true);

        // récupération de l'historique
        $histoStatutFactureCPP = $portailFacture->getHistoStatutFacture(
            $this->getDonneesFormulaire()->get('identifiant_facture_cpp')
        );
        $this->getDonneesFormulaire()->addFileFromData(
            "histo_statut_cpp",
            "histo_statut_cpp.json",
            json_encode($histoStatutFactureCPP)
        );
        $statut_courant = $histoStatutFactureCPP['statut_courant'];
        $this->getDonneesFormulaire()->setData('statut_facture', $statut_courant);

        if (!$this->miseEnEtatSuivant($histoStatutFactureCPP)) {
            $message = "Le status de la facture est : $statut_courant";
            $this->setLastMessage($message);
        }
        return true;
    }

    /**
     * @param $histoStatutFactureCPP
     * @return bool
     * @throws Exception
     */
    public function miseEnEtatSuivant($histoStatutFactureCPP)
    {
        $statut_courant = $histoStatutFactureCPP['statut_courant'];

        /** @var ParamChorusFournisseur $parametrageFluxFactureChorusFournisseur */
        $parametrageFluxFactureChorusFournisseur = $this->getConnecteurOrFail("ParamChorusFournisseur");

        $action = 'termine';
        if (($this->getDonneesFormulaire()->get('envoi_sae')) && !($this->getDonneesFormulaire()->get('sae_transfert_id'))) {
            $action = 'preparation-send-sae';
        }

        if (in_array($statut_courant, $this->getStatutTerminalFournisseur())) {
            $message = "La facture est en statut final $statut_courant : fin de synchronisation";
            $this->changeActionNotify($action, $message);
            return true;
        }

        $nb_jour_max = $parametrageFluxFactureChorusFournisseur->getNbJourMaxSynchro();
        $lastSynchro = $histoStatutFactureCPP['histo_statut'][0];

        $time_action = strtotime($lastSynchro['statut_date_passage']);
        if (time() - $time_action > $nb_jour_max * 86400) {
            $message = "La facture est en statut $statut_courant depuis plus de $nb_jour_max jours : fin de synchronisation";
            $this->changeActionNotify($action, $message);
            return true;
        }

        $recup_status = $parametrageFluxFactureChorusFournisseur->recupStatutFacture();
        $en_etat_termine = ($this->getDocumentActionEntiteSQL()->getLastAction($this->id_e, $this->id_d) === 'termine');

        if (! $recup_status && ! $en_etat_termine) {
            $message = "La facture est en statut $statut_courant : pas de récupération de statut : fin de synchronisation";
            $this->changeActionNotify($action, $message);
            return true;
        }
        return false;
    }

    /**
     * @param $action
     * @param $message
     * @return bool
     */
    public function changeActionNotify($action, $message)
    {
        $this->changeAction($action, $message);
        $this->notify($action, $this->type, $message);
        $this->setLastMessage($message);
        return true;
    }
}
