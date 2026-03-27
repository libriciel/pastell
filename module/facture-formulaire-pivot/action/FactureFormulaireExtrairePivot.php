<?php

class FactureFormulaireExtrairePivot extends ActionExecutor
{
    /**
     * @return string
     * @throws NotFoundException
     * @throws Exception
     */
    protected function metier()
    {
        $donneesFormulaire = $this->getDonneesFormulaire();

        $fichier_pivot = $donneesFormulaire->getFilePath('fichier_facture');
        if (!$fichier_pivot) {
            throw new Exception("Le fichier CPPFacturePivot est manquant.");
        }
        /** @var FactureFichierPivot $pivot */
        $pivot = new FactureFichierPivot();
        try {
            $pivot->verifIsFormatPivot($fichier_pivot);
        } catch (Exception $e) {
            throw new Exception("Le fichier CPPFacturePivot est incorrect: " . $e->getMessage());
        }

        //Extraction des donnees pivot
        $this->objectInstancier->getInstance(ExtraireDonneesPivot::class)->getAllPJ($donneesFormulaire);

        $fournisseur = $this->objectInstancier->getInstance(ExtraireDonneesPivot::class)->getFournisseur($donneesFormulaire);
        $donneesFormulaire->setData('fournisseur_type_id', $fournisseur['fournisseur_type_id']);
        $donneesFormulaire->setData('fournisseur', $fournisseur['fournisseur']);
        $donneesFormulaire->setData('fournisseur_raison_sociale', $fournisseur['fournisseur_raison_sociale']);
        $donneesFormulaire->setData('fournisseur_code_pays', $fournisseur['fournisseur_code_pays']);
        $donneesFormulaire->setData('fournisseur_ref_bancaire_type', $fournisseur['fournisseur_ref_bancaire_type']);
        $donneesFormulaire->setData('fournisseur_ref_bancaire_compte', $fournisseur['fournisseur_ref_bancaire_compte']);
        $donneesFormulaire->setData('fournisseur_ref_bancaire_etablissement', $fournisseur['fournisseur_ref_bancaire_etablissement']);
        $donneesFormulaire->setData('fournisseur_mode_emission', $fournisseur['fournisseur_mode_emission']);

        $debiteur = $this->objectInstancier->getInstance(ExtraireDonneesPivot::class)->getDebiteur($donneesFormulaire);
        $donneesFormulaire->setData('siret', $debiteur['siret']);
        $donneesFormulaire->setData('service_destinataire_code', $debiteur['service_destinataire_code']);

        $donnees_facture = $this->objectInstancier->getInstance(ExtraireDonneesPivot::class)->getDonneesFacture($donneesFormulaire);
        $donneesFormulaire->setData('no_facture', $donnees_facture['no_facture']);
        $donneesFormulaire->setData('facture_type', $donnees_facture['facture_type']);
        $donneesFormulaire->setData('facture_cadre', $donnees_facture['facture_cadre']);
        $donneesFormulaire->setData('date_facture', $donnees_facture['date_facture']);
        $donneesFormulaire->setData('facture_date_reception', $donnees_facture['facture_date_reception']);
        $donneesFormulaire->setData('facture_mode_paiement_code', $donnees_facture['facture_mode_paiement_code']);
        $donneesFormulaire->setData('facture_mode_paiement_libelle', $donnees_facture['facture_mode_paiement_libelle']);
        $donneesFormulaire->setData('facture_devise', $donnees_facture['facture_devise']);
        $donneesFormulaire->setData('facture_montant_ht', $donnees_facture['facture_montant_ht']);
        $donneesFormulaire->setData('montant_ttc', $donnees_facture['montant_ttc']);
        $donneesFormulaire->setData('facture_montant_net', $donnees_facture['facture_montant_net']);
        $donneesFormulaire->setData('facture_numero_engagement', $donnees_facture['facture_numero_engagement']);
        $donneesFormulaire->setData('facture_numero_marche', $donnees_facture['facture_numero_marche']);

        $this->objectInstancier->getInstance(DocumentSQL::class)->setTitre($this->id_d, $donneesFormulaire->getTitre());

        $message = "Le formulaire a été renseigné d'après le fichier CPPFacturePivot";
        $this->addActionOK($message);

        return $message;
    }

    /**
     * @return bool
     */
    public function go()
    {
        try {
            $result = $this->metier();
            $this->setLastMessage($result);
        } catch (Exception $e) {
            $this->setLastMessage('ERREUR : ' . $e->getMessage());
            return false;
        }
        return true;
    }
}
