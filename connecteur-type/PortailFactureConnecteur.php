<?php

abstract class PortailFactureConnecteur extends Connecteur
{
    public const STATUT_DEPOSEE = "DEPOSEE";
    public const STATUT_ACHEMINEMENT = "EN_COURS_ACHEMINEMENT";
    public const STATUT_MISE_A_DISPOSITION = "MISE_A_DISPOSITION";
    public const STATUT_A_RECYCLER = "A_RECYCLER";
    public const STATUT_REJETEE = "REJETEE";
    public const STATUT_SUSPENDUE = "SUSPENDUE";
    public const STATUT_SERVICE_FAIT = "SERVICE_FAIT";
    public const STATUT_MANDATEE = "MANDATEE";
    public const STATUT_MISE_A_DISPOSITION_COMPTABLE = "MISE_A_DISPOSITION_COMPTABLE";
    public const STATUT_COMPTABILISEE = "COMPTABILISEE";
    public const STATUT_MISE_EN_PAIEMENT = "MISE_EN_PAIEMENT";
    public const STATUT_COMPLETEE = "COMPLETEE";

    private const TYPE_INTEGRATION_CPP = "CPP";
    private const TYPE_INTEGRATION_CPP_TRAVAUX = "CPP_TRAVAUX";

    protected $objectInstancier;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
    }

    /**
     * Recherche sur CPP les factures ayant changé de statut
     * @param string $idFournisseur
     * @param string $periodeDateHeureEtatCourantDu
     * @param string $periodeDateHeureEtatCourantAu
     * @return mixed
     */
    abstract protected function rechercheFactureParRecipiendaire(
        string $idFournisseur = "",
        string $periodeDateHeureEtatCourantDu = "",
        string $periodeDateHeureEtatCourantAu = ""
    );

    /**
     * @param string $periodeDateHeureEtatCourantDu
     * @param string $periodeDateHeureEtatCourantAu
     * @return mixed
     */
    abstract protected function rechercheFactureTravaux(
        string $periodeDateHeureEtatCourantDu = "",
        string $periodeDateHeureEtatCourantAu = ""
    );

    /**
     * Recherche sur cpp l'historique des statuts d'une facture
     * @param $idFacture
     * @param int $nbResultatsMaximum
     * @return mixed (array $HistoStatutFactureCPP avec HistoStatutCode et HistoStatutDatePassage)
     * Remarque: Par defaut le Tri est descendant sur "HistoStatutDatePassage"
     */
    abstract protected function consulterHistoriqueFacture($idFacture, $nbResultatsMaximum = 50);

    /**
     * Télécharger de cpp les fichiers (au format PDF ou PIVOT) d'une facture
     * @param $format (PDF ou PIVOT)
     * @param $idFacture
     * @return mixed (string $PathFichierFactureCPP)
     */
    abstract protected function telechargerGroupeFacture($format, $idFacture);

    /**
     * Mise à jour d'un nouveau statut d'une facture sur cpp
     * @param $idFacture
     * @param $idNouveauStatut
     * @param string $motif
     * @param string $numeroMandat
     * @return mixed (array $ResultStatutFactureCPP)
     */
    abstract protected function traiterFactureRecue($idFacture, $idNouveauStatut, $motif = "", $numeroMandat = "");

    /**
     * @return array
     */
    public static function getListeStatutCourant()
    {
        return  [self::STATUT_MISE_A_DISPOSITION,
            self::STATUT_SERVICE_FAIT,
            self::STATUT_MANDATEE,
            self::STATUT_COMPLETEE
        ];
    }

    /**
     * @return array
     */
    public static function getListeStatutCible()
    {
        return  [self::STATUT_MISE_A_DISPOSITION,
            self::STATUT_SUSPENDUE,
            self::STATUT_A_RECYCLER,
            self::STATUT_REJETEE,
            self::STATUT_SERVICE_FAIT,
            self::STATUT_MANDATEE,
            self::STATUT_MISE_A_DISPOSITION_COMPTABLE,
            self::STATUT_COMPTABILISEE,
            self::STATUT_MISE_EN_PAIEMENT
        ];
    }

    /**
     * @param string $idFournisseur
     * @param string $periodeDateHeureEtatCourantDu
     * @param string $periodeDateHeureEtatCourantAu
     * @return array
     */
    public function getListeFacturesRecipiendaire(
        string $idFournisseur = "",
        string $periodeDateHeureEtatCourantDu = "",
        string $periodeDateHeureEtatCourantAu = ""
    ) {
        $ListeFacturesCPPFormat = [];
        $ListeFacturesCPP = $this->rechercheFactureParRecipiendaire(
            $idFournisseur,
            $periodeDateHeureEtatCourantDu,
            $periodeDateHeureEtatCourantAu
        );

        foreach ($ListeFacturesCPP['listeFactures'] as $FactureCPP) {
            $ListeFacturesCPPFormat[$FactureCPP["idFacture"]] = [
                "id_facture_cpp"        => $FactureCPP["idFacture"],
                "fournisseur"           => $FactureCPP["codeFournisseur"],
                "destinataire"          => $FactureCPP["idDestinataire"],
                "siret"                 => $FactureCPP["codeDestinataire"],
                "type_identifiant"      => $FactureCPP["typeIdentifiantFournisseur"],
                "fournisseur_raison_sociale" => $FactureCPP["designationFournisseur"],
                "service_destinataire"  => isset($FactureCPP["idServiceExecutant"]) ? $FactureCPP["idServiceExecutant"] : "",
                "service_destinataire_code" => isset($FactureCPP["codeServiceExecutant"]) ? $FactureCPP["codeServiceExecutant"] : "",
                "type_facture"          => isset($FactureCPP["typeFacture"]) ? $FactureCPP["typeFacture"] : "",
                "no_facture"            => $FactureCPP["numeroFacture"],
                "date_facture"          => isset($FactureCPP["dateFacture"]) ? $FactureCPP["dateFacture"] : "",
                "date_depot"            => isset($FactureCPP["dateDepot"]) ? $FactureCPP["dateDepot"] : "",
                "date_statut_courant"   => isset($FactureCPP["dateHeureEtatCourant"]) ? date("Y-m-d", strtotime($FactureCPP["dateHeureEtatCourant"])) : "",
                "montant_ttc"           => $FactureCPP["montantTTC"],
                "statut"                => $FactureCPP["statut"],
                "type_integration"      => self::TYPE_INTEGRATION_CPP,
            ];
        }
        return $ListeFacturesCPPFormat;
    }

    /**
     * @param string $periodeDateHeureEtatCourantDu
     * @param string $periodeDateHeureEtatCourantAu
     * @return array
     */
    public function getListeFacturesTravaux(
        string $periodeDateHeureEtatCourantDu = "",
        string $periodeDateHeureEtatCourantAu = ""
    ) {
        $ListeFacturesCPPFormat = [];
        $ListeFacturesCPP = $this->rechercheFactureTravaux(
            $periodeDateHeureEtatCourantDu,
            $periodeDateHeureEtatCourantAu
        );

        foreach ($ListeFacturesCPP['listeFactures'] as $FactureCPP) {
            $ListeFacturesCPPFormat[$FactureCPP["idFactureTravaux"]] = [
                "id_facture_cpp"        => $FactureCPP["idFactureTravaux"],
                "fournisseur"           => $FactureCPP["identifiantFournisseur"],
                "destinataire"          => $FactureCPP["idDestinataire"],
                "siret"                 => $FactureCPP["identifiantDestinataire"],
                "type_identifiant"      => $FactureCPP["typeIdentifiantFournisseur"],
                "fournisseur_raison_sociale" => $FactureCPP["designationFournisseur"],
                "service_destinataire"  => isset($FactureCPP["idServiceExecutant"]) ? $FactureCPP["idServiceExecutant"] : "",
                "service_destinataire_code" => isset($FactureCPP["codeServiceExecutant"]) ? $FactureCPP["codeServiceExecutant"] : "",
                "type_facture"          => isset($FactureCPP["typeFactureTravaux"]) ? $FactureCPP["typeFactureTravaux"] : "",
                "no_facture"            => $FactureCPP["numeroFactureTravaux"],
                "date_facture"          => isset($FactureCPP["dateFactureTravaux"]) ? date("Y-m-d", strtotime($FactureCPP["dateFactureTravaux"])) : "",
                "date_depot"            => isset($FactureCPP["dateDepot"]) ? date("Y-m-d", strtotime($FactureCPP["dateDepot"])) : "",
                "date_statut_courant"   => isset($FactureCPP["dateHeureEtatCourant"]) ? date("Y-m-d", strtotime($FactureCPP["dateHeureEtatCourant"])) : "",
                "montant_ttc"           => $FactureCPP["montantTTC"],
                "statut"                => $FactureCPP["statutFactureTravaux"],
                "type_integration"      => self::TYPE_INTEGRATION_CPP_TRAVAUX,
            ];
        }
        return $ListeFacturesCPPFormat;
    }

    /**
     * @param $IdFacture
     * @return array
     * @throws Exception
     */
    public function getHistoStatutFacture($IdFacture)
    {
        $HistoStatutFactureCPPFormat = [];
        $HistoStatutFactureCPP = $this->consulterHistoriqueFacture($IdFacture);
        if (!empty($HistoStatutFactureCPP['codeRetour'])) {
            throw new Exception('Le service Chorus Portail Pro a retourné une erreur : ' . $HistoStatutFactureCPP['codeRetour'] . ' - ' . $HistoStatutFactureCPP['libelle']);
        }
        if (empty($HistoStatutFactureCPP['statutCourantCode'])) {
            throw new Exception("L'historique de la facture retourné par le service Chorus Pro ne présente pas de statut courant");
        }
        $HistoStatutFactureCPPFormat['statut_courant'] = $HistoStatutFactureCPP['statutCourantCode'];
        foreach ($HistoStatutFactureCPP["historiquesDesStatuts"]["histoStatut"] as $HistoStatut) {
            $HistoStatutFactureCPPFormat['histo_statut'][] = [
                "statut_id"             => $HistoStatut["histoStatutId"],
                "statut_code"           => $HistoStatut["histoStatutCode"],
                "statut_date_passage"   => $HistoStatut["histoStatutDatePassage"],
                "statut_commentaire"    => isset($HistoStatut["histoStatutCommentaire"]) ? $HistoStatut["histoStatutCommentaire"] : "",
                "statut_utilisateur_nom" => isset($HistoStatut["histoStatutUtilisateurNom"]) ? $HistoStatut["histoStatutUtilisateurNom"] : "",
                "statut_utilisateur_prenom" => isset($HistoStatut["histoStatutUtilisateurPrenom"]) ? $HistoStatut["histoStatutUtilisateurPrenom"] : "",
            ];
        }
        return $HistoStatutFactureCPPFormat;
    }

    /**
     * @param $IdFacture
     * @param $Format
     * @return mixed
     */
    public function getFichierFacture($IdFacture, $Format)
    {
        return $this->telechargerGroupeFacture($Format, $IdFacture);
    }

    /**
     * @param $idFacture
     * @param $idNouveauStatut
     * @param string $motif
     * @param $numeroMandat
     * @return array
     */
    public function setStatutFacture($idFacture, $idNouveauStatut, $motif = "", $numeroMandat = "")
    {
        $ResultStatutFactureCPP = $this->traiterFactureRecue($idFacture, $idNouveauStatut, $motif, $numeroMandat);

        if (! empty($ResultStatutFactureCPP['codeRetour'])) {
            $ResultStatutFactureCPPFormat = [
                "retourFonctionnel"         => $ResultStatutFactureCPP['codeRetour'],
                "libelleRetourFonctionnel"  => $ResultStatutFactureCPP['libelle'],
            ];
        } else {
            $ResultStatutFactureCPPFormat = [
                "id_facture_cpp"    => $ResultStatutFactureCPP['idFacture'],
                "no_facture"        => $ResultStatutFactureCPP['numeroFacture'],
                "date_traitement"   => $ResultStatutFactureCPP['dateTraitement'],
                "statut"            => $ResultStatutFactureCPP['nouveauStatut'],
            ];
        }
        return $ResultStatutFactureCPPFormat;
    }
}
