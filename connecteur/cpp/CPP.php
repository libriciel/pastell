<?php

/**
 * Class CPP
 *
 * La classe CPP fait le lien entre Pastell et Chorus
 *
 */

class CPP extends PortailFactureConnecteur
{
    private const DEPOSE_DEPUIS_NB_JOURS = 30;
    private const DEPOSE_AVANT_NB_JOURS = 0;

    private $no_change_statut_chorus;
    private $no_recup_facture;

    private int $depose_depuis_nb_jours = self::DEPOSE_DEPUIS_NB_JOURS;
    private int $depose_avant_nb_jours = self::DEPOSE_AVANT_NB_JOURS;

    /** @var DonneesFormulaire|false $globalConfig */
    private $globalConfig;

    /** @var  DonneesFormulaire $connecteurConfig */
    private $connecteurConfig;

    /** @var  CPPWrapper */
    private $cppWrapper;

    /** @var CPPWrapperFactory */
    private $cppWrapperFactory;

    /**
     * CPP constructor.
     * @param ObjectInstancier $objectInstancier
     */
    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->cppWrapperFactory = $objectInstancier->getInstance(CPPWrapperFactory::class);
        parent::__construct($objectInstancier);
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @throws CPPException
     * @throws Exception
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->connecteurConfig = $donneesFormulaire;
        $this->setConfigFromGlobalConnecteur();
        $this->no_change_statut_chorus = $donneesFormulaire->get('no_change_statut_chorus');
        $this->no_recup_facture = $donneesFormulaire->get("no_recup_facture");
        $cppWrapperConfig = new CPPWrapperConfig();
        $cppWrapperConfig->url_piste_get_token = $this->getFromLocalOrGlobalConfig('url_piste_get_token');
        $cppWrapperConfig->client_id = $this->getFromLocalOrGlobalConfig('client_id');
        $cppWrapperConfig->client_secret = $this->getFromLocalOrGlobalConfig('client_secret');
        $cppWrapperConfig->url_piste_api = $this->getFromLocalOrGlobalConfig('url_piste_api');
        $cppWrapperConfig->proxy = $this->getFromLocalOrGlobalConfig('proxy');
        $cppWrapperConfig->user_login = $donneesFormulaire->get('user_login');
        $cppWrapperConfig->user_password = $donneesFormulaire->get('user_password');
        $cppWrapperConfig->cpro_account = base64_encode(
            $donneesFormulaire->get('user_login') . ":" . $donneesFormulaire->get('user_password')
        );
        $cppWrapperConfig->user_role = $donneesFormulaire->get('user_role');
        $cppWrapperConfig->identifiant_structure_cpp = $donneesFormulaire->get('identifiant_structure_cpp');
        $cppWrapperConfig->service_destinataire = $donneesFormulaire->get('service_destinataire');
        $fetchInvoicesChoice = (int)$donneesFormulaire->get('fetch_invoices_option');
        if ($fetchInvoicesChoice === 2) {
            $cppWrapperConfig->fetchDownloadedInvoices = false;
        } elseif ($fetchInvoicesChoice === 3) {
            $cppWrapperConfig->fetchDownloadedInvoices = true;
        } else {
            $cppWrapperConfig->fetchDownloadedInvoices = null;
        }
        $this->setDeposeNbJours($donneesFormulaire);

        $this->cppWrapper = $this->cppWrapperFactory->newInstance();
        $this->cppWrapper->setCppWrapperConfig($cppWrapperConfig);
    }

    public function setDeposeNbJours(DonneesFormulaire $donneesFormulaire): void
    {
        $this->depose_depuis_nb_jours = $this->getNbJours($donneesFormulaire, 'depose_depuis_nb_jours')
            ?? self::DEPOSE_DEPUIS_NB_JOURS;
        $this->depose_avant_nb_jours = $this->getNbJours($donneesFormulaire, 'depose_avant_nb_jours')
            ?? self::DEPOSE_AVANT_NB_JOURS;

        if ($this->depose_avant_nb_jours > $this->depose_depuis_nb_jours) {
            $this->depose_avant_nb_jours = self::DEPOSE_AVANT_NB_JOURS;
        }
    }

    private function getNbJours(DonneesFormulaire $donneesFormulaire, string $field_name): ?int
    {
        if (! $donneesFormulaire->fieldExists($field_name)) {
            return null;
        }
        $nb_jours = $donneesFormulaire->getWithDefault($field_name);
        return is_numeric($nb_jours) ? (int)$nb_jours : null;
    }

    public function getDateDepuisLe(): string
    {
        $time_debut = time() - ($this->depose_depuis_nb_jours * 86400);
        return date('Y-m-d', $time_debut);
    }

    public function getDateJusquAu(): string
    {
        $time_au = time() - ($this->depose_avant_nb_jours * 86400);
        return date('Y-m-d', $time_au); //date('Y-m-d 23:59:59')
    }

    /**
     * @return mixed
     */
    public function getNoChangeStatutChorus()
    {
        return $this->no_change_statut_chorus;
    }

    /**
     * @return mixed
     */
    public function getNoRecupFacture()
    {
        return $this->no_recup_facture;
    }

    /**
     * @param $element_name
     * @return array|bool|string
     */
    protected function getFromLocalOrGlobalConfig($element_name)
    {
        $value = $this->connecteurConfig->get($element_name);

        if ($value) {
            return $value;
        }
        if ($this->globalConfig) {
            return $this->globalConfig->get($element_name);
        }
        return false;
    }

    private function setConfigFromGlobalConnecteur()
    {
        /** @var ConnecteurFactory $connecteurFactory */
        $connecteurFactory = $this->objectInstancier->getInstance(ConnecteurFactory::class);
        $this->globalConfig = $connecteurFactory->getGlobalConnecteurConfig('PortailFacture');
    }

    /**
     * @param $fonction_cpp
     * @param array $data
     * @return array|mixed
     * @throws Exception
     */
    public function call($fonction_cpp, array $data)
    {
        return $this->cppWrapper->call($fonction_cpp, $data);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function testConnexion(): bool
    {
        return $this->cppWrapper->testConnexion();
    }

    /**
     * @param string $idFournisseur
     * @param string $periodeDateHeureEtatCourantDu
     * @param string $periodeDateHeureEtatCourantAu
     * @return array
     * @throws Exception
     */
    public function rechercheFactureParRecipiendaire(
        string $idFournisseur = "",
        string $periodeDateHeureEtatCourantDu = "",
        string $periodeDateHeureEtatCourantAu = ""
    ): array {
        return $this->cppWrapper->rechercheFactureParRecipiendaire(
            $idFournisseur,
            $periodeDateHeureEtatCourantDu,
            $periodeDateHeureEtatCourantAu
        );
    }

    /**
     * @param string $periodeDateHeureEtatCourantDu
     * @param string $periodeDateHeureEtatCourantAu
     * @return array
     * @throws Exception
     */
    public function rechercheFactureTravaux(
        string $periodeDateHeureEtatCourantDu = "",
        string $periodeDateHeureEtatCourantAu = ""
    ): array {
        return $this->cppWrapper->rechercheFactureTravaux(
            $periodeDateHeureEtatCourantDu,
            $periodeDateHeureEtatCourantAu
        );
    }

    /**
     * @param $idFacture
     * @param int $nbResultatsMaximum
     * @return array|mixed
     * @throws Exception
     */
    protected function consulterHistoriqueFacture($idFacture, $nbResultatsMaximum = 50)
    {
        return $this->cppWrapper->consulterHistoriqueFacture($idFacture, $nbResultatsMaximum);
    }

    /**
     * @param $format
     * @param $idFacture
     * @return false|string
     * @throws Exception
     */
    protected function telechargerGroupeFacture($format, $idFacture)
    {
        return $this->cppWrapper->telechargerGroupeFacture($format, $idFacture);
    }

    /**
     * @param $idFacture
     * @param $idNouveauStatut
     * @param string $motif
     * @param string $numeroMandat
     * @return array|mixed
     * @throws Exception
     */
    protected function traiterFactureRecue($idFacture, $idNouveauStatut, $motif = "", $numeroMandat = "")
    {
        return $this->cppWrapper->traiterFactureRecue($idFacture, $idNouveauStatut, $motif, $numeroMandat);
    }

    /**
     * @return array|mixed
     * @throws Exception
     */
    public function listeStructure()
    {
        return $this->cppWrapper->recupererStructuresActivesPourDestinataire();
    }

    /**
     * @param $identifiant_structure
     * @param string $restreindre_structures
     * @return bool|mixed
     * @throws Exception
     */
    public function getIdentifiantStructureCPPByIdentifiantStructure(
        $identifiant_structure,
        string $restreindre_structures = ""
    ) {
        return $this->cppWrapper->getIdentifiantStructureCPPByIdentifiantStructure(
            $identifiant_structure,
            $restreindre_structures
        );
    }

    /**
     * @throws Exception
     */
    public function getListeService(int $pageCourante, int $nbResultatsParPage): array
    {
        return $this->cppWrapper->getListeService($pageCourante, $nbResultatsParPage);
    }

    /**
     * @throws Exception
     */
    public function getService(int $idService): array
    {
        return $this->cppWrapper->getService($idService);
    }


    /**
     * @param $filename
     * @param $filecontent
     * @param string $syntaxe_flux
     * @return array|mixed
     * @throws Exception
     */
    public function deposerXML($filename, $filecontent, string $syntaxe_flux = 'IN_DP_E1_UBL_INVOICE')
    {
        $data = [
            'fichierFlux' => base64_encode($filecontent),
            'nomFichier' => $filename,
            'syntaxeFlux' => $syntaxe_flux,
        ];
        return $this->call(CPPWrapper::DEPOSER_FLUX, $data);
    }

    /**
     * @param $filename
     * @param $filecontent
     * @return array|mixed
     * @throws Exception
     */
    public function deposerPDF($filename, $filecontent)
    {
        $data = [
            'fichierFacture' => base64_encode($filecontent),
            'nomFichier' => $filename,
            'formatDepot' => 'PDF_NON_SIGNE',
        ];
        return $this->call(CPPWrapper::DEPOSER_PDF, $data);
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @return array|mixed
     * @throws Exception
     */
    public function soumettreFacture(DonneesFormulaire $donneesFormulaire)
    {
        $data = [
            'modeDepot' => "DEPOT_PDF_API",
            'numeroFactureSaisi' => $donneesFormulaire->get('numero_facture'),
            'dateFacture' => $donneesFormulaire->get('date_facture'),
            'destinataire' => ['codeDestinataire' => $donneesFormulaire->get('code_destinataire')],
            'fournisseur' => ["idFournisseur" => intval($donneesFormulaire->get('id_cpp_fournisseur'))],
            'cadreDeFacturation' => ['codeCadreFacturation' => $donneesFormulaire->get('cadre_facturation')],
            'references' => [
                'deviseFacture' => $donneesFormulaire->get('code_devise_facture'),
                'typeFacture' => $donneesFormulaire->get('type_facture'),
                'typeTva' => $donneesFormulaire->get('type_tva'),
                'modePaiement' => 'VIREMENT',
            ],
            'montantTotal' => [
                'montantHtTotal' => floatval($donneesFormulaire->get('montant_ht_total')),
                'montantTVA' => floatval($donneesFormulaire->get('montant_tva')),
                'montantTtcTotal' => floatval($donneesFormulaire->get('montant_ttc_avant_remise_global_ttc')),
                'montantAPayer' => floatval($donneesFormulaire->get('montant_a_payer')),
            ],
            'pieceJointePrincipale' => [
                [
                    'pieceJointePrincipaleDesignation' => 'ma facture',
                    'pieceJointePrincipaleId' => intval($donneesFormulaire->get('piece_jointe_id'))
                ]
            ],
        ];

        if ($donneesFormulaire->get('code_service_executant')) {
            $data['destinataire']['codeServiceExecutant'] = $donneesFormulaire->get('code_service_executant');
        }
        if ($donneesFormulaire->get('id_cpp_service_fournisseur')) {
            $data['fournisseur']['idServiceFournisseur'] = $donneesFormulaire->get('id_cpp_service_fournisseur');
        }
        if ($donneesFormulaire->get('numero_bon_commande')) {
            $data['references']['numeroBonCommande'] = $donneesFormulaire->get('numero_bon_commande');
        }
        return $this->call(CPPWrapper::SOUMETTRE_FACTURE, $data);
    }

    /**
     * @param $numero_flux_depot
     * @return mixed
     * @throws Exception
     */
    public function getInfoByNumeroFluxDepot($numero_flux_depot)
    {
        return $this->cppWrapper->getInfoByNumeroFluxDepot($numero_flux_depot);
    }

    /**
     * @param $numero_flux_depot
     * @return array|mixed
     * @throws Exception
     */
    public function consulterCompteRenduImport($numero_flux_depot)
    {
        return $this->cppWrapper->consulterCompteRenduImport($numero_flux_depot);
    }

    /**
     * @param int $supplierCppId
     * @param string $invoiceNumber
     * @return int
     * @throws Exception
     */
    public function getInvoicePerSupplier(int $supplierCppId, string $invoiceNumber): int
    {
        return $this->cppWrapper->getCppInvoiceId($supplierCppId, $invoiceNumber);
    }
}
