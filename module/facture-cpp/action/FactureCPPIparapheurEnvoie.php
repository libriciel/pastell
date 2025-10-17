<?php

class FactureCPPIparapheurEnvoie extends SignatureEnvoie
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
        $this->setMapping([
            "document" => "fichier_facture_pdf",
            "objet" => "id_facture_cpp",
            "autre_document_attache" => "facture_pj_02",
            ]);

        return parent::go();
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function metier()
    {

        $donneesFormulaire = $this->getDonneesFormulaire();

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
        $statut_possible = [
            PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
            PortailFactureConnecteur::STATUT_COMPLETEE
        ];
        if (!in_array($statut, $statut_possible)) {
            $message = 'Le statut ' . $statut . " de la facture ne permet pas l'envoi pour validation";
            $this->changeAction('send-iparapheur-annule', $message);
            return false;
        }

        $json_metadata = $donneesFormulaire->getFileContent("json_metadata");
        if (!$json_metadata) {
            $metadata = [
                'CodeService' => $donneesFormulaire->get('service_destinataire_code'),
                'NumEngagement' => $donneesFormulaire->get('facture_numero_engagement'),
                'NumMarche' => $donneesFormulaire->get('facture_numero_marche'),
                'Siret' => $donneesFormulaire->get('siret'),
                'MontantTtc' => $donneesFormulaire->get('montant_ttc'),
            ];
            $donneesFormulaire->addFileFromData('json_metadata', "json_metadata.json", json_encode($metadata));
        }

        return true;
    }
}
