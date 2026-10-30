<?php

class CppModifStatut extends ActionExecutor
{
    /**
     * @return array|bool ['statut' => statut final du document, 'deja_en_statut' => true si aucune modification n'était nécessaire]
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function metier()
    {
        $doc = $this->getDonneesFormulaire();
        $statut_cible = $doc->get(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE);
        $statut_cpp = $doc->get(AttrFactureCPP::ATTR_STATUT_CPP);
        $motif_maj = $doc->get(AttrFactureCPP::ATTR_MOTIF_MAJ);

        if ($statut_cible == $statut_cpp) {
            $doc->setData(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE, '');
            return ['statut' => $statut_cpp, 'deja_en_statut' => true];
        }
        if (!$this->controlerStatutCible($statut_cible, $motif_maj)) {
            return false;
        }
        if (!$this->modifStatut($statut_cible)) {
            return false;
        }
        $doc->setData(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE, '');
        return ['statut' => $statut_cible, 'deja_en_statut' => false];
    }

    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go()
    {
        /** @var CPP $connPortailFacture */
        $connPortailFacture = $this->getConnecteur('PortailFacture');

        if ($connPortailFacture->getNoChangeStatutChorus()) {
            $message = 'La remontée de la modification de statut sur Chorus Pro est désactivée.';
            $this->changeAction('cpp-modif-statut-erreur', $message);
            return false;
        }
        try {
            $result_modif = $this->metier();
            if (!$result_modif) {
                $this->changeAction('cpp-modif-statut-erreur', $this->getLastMessage());
                $this->notify('cpp-modif-statut-erreur', $this->type, $this->getLastMessage());
                return false;
            }
            if ($result_modif['deja_en_statut']) {
                $message = "La facture est déja en statut " . $result_modif['statut'];
                $this->changeAction('cpp-modif-statut-ok', $message);
                $this->notify('cpp-modif-statut-ok', $this->type, $message);
                return true;
            }

            $this->addActionOK('La facture est en statut ' . $result_modif['statut']);

            $statut_cpp = $this->getDonneesFormulaire()->get(AttrFactureCPP::ATTR_STATUT_CPP);
            $message = 'La facture est en statut ' . $statut_cpp;
            $this->changeAction('cpp-modif-statut-ok', $message);
            $this->notify('cpp-modif-statut-ok', $this->type, $message);

            $synchronisationFacture = new SynchronisationFacture($connPortailFacture);
            $result_synchro = $synchronisationFacture->getSynchroDocumentFacture($this->getDonneesFormulaire(), true);

            $this->objectInstancier
                ->getInstance(Journal::class)
                ->addSQL(
                    Journal::DOCUMENT_ACTION,
                    $this->id_e,
                    $this->id_u,
                    $this->id_d,
                    'synchroniser-statut',
                    $synchronisationFacture->formatResultSynchro($result_synchro)
                );
        } catch (Exception $e) {
            $this->setLastMessage('ERREUR : ' . $e->getMessage());
            return false;
        }
        return true;
    }

    // Contrat de sortie :
    //  - true si le controle est ok
    //  - false en cas de non conformité (le message d'erreur est positionné).
    /**
     * @param $statut_cible
     * @param $motif_maj
     * @return bool
     */
    protected function controlerStatutCible($statut_cible, $motif_maj)
    {
        if (empty($statut_cible)) {
            $this->setLastMessage('Le statut cible est vide.');
            return false;
        }

        $statuts_cible_autorisees = [
            PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
            PortailFactureConnecteur::STATUT_REJETEE,
            PortailFactureConnecteur::STATUT_SUSPENDUE,
            PortailFactureConnecteur::STATUT_SERVICE_FAIT,
            PortailFactureConnecteur::STATUT_MANDATEE,
            PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION_COMPTABLE,
            PortailFactureConnecteur::STATUT_COMPTABILISEE,
            PortailFactureConnecteur::STATUT_MISE_EN_PAIEMENT
        ];

        if (
            (($statut_cible == PortailFactureConnecteur::STATUT_REJETEE)
            || ($statut_cible == PortailFactureConnecteur::STATUT_SUSPENDUE))
            && (!$motif_maj)
        ) {
            $this->setLastMessage('Le statut cible ' . $statut_cible . ' nécessite un motif');
            return false;
        }
        if (!in_array($statut_cible, $statuts_cible_autorisees)) {
            $this->setLastMessage("Le statut cible $statut_cible n'existe pas.");
            return false;
        }
        return true;
    }

    /**
     * @param $statut_cible
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    private function modifStatut($statut_cible)
    {
        $doc = $this->getDonneesFormulaire();
        $id_facture_cpp = $doc->get(AttrFactureCPP::ATTR_ID_FACTURE_CPP);
        $motif_maj = substr($doc->get(AttrFactureCPP::ATTR_MOTIF_MAJ, ''), 0, 255);
        $numero_mandat = '';
        if ($statut_cible == PortailFactureConnecteur::STATUT_MANDATEE) {
            $numero_mandat = $doc->get(AttrFactureCPP::ATTR_NUMERO_MANDAT, '');
        }

        // Traitement de modification du statut sur CPP
        /** @var PortailFactureConnecteur $connPortailFacture */
        $connPortailFacture = $this->getConnecteur('PortailFacture');
        $result = $connPortailFacture->setStatutFacture($id_facture_cpp, $statut_cible, $motif_maj, $numero_mandat);
        if ($result) {
            if (isset($result['retourFonctionnel'])) {
                // Resynchronisation du document avec CPP.
                $synchronisationFacture = new SynchronisationFacture($connPortailFacture);
                $synchronisationFacture->getSynchroDocumentFacture($this->getDonneesFormulaire(), false);
                $this->setLastMessage($result['retourFonctionnel'] . ' - ' . $result['libelleRetourFonctionnel']);
                return false;
            }
            $doc->setData(AttrFactureCPP::ATTR_STATUT_CPP, $statut_cible);
        }
        return true;
    }
}
