<?php

class CppCreation extends ActionExecutor
{
    /**
     * @return array
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function metier()
    {
        $factureCPP = $this->action_params['factureCPP'];

        $this->objectInstancier->getInstance(CreationFactureCPP::class)->alimenter($this->id_d, $factureCPP);

        // Journalisation et changement d état
        $this->changeAction(Action::CREATION, 'Création du document [Succes]');
        $this->changeAction(Action::MODIFICATION, 'Modification du document');

        /** @var PortailFactureConnecteur $portailFactureConnecteur */
        $portailFactureConnecteur = $this->getConnecteur('PortailFacture');
        $synchronisationFacture = new SynchronisationFacture($portailFactureConnecteur);
        try {
            $result_synchro = $synchronisationFacture->getSynchroDocumentFacture($this->getDonneesFormulaire(), true);
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la synchronisation : ' . $e->getMessage());
        }
        return $result_synchro;
    }

    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go()
    {
        try {
            $result_synchro = $this->metier();
        } catch (Exception $e) {
            $this->doSuppression($this->id_d);
            throw new Exception(
                'Une erreur est survenue lors de la création du document. La récupération est annulé: ' . $e->getMessage()
            );
        }

        /** @var PortailFactureConnecteur $portailFactureConnecteur */
        $portailFactureConnecteur = $this->getConnecteur('PortailFacture');
        $synchronisationFacture = new SynchronisationFacture($portailFactureConnecteur);

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

        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($this->id_d);

        //Valorisation des attributs spécifiques
        $donneesFormulaire->setData('is_cpp', true);

        //Extraction des donnees pivot
        $this->objectInstancier->getInstance(ExtraireDonneesPivot::class)->getAllPJ($donneesFormulaire);
        $donnees_facture = $this->objectInstancier
            ->getInstance(ExtraireDonneesPivot::class)
            ->getDonneesFacture($donneesFormulaire);
        $donneesFormulaire->setData('facture_numero_engagement', $donnees_facture['facture_numero_engagement']);
        $donneesFormulaire->setData('facture_numero_marche', $donnees_facture['facture_numero_marche']);
        $donneesFormulaire->setData('facture_cadre', strval($donnees_facture['facture_cadre']));

        // Valorisation du cheminement d'après les valeurs par défaut définit dans le connecteur PortailFacture associé au flux Facture CPP
        $parametrageFluxFactureCPP = $this->objectInstancier
            ->getInstance(ConnecteurFactory::class)
            ->getConnecteurByType($this->id_e, $this->type, 'ParametrageFlux');
        if ($parametrageFluxFactureCPP) {
            /** @var ParametrageFluxFactureCPP $parametrageFluxFactureCPP */
            $this->objectInstancier
                ->getInstance(CreationFactureCPP::class)
                ->parametrer($this->id_d, $parametrageFluxFactureCPP);
        }

        // Controle du document
        if (! $donneesFormulaire->isValidable()) {
            $this->setLastMessage('Création du document avec erreur');
            throw new Exception('Une erreur est survenue lors de la création du document ' . $this->id_d . ' : ' . $donneesFormulaire->getLastError());
        }

        // Valorisation de l'état suivant
        $this->changeAction('importation', 'Traitement du document');
        $this->objectInstancier->getInstance(ActionExecutorFactory::class)->executeOnDocument(
            $this->id_e,
            $this->id_u,
            $this->id_d,
            'orientation'
        );
        $this->setLastMessage('Création du document');

        return true;
    }

    /**
     * @param $id_d
     * @return string
     * @throws NotFoundException
     */
    private function doSuppression($id_d)
    {
        $info = $this->getDocument()->getInfo($id_d);

        $this->getDonneesFormulaire()->delete();
        $this->getDocument()->delete($id_d);

        $message = "Le document « {$info['titre']} » ({$id_d}) a été supprimé";
        $this->getJournal()->add(Journal::DOCUMENT_ACTION, $this->id_e, $id_d, "suppression", $message);

        $this->setLastMessage($message);
        return $message;
    }
}
