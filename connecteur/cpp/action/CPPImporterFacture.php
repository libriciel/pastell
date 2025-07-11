<?php

use lib\CPPCheckIfCanCreateFacture;
use Pastell\Service\ChorusPro\ChorusProImportCreationService;
use Pastell\Service\ChorusPro\ChorusProImportSynchroService;
use Pastell\Service\ChorusPro\ChorusProImportUtilService;

class CPPImporterFacture extends ActionExecutor
{
    /**
     * @return ChorusProImportCreationService
     */
    private function getChorusProCreationService()
    {
        return $this->objectInstancier->getInstance(ChorusProImportCreationService::class);
    }

    /**
     * @return ChorusProImportSynchroService
     */
    private function getChorusProSynchroService()
    {
        return $this->objectInstancier->getInstance(ChorusProImportSynchroService::class);
    }

    /**
     * @return ChorusProImportUtilService
     */
    private function getChorusProUtilService()
    {
        return $this->objectInstancier->getInstance(ChorusProImportUtilService::class);
    }

    private function getCPPCheckIfCanCreateFacture(): CPPCheckIfCanCreateFacture
    {
        return $this->objectInstancier->getInstance(CPPCheckIfCanCreateFacture::class);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        $this->getChorusProCreationService()->setChorusProConfigService($this->id_e, $this->id_u, $this->id_ce);
        $this->getChorusProSynchroService()->setChorusProConfigService($this->id_e, $this->id_u);

        $result = $this->metier();

        /** @var CPP $connecteur_chorus */
        $connecteur_chorus = $this->getMyConnecteur();
        $message =
            'Récupération des factures ayant changé de statut entre le ' . $connecteur_chorus->getDateDepuisLe() .
            ' et le ' . $connecteur_chorus->getDateJusquAu() .
            ' et synchronisation des factures déja présentes:<br/>';
        $message .= $this->getChorusProUtilService()->miseEnFormeResult($result);
        $this->setLastMessage($message);
        return true;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function metier()
    {
        /** @var CPP $connecteur_chorus */
        $connecteur_chorus = $this->getMyConnecteur();
        if ($connecteur_chorus->getNoRecupFacture()) {
            throw new Exception("La récupération des factures est désactivée pour ce connecteur");
        }

        ///////////////////////////////////////////////////////////////////////
        //Traitement des factures à partir des factures présentes sur Chorus //
        ///////////////////////////////////////////////////////////////////////

        // Chargement des factures présentes sur la plateforme chorus ayant changé de statut

        $min_date_statut_courant_recues = $this->getChorusProUtilService()->getMinDateStatutCourant(
            $this->id_e,
            $connecteur_chorus->getDateDepuisLe(),
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_VALEUR
        );
        $this->getLogger()->info("Date de dépôt minimum factures recues: $min_date_statut_courant_recues");
        $liste_facture_recues = $connecteur_chorus->getListeFacturesRecipiendaire(
            '',
            $min_date_statut_courant_recues,
            $connecteur_chorus->getDateJusquAu()
        );

        $min_date_statut_courant_travaux = $this->getChorusProUtilService()->getMinDateStatutCourant(
            $this->id_e,
            $connecteur_chorus->getDateDepuisLe(),
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_TRAVAUX_VALEUR
        );
        $this->getLogger()->info("Date de dépôt minimum factures travaux: $min_date_statut_courant_travaux");
        $liste_facture_travaux = $connecteur_chorus->getListeFacturesTravaux(
            $min_date_statut_courant_travaux,
            $connecteur_chorus->getDateJusquAu()
        );

        $liste_facture_chorus = array_merge($liste_facture_travaux, $liste_facture_recues);

        // Chargement des factures cpp présentes sur Pastell
        $liste_facture_bus_recues = $this->getChorusProUtilService()->getListeFacturePastell(
            $this->id_e,
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_VALEUR
        );
        $liste_facture_bus_travaux = $this->getChorusProUtilService()->getListeFacturePastell(
            $this->id_e,
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_TRAVAUX_VALEUR
        );
        $liste_facture_bus = array_merge($liste_facture_bus_travaux, $liste_facture_bus_recues);

        $liste_facture_a_creer = [];
        $result_all = [];
        /** @var CPP $connecteur_chorus */
        $connecteur_chorus = $this->getMyConnecteur();
        $dateLimiteDePriseEnCharge = $connecteur_chorus->getDateDepuisLe();
        $statusCourant = $connecteur_chorus->getListeStatutCourant();
        $cppCheckIfCanCreateFactureService = $this->getCPPCheckIfCanCreateFacture();

        foreach ($liste_facture_chorus as $facture_chorus) {
            // Le document existe-t-il déjà sur le bus
            $facture_bus = $this->getChorusProUtilService()->rechercherDocumentPastell(
                $facture_chorus['id_facture_cpp'],
                $liste_facture_bus
            );
            $canCreateFacture = $cppCheckIfCanCreateFactureService->canCreateFacture($facture_chorus, $dateLimiteDePriseEnCharge, $statusCourant);

            if ($facture_bus !== false) {
                // La facture existe. Il faut l'actualiser
                $result = $this->getChorusProSynchroService()->analyseOneFactureSynchro($facture_chorus, $facture_bus);
                $result_all[] = $result;
            } elseif ($canCreateFacture) {
                // La facture n'existe pas et répond aux conditions de création. Il faudra la créer.
                $liste_facture_a_creer[] = $facture_chorus;
                $this->getLogger()->info("Facture à créer", $facture_chorus);
            }
        }
        //////////////////////////////////////////
        // Traitement des créations de factures //
        //////////////////////////////////////////

        foreach ($liste_facture_a_creer as $facture_a_creer) {
            $result = $this->getChorusProCreationService()->analyseOneFactureCreation($facture_a_creer);
            $result['id_facture_cpp'] = $facture_a_creer['id_facture_cpp'];
            $result_all[] = $result;
        }
        return $result_all;
    }
}
