<?php

use Pastell\Service\ChorusPro\ChorusProImportCreationService;
use Pastell\Service\ChorusPro\ChorusProImportSynchroService;
use Pastell\Service\ChorusPro\ChorusProImportUtilService;

class ChorusParCSVImporterFacture extends ActionExecutor
{
    /**
     * @return ChorusProImportCreationService
     */
    private function getChorusProCreationService(): ChorusProImportCreationService
    {
        return $this->objectInstancier->getInstance(ChorusProImportCreationService::class);
    }

    /**
     * @return ChorusProImportSynchroService
     */
    private function getChorusProSynchroService(): ChorusProImportSynchroService
    {
        return $this->objectInstancier->getInstance(ChorusProImportSynchroService::class);
    }

    /**
     * @return ChorusProImportUtilService
     */
    private function getChorusProUtilService(): ChorusProImportUtilService
    {
        return $this->objectInstancier->getInstance(ChorusProImportUtilService::class);
    }

    /**
     * @return bool
     */
    public function go(): bool
    {
        $this->getChorusProCreationService()->setChorusProConfigService($this->id_e, $this->id_u, $this->id_ce);
        $this->getChorusProSynchroService()->setChorusProConfigService($this->id_e, $this->id_u);

        try {
            $result = $this->metier();
        } catch (Exception $ex) {
            $this->setLastMessage(
                "La liste des factures d'après Le fichier CSV interprété n'a pas pu être récupérée: " . "<br/>" .
                $ex->getMessage()
            );
            return false;
        }
        $this->setLastMessage("Liste des factures: " . '<br/>' . $result);
        return true;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function metier(): string
    {
        /** @var ChorusParCsv $connecteur_chorus */
        $connecteur_chorus = $this->getMyConnecteur();
        $oldestDateDepuisLe = $this->getChorusProUtilService()->getOldestDateDepuisLe(
            $this->id_e,
            $connecteur_chorus->getDateDepuisLe(),
            ChorusProImportUtilService::TYPE_INTEGRATION_CSV_VALEUR
        );
        $this->getLogger()->info("Date de dépôt minimum factures recues: $oldestDateDepuisLe");

        $connecteur_properties = $this->getConnecteurProperties();

        $fichier_csv_interprete = $connecteur_properties->getFilePath('fichier_csv_interprete');
        if (!file_exists($fichier_csv_interprete)) {
            throw new Exception("Il n'y a pas de fichier CSV interprété");
        }
        $CSV = new CSV();
        $colList = $CSV->get($fichier_csv_interprete, ';');

        $message = "";

        foreach ($colList as $col) {
            if (!$col[0]) {
                continue;
            }
            if (count($col) !== 6) {
                $message = 'Les lignes doivent être de la forme ' .
                    '"utilisateur technique";' .
                    '"mot de passe";' .
                    '"SIRET de la structure (optionel)";' .
                    '"Identifiant Chorus de la structure (optionel)";' .
                    '"SIRET du fournisseur (optionel)";' .
                    '"Identifiant Chorus du fournisseur (optionel)"';
                throw new Exception($message);
            }
            $message .= 'Pour la ligne CSV: ' . $col[0] . ";" . $col[2] . ";" . $col[4] . '<br/>';
            // ('user_login', 'user_password', 'siret_structure', 'id_chorus_structure','siret_fournisseur','id_chorus_fournisseur')
            $connecteur_properties->setData('user_login', $col[0]);
            $connecteur_properties->setData('user_password', $col[1]);
            $connecteur_properties->setData('identifiant_structure_cpp', $col[3]);

            $result = $this->traiterUneLigne($col[5], $oldestDateDepuisLe);
            $message .= $this->getChorusProUtilService()->miseEnFormeResult($result);
        }
        return $message;
    }

    /**
     * @param string $fournisseur
     * @param string $oldestDateDepuisLe
     * @return array
     * @throws Exception
     */
    public function traiterUneLigne(string $fournisseur, string $oldestDateDepuisLe): array
    {
        $liste_facture_a_creer = [];
        $result_all = [];

        /** @var ChorusParCsv $connecteur_chorus */
        $connecteur_chorus = $this->getMyConnecteur();

        $utilisateur_technique = $connecteur_chorus->getUserLogin();
        $liste_facture_pastell = $this->getListeFacturePastellCSV($utilisateur_technique);
        // Chargement des factures présentes sur la plateforme chorus ayant changé de statut
        $liste_facture_chorus = $connecteur_chorus->getListeFacturesRecipiendaire(
            $fournisseur,
            $oldestDateDepuisLe
        );

        foreach ($liste_facture_chorus as $facture_chorus) {
            // Le document existe-t-il déjà sur Pastell
            $facture_pastell = $this->getChorusProUtilService()->rechercherDocumentPastell(
                $facture_chorus['id_facture_cpp'],
                $liste_facture_pastell
            );
            if ($facture_pastell !== false) {
                // La facture existe. Il faut l'actualiser
                $result = $this->getChorusProSynchroService()->analyseOneFactureSynchro(
                    $facture_chorus,
                    $facture_pastell
                );
                $result_all[] = $result;
            } else {
                // La facture n'existe pas. Il faudra la créer.
                // Enregistrement de la facture chorus à créer.
                $liste_statut_courant = $connecteur_chorus->getListeStatutCourant();
                // Si la facture n'est pas en statut courant on ne la crée pas
                if (! in_array($facture_chorus['statut'], $liste_statut_courant)) {
                    break;
                }
                $liste_facture_a_creer[] = $facture_chorus;
            }
        }

        foreach ($liste_facture_a_creer as $facture_a_creer) {
            //créations de factures
            $facture_a_creer['utilisateur_technique'] = $utilisateur_technique;
            $result = $this->getChorusProCreationService()->analyseOneFactureCreation(
                $facture_a_creer,
                ChorusProImportUtilService::NOMMAGE_ID_FACTURE_CSV
            );
            $result['id_facture_cpp'] = $facture_a_creer['id_facture_cpp'];
            $result_all[] = $result;
        }
        return $result_all;
    }

    /**
     * @param string $utilisateur_technique
     * @return array
     * @throws Exception
     */
    public function getListeFacturePastellCSV(string $utilisateur_technique): array
    {
        $liste_facture_pastell_csv = [];
        $liste_facture_pastell = $this->getChorusProUtilService()->getListeFacturePastell(
            $this->id_e,
            ChorusProImportUtilService::TYPE_INTEGRATION_CSV_VALEUR,
            $utilisateur_technique
        );
        foreach ($liste_facture_pastell as $facture_pastell) {
            //id_facture_cpp de la forme id_facture_cpp-99-CSV
            $explode_id = explode('-', $facture_pastell['id_facture_cpp']);
            $facture_pastell['id_facture_cpp'] = $explode_id[0];
            $liste_facture_pastell_csv[] = $facture_pastell;
        }
        return $liste_facture_pastell_csv;
    }
}
