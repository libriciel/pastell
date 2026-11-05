<?php

use Pastell\Service\Document\DocumentEntiteService;

abstract class SupprimerFacture extends ActionExecutor
{
    abstract public function doTraitementSuppression($all_id_d, $id_e);
    abstract public function getNbJourAvantSupp();

    /**
     * @return string
     * @throws NotFoundException
     */
    public function metier()
    {
        $result = "";
        $listUsedConnecteur = $this->objectInstancier->getInstance(FluxEntiteSQL::class)->getUsedByConnecteur($this->id_ce);

        foreach ($listUsedConnecteur as $fluxEntite) {
            $result .= $this->SupprimerByFluxEntite($fluxEntite['flux'], $fluxEntite['id_e']) . '<br/>';
        }

        return $result;
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

    /**
     * @param $flux
     * @param $id_e
     * @return string
     * @throws NotFoundException
     */
    public function SupprimerByFluxEntite($flux, $id_e)
    {
        $all_id_d = [];
        $nb_jours_avant_supp = $this->getNbJourAvantSupp() - 1;
        $listeStatutCourant = PortailFactureConnecteur::getListeStatutCourant();

        // Chargement de toutes les factures CPP
        $listDocument = $this->objectInstancier->getInstance(DocumentEntiteService::class)
            ->getDocumentsByType((int) $id_e, $flux);

        // Filtre des factures en fonction du statut cpp et de la date du dernier changement de statut.
        foreach ($listDocument as $document) {
            if ($this->isDocASupprimer($document, $listeStatutCourant, $nb_jours_avant_supp)) {
                $all_id_d[] = $document['id_d'];
            }
        }
        $result = 'Entité id_e ' . $id_e . ' : ';
        // Lancement du traitement de suppression des factures.
        if ($all_id_d) {
            $result .= $this->doTraitementSuppression($all_id_d, $id_e);
        } else {
            $result .= "Il n'y a pas de factures à supprimer";
        }
        return $result;
    }

    /**
     * @param array $document
     * @param $listeStatutCourant
     * @param $nb_jours
     * @return bool
     * @throws NotFoundException
     */
    public function isDocASupprimer(array $document, $listeStatutCourant, $nb_jours)
    {
        $doc_a_supprimer = false;

        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($document['id_d']);
        if (!in_array($donneesFormulaire->get(AttrFactureCPP::ATTR_STATUT_CPP), $listeStatutCourant)) {
            $time_statut = strtotime($donneesFormulaire->get(AttrFactureCPP::ATTR_DATE_PASSAGE_STATUT));
            // Si la date n'est pas renseigné, il ne faut pas supprimer le document.
            if ($time_statut && (time() - $time_statut > $nb_jours * 86400)) {
                $doc_a_supprimer = true;
            }
        }
        return $doc_a_supprimer;
    }
}
