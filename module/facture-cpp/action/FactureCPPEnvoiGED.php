<?php

class FactureCPPEnvoiGED extends GEDEnvoyer
{
    public function go()
    {

        try {
            if (!$this->metier()) {
                return false;
            }
        } catch (Exception $e) {
            $this->setLastMessage('ERREUR : ' . $e->getMessage());
            return false;
        }
        // Dans le cas de l'héritage on fait le mapping ici (il n'est pas dans le definition.yml)
        $this->setMapping(["fatal-error" => "error-ged"]);
        $result_ged = parent::go();

        if ($result_ged) {
            $this->getDonneesFormulaire()->setData('has_send_ged', true);
        }

        return $result_ged;
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function metier()
    {

        $donneesFormulaire = $this->getDonneesFormulaire();

        if ($donneesFormulaire->get('has_send_ged') == true) {
            $message = 'La facture a déja été déposée en GED';
            $this->changeAction('modification', $message);
            return false;
        }

        if (($donneesFormulaire->get('is_cpp')) && (!$donneesFormulaire->get('is_annule'))) {
            // Synchronisation de la facture avant de tester la valeur du statut
            /** @var PortailFactureConnecteur $portailFactureConnecteur */
            $portailFactureConnecteur = $this->getConnecteur('PortailFacture');
            $synchronisationFacture = new SynchronisationFacture($portailFactureConnecteur);
            $synchronisationFacture->getSynchroDocumentFacture($donneesFormulaire, true);
        }

        $statut = $donneesFormulaire->get('statut_cpp');
        if (!$statut) {
            $message = "Le statut de la facture n'a pas pu être vérifié";
            $this->setLastMessage($message);
            return false;
        }

        /** @var ParametrageFluxFactureCPP $parametrageFlux */
        $parametrageFlux = $this->getConnecteur("ParametrageFlux");

        $gedListeStatuts = $parametrageFlux->getGedListeStatuts();
        $gedListeStatutsTab = explode(";", $gedListeStatuts);

        if (!(in_array($statut, $gedListeStatutsTab))) {
            $message = 'Le statut ' . $statut . " de la facture ne permet pas l'envoi en GED";
            $this->notify($this->action, $this->type, $message);
            $this->changeAction('send-ged-annule', $message);
            return false;
        }
        return true;
    }
}
