<?php

/**
 * @deprecated 5.0.0 Chorus
 */

class FactureFormulaireIntegrerCPP extends ActionExecutor
{
    private $nom_flux_cpp = 'facture-cpp';

    /**
     * @return string
     * @throws Exception
     */
    protected function metier()
    {
        /** @var TmpFolder $tmpFolder */
        $tmpFolder = $this->objectInstancier->getInstance(TmpFolder::class);
        $tmp_folder = $tmpFolder->create();

        try {
            $result = $this->goThrow($tmp_folder);
        } catch (Exception $e) {
            $tmpFolder->delete($tmp_folder);
            throw $e;
        }
        $tmpFolder->delete($tmp_folder);

        return $result;
    }

    /**
     * @param $tmp_folder
     * @return string
     * @throws DonneesFormulaireException
     * @throws NotFoundException
     * @throws Exception
     */
    private function goThrow($tmp_folder)
    {
        $donneesFormulaire = $this->getDonneesFormulaire();

        $factureFormulaireCreerPivot = new FactureFormulaireCreerPivot();
        $factureFormulaireCreerPivot->createCPPFacturePivot($donneesFormulaire);

        $fichier_facture = $donneesFormulaire->copyFile('fichier_facture', $tmp_folder, 0, "fichier_facture");
        if (!$fichier_facture) {
            throw new Exception("Le fichier CPPFacturePivot est manquant.");
        }
        @ unlink($tmp_folder . "/empty");

        $docInfo = [
            'id_e' => $this->id_e,
            'id_u' => $this->id_u,
            'nom_flux_cpp' => $this->nom_flux_cpp,
            'id_facture_cpp' => $donneesFormulaire->get('id_facture'),

            'destinataire' => $donneesFormulaire->get('service_destinataire'), // Identifiant CPP du destinataire
            'service_destinataire' => $donneesFormulaire->get('service_destinataire'), // Identifiant CPP du service destinataire

            'type_integration' => 'PIVOT',
            'statut' => 'MISE_A_DISPOSITION',
            'commentaire' => "Facture issue du formulaire pivot",
            'date_depot' => '',
            'date_statut_courant' => '',

            'fichier_facture' => $fichier_facture,
        ];

        $result = $this->objectInstancier->getInstance(CreationFactureCPP::class)->creerFactureCPP($docInfo);

        $message = "La facture d'après formulaire a été intégrée au type de dossier facture Chorus Pro: " . $result;
        $this->addActionOK($message);
        $this->notify($this->action, $this->type, $message);

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
