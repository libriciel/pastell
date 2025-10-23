<?php

class CreationFactureCPP
{
    protected $objectInstancier;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
    }

    /**
     * @param array $docInfo
     * @return string
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function creerFactureCPP(array $docInfo)
    {
        $id_e = $docInfo['id_e'];
        $id_u = $docInfo['id_u'];
        $nom_flux_cpp = $docInfo['nom_flux_cpp'];

        $new_id_d = $this->creer($docInfo);
        $docInfoComplete = $this->integrerPivot($new_id_d, $docInfo);
        $titre = $this->alimenter($new_id_d, $docInfoComplete);
        $this->historiser($new_id_d, $docInfo) ;

        // Valorisation du cheminement d'après les valeurs par défaut définies dans le connecteur ParametrageFlux associé au flux Facture CPP
        $parametrageFluxFactureCPP = $this->objectInstancier->getInstance(ConnecteurFactory::class)->getConnecteurByType($id_e, $nom_flux_cpp, 'ParametrageFlux');
        if ($parametrageFluxFactureCPP) {
            /** @var ParametrageFluxFactureCPP $parametrageFluxFactureCPP */
            $this->parametrer($new_id_d, $parametrageFluxFactureCPP);
        }

        $actionChange = $this->objectInstancier->getInstance(ActionChange::class);

        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($new_id_d);
        $erreur = false;
        if (! $donneesFormulaire->isValidable()) {
            $erreur = $donneesFormulaire->getLastError();
        }

        if ($erreur) { // création avec erreur
            $message = "Création de la facture CPP avec erreur: #ID $new_id_d - type : $nom_flux_cpp - $titre - Erreur: $erreur";
            $actionChange->addAction($new_id_d, $id_e, $id_u, Action::CREATION, $message);
            return $message;
        } else { // création succcès
            $message = "Création de la facture CPP succès #ID $new_id_d - type : $nom_flux_cpp - $titre";
            $actionChange->addAction($new_id_d, $id_e, $id_u, Action::MODIFICATION, $message);

            // Valorisation de l'état suivant
            $actionChange->addAction($new_id_d, $id_e, $id_u, 'importation', 'Traitement du document');
            $this->objectInstancier->getInstance(ActionExecutorFactory::class)->executeOnDocument($id_e, 0, $new_id_d, 'orientation');

            return $message;
        }
    }

    /**
     * @param array $docInfo
     * @return mixed
     * @throws Exception
     */
    public function creer(array $docInfo)
    {
        $id_e = $docInfo['id_e'];
        $nom_flux_cpp = $docInfo['nom_flux_cpp'];
        $id_facture_cpp = $docInfo['id_facture_cpp'];

        $id_d = $this->isDocumentFacture($id_facture_cpp);
        if ($id_d) {
            throw new Exception("La facture " . $id_facture_cpp . " est déja présente sur le type de dossier facture Chorus Pro.");
        }
        if (!$this->objectInstancier->getInstance(DocumentTypeFactory::class)->isTypePresent($nom_flux_cpp)) {
            throw new Exception("Le type $nom_flux_cpp n'existe pas sur cette plateforme Pastell");
        }

        $new_id_d = $this->objectInstancier->getInstance(DocumentSQL::class)->getNewId();
        $this->objectInstancier->getInstance(DocumentSQL::class)->save($new_id_d, $nom_flux_cpp);
        $this->objectInstancier->getInstance(DocumentEntite::class)->addRole($new_id_d, $id_e, "editeur");

        return $new_id_d;
    }

    /**
     * @param $id_d
     * @param array $docInfo
     * @return array
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    public function integrerPivot($id_d, array $docInfo)
    {
        $id_facture_cpp = $docInfo['id_facture_cpp'];
        $fichier_facture = $docInfo['fichier_facture'];

        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($id_d);
        $donneesFormulaire->addFileFromCopy('fichier_facture', $id_facture_cpp . ".xml", $fichier_facture);
        //Extraction des donnees pivot
        /** @var ExtraireDonneesPivot $extraireDonneesPivot */
        $extraireDonneesPivot = $this->objectInstancier->getInstance(ExtraireDonneesPivot::class);
        $extraireDonneesPivot->getAllPJ($donneesFormulaire);

        $fournisseur = $extraireDonneesPivot->getFournisseur($donneesFormulaire);
        $debiteur = $extraireDonneesPivot->getDebiteur($donneesFormulaire);
        $donnees_facture = $extraireDonneesPivot->getDonneesFacture($donneesFormulaire);

        $donneesFormulaire->setData('facture_cadre', $donnees_facture['facture_cadre']);
        $donneesFormulaire->setData('facture_numero_engagement', $donnees_facture['facture_numero_engagement']);
        $donneesFormulaire->setData('facture_numero_marche', $donnees_facture['facture_numero_marche']);

        return array_merge($docInfo, $fournisseur, $debiteur, $donnees_facture);
    }

    /**
     * @param $id_d
     * @param array $cpp_array
     * @return array|string
     * @throws NotFoundException
     */
    public function alimenter($id_d, array $cpp_array)
    {
        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($id_d);

        // Alimentation des attributs simples disponibles dans $factureCPP
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_ID_FACTURE_CPP, $cpp_array['id_facture_cpp']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_STATUT_CPP, $cpp_array['statut']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_FOURNISSEUR, $cpp_array['fournisseur']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_DESTINATAIRE, $cpp_array['destinataire']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_SIRET, $cpp_array['siret']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_SERVICE_DESTINATAIRE, $cpp_array['service_destinataire']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_SERVICE_DESTINATAIRE_CODE, $cpp_array['service_destinataire_code']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_TYPE_FACTURE, $cpp_array['type_facture']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_NO_FACTURE, $cpp_array['no_facture']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_FACTURE, $cpp_array['date_facture']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_DEPOT, $cpp_array['date_depot']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_DATE_STATUT_COURANT, $cpp_array['date_statut_courant']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_MONTANT_TTC, $cpp_array['montant_ttc']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_TYPE_IDENTIFIANT, $cpp_array['type_identifiant']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_FOURNISSEUR_RAISON_SOCIALE, $cpp_array['fournisseur_raison_sociale']);
        $donneesFormulaire->setData(AttrFactureCPP::ATTR_TYPE_INTEGRATION, $cpp_array['type_integration']);

        // Affectation du titre au document
        $titre_fieldname = $donneesFormulaire->getFormulaire()->getTitreField();
        $titre = $donneesFormulaire->get($titre_fieldname);
        $this->objectInstancier->getInstance(DocumentSQL::class)->setTitre($id_d, $titre);

        return $titre;
    }

    /**
     * @param $id_d
     * @param $parametrageFluxFactureCPP
     * @throws NotFoundException
     */
    public function parametrer($id_d, $parametrageFluxFactureCPP)
    {
        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($id_d);

        $statut = $donneesFormulaire->get(AttrFactureCPP::ATTR_STATUT_CPP);
        $tabParam = $parametrageFluxFactureCPP->getParametres();
        if (($statut == "MISE_A_DISPOSITION") || ($statut == "COMPLETEE")) {
            $donneesFormulaire->setData('envoi_visa', $tabParam['envoi_visa']);
            $donneesFormulaire->setData('iparapheur_type', $tabParam['iparapheur_type']);
            $donneesFormulaire->setData('iparapheur_sous_type', $tabParam['iparapheur_sous_type']);
        }
        $donneesFormulaire->setData('envoi_ged', $tabParam['envoi_ged']);
        $donneesFormulaire->setData('envoi_sae', $tabParam['envoi_sae']);
        $donneesFormulaire->setData('check_mise_a_dispo_gf', $tabParam['check_mise_a_dispo_gf']);
        $donneesFormulaire->setData('envoi_auto', $tabParam['envoi_auto']);
    }

    /**
     * @param $id_d
     * @param array $docInfo
     * @throws Exception
     */
    public function historiser($id_d, array $docInfo)
    {
        $id_u = $docInfo['id_u'];
        $commentaire = $docInfo['commentaire'];
        $statut = $docInfo['statut'];

        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($id_d);

        $donneesFormulaire->setData('is_cpp', false);
        $donneesFormulaire->setData('date_mise_a_dispo', date("Y-m-d"));
        $donneesFormulaire->setData('date_passage_statut', date("Y-m-d"));

        $utilisateurSQL = $this->objectInstancier->getInstance(UtilisateurSQL::class);
        $utilisateur_info = $utilisateurSQL->getInfo($id_u);

        $histoStatutCPP = new HistoStatutCPP();
        $statut_file_content = $histoStatutCPP->create();
        $statut_file_content = $histoStatutCPP->addStatut(
            $statut_file_content,
            $statut,
            $commentaire,
            $utilisateur_info['nom'] ?? "",
            $utilisateur_info['prenom'] ?? ""
        );
        $donneesFormulaire->addFileFromData(
            'histo_statut_cpp',
            'histo_statut_cpp.json',
            $statut_file_content
        );
    }

    /**
     * @param $idFacture
     * @return mixed
     */
    public function isDocumentFacture($idFacture)
    {
        return $this->objectInstancier->getInstance(DocumentIndexSQL::class)->getByFieldValue('id_facture_cpp', $idFacture);
    }
}
