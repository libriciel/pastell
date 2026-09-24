<?php

class SynchronisationFacture
{
    /** @var PortailFactureConnecteur */
    private $portailFactureConnecteur;

    private const RETOUR_SYNCHRO_MAJ = 'MAJ';
    private const RETOUR_SYNCHRO_VERIF = 'VERIF';

    public function __construct(PortailFactureConnecteur $portailFactureConnecteur)
    {
        $this->portailFactureConnecteur = $portailFactureConnecteur;
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @param bool $forcer_synchro
     * @return array
     * @throws Exception
     */
    public function getSynchroDocumentFacture(DonneesFormulaire $donneesFormulaire, $forcer_synchro = false)
    {
        $id_facture_cpp = $donneesFormulaire->get('id_facture_cpp');

        try {
            $histoStatutFactureCPP = $this->portailFactureConnecteur->getHistoStatutFacture($id_facture_cpp);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la synchronisation pour récupérer l'historique : " . $e->getMessage());
        }

        $statut_courant = $histoStatutFactureCPP['statut_courant'];

        if ((!($donneesFormulaire->get('statut_cpp') == $statut_courant)) || ($forcer_synchro)) {
            $date_mise_a_dispo = $this->getPremiereDateTrouvePassageStatut($histoStatutFactureCPP, PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION);
            // La date de fin suspension correspond à la date de l'historique "COMPLETEE" le plus récent.
            $date_fin_suspension = $this->getDateLaPlusRecentePassageStatut($histoStatutFactureCPP, PortailFactureConnecteur::STATUT_COMPLETEE);
            $date_passage_statut = $this->getPremiereDateTrouvePassageStatut($histoStatutFactureCPP, $statut_courant);
            try {
                $fichier_facture = $this->portailFactureConnecteur->getFichierFacture($id_facture_cpp, "PIVOT");
            } catch (Exception $e) {
                throw new Exception('Erreur lors de la synchronisation pour récupérer le fichier : ' . $e->getMessage());
            }

            $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_MISE_A_DISPO, $date_mise_a_dispo);
            $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_FIN_SUSPENSION, $date_fin_suspension);
            $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_PASSAGE_STATUT, $date_passage_statut);
            $donneesFormulaire->addFileFromData(AttrFactureCPP::ATTR_FICHIER_FACTURE, $id_facture_cpp . ".xml", $fichier_facture, 0);
            $donneesFormulaire->setData(AttrFactureCPP::ATTR_STATUT_CPP, $statut_courant);
            $donneesFormulaire->addFileFromData("histo_statut_cpp", "histo_statut_cpp.json", json_encode($histoStatutFactureCPP));

            $action = self::RETOUR_SYNCHRO_MAJ;
        } else {
            $action = self::RETOUR_SYNCHRO_VERIF;
        }

        // #2467 : A_RECYCLER n'est plus un statut cible, mais des factures peuvent encore l'avoir comme statut courant
        if ($statut_courant == PortailFactureConnecteur::STATUT_A_RECYCLER) {
            $donneesFormulaire->setData(AttrFactureCPP::ATTR_ID_FACTURE_CPP, $id_facture_cpp . "-1-RECYCLEE");
            $donneesFormulaire->setData(AttrFactureCPP::ATTR_IS_ANNULE, true);
        }

        if ($statut_courant == PortailFactureConnecteur::STATUT_SUSPENDUE) {
            $donneesFormulaire->setData(AttrFactureCPP::ATTR_ID_FACTURE_CPP, $id_facture_cpp . "-2-SUSPENDUE");
            $donneesFormulaire->setData(AttrFactureCPP::ATTR_IS_ANNULE, true);
        }

        return ['action' => $action, 'statut' => $statut_courant];
    }

    /**
     * @param $histoStatutFactureCPP
     * @param $codeStatut
     * @return bool|mixed
     */
    private function getPremiereDateTrouvePassageStatut($histoStatutFactureCPP, $codeStatut)
    {
        foreach ($histoStatutFactureCPP['histo_statut'] as $histoStatut) {
            if ($histoStatut['statut_code'] == $codeStatut) {
                return $histoStatut['statut_date_passage'];
            }
        }
        return false;
    }

    /**
     * @param $histoStatutFactureCPP
     * @param $codeStatut
     * @return bool|mixed
     */
    private function getDateLaPlusRecentePassageStatut($histoStatutFactureCPP, $codeStatut)
    {
        $date_passage = false;
        foreach ($histoStatutFactureCPP['histo_statut'] as $histoStatut) {
            if (
                ($histoStatut['statut_code'] == $codeStatut)
                && ($date_passage === false || strtotime($date_passage) < strtotime($histoStatut['statut_date_passage']))
            ) {
                    $date_passage = $histoStatut['statut_date_passage'];
            }
        }
        return $date_passage;
    }

    /**
     * @param $result_synchro
     * @return string
     */
    public function formatResultSynchro($result_synchro)
    {
        $message = "";
        if ($result_synchro['action'] == self::RETOUR_SYNCHRO_MAJ) {
            $message = "Mise à jour de la facture en statut " . $result_synchro['statut'];
        } elseif ($result_synchro['action'] == self::RETOUR_SYNCHRO_VERIF) {
            $message = "Le statut " . $result_synchro['statut'] . " est vérifié";
        }
        return $message;
    }

    /**
     * @param $id_facture_cpp
     * @return mixed
     */
    public function getFichierFacturePDF($id_facture_cpp)
    {
        return $this->portailFactureConnecteur->getFichierFacture($id_facture_cpp, "PDF");
    }
}
