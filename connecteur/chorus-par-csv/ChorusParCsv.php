<?php

class ChorusParCsv extends PortailFactureConnecteur
{
    private const DEPOSE_DEPUIS_NB_JOURS = 30;

    private int $depose_depuis_nb_jours = self::DEPOSE_DEPUIS_NB_JOURS;

    private $user_login;

    /** @var DonneesFormulaire|false $globalConfig */
    private $globalConfig;

    /** @var  DonneesFormulaire $connecteurConfig */
    private $connecteurConfig;

    /** @var  CPPWrapper */
    private $cppWrapper;

    /** @var CPPWrapperFactory */
    private $cppWrapperFactory;

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
        $cppWrapperConfig = new CPPWrapperConfig();
        $cppWrapperConfig->url_piste_get_token = $this->getFromLocalOrGlobalConfig('url_piste_get_token');
        $cppWrapperConfig->client_id = $this->getFromLocalOrGlobalConfig('client_id');
        $cppWrapperConfig->client_secret = $this->getFromLocalOrGlobalConfig('client_secret');
        $cppWrapperConfig->url_piste_api = $this->getFromLocalOrGlobalConfig('url_piste_api');
        $cppWrapperConfig->user_login = $donneesFormulaire->get('user_login');
        $cppWrapperConfig->user_password = $donneesFormulaire->get('user_password');
        $cppWrapperConfig->cpro_account = base64_encode(
            $donneesFormulaire->get('user_login') . ":" . $donneesFormulaire->get('user_password')
        );
        $cppWrapperConfig->identifiant_structure_cpp = $donneesFormulaire->get('identifiant_structure_cpp');

        $this->depose_depuis_nb_jours = $this->getDeposeDepuisNbJours($donneesFormulaire);
        $this->user_login = $donneesFormulaire->get('user_login');

        $this->cppWrapper = $this->cppWrapperFactory->newInstance();
        $this->cppWrapper->setCppWrapperConfig($cppWrapperConfig);
    }

    /**
     * @return mixed
     */
    public function getUserLogin()
    {
        return $this->user_login;
    }

    public function getDeposeDepuisNbJours(DonneesFormulaire $donneesFormulaire): int
    {
        $depose_depuis_nb_jours = $donneesFormulaire->getWithDefault('depose_depuis_nb_jours');
        return is_numeric($depose_depuis_nb_jours) ? (int)$depose_depuis_nb_jours : self::DEPOSE_DEPUIS_NB_JOURS;
    }

    public function getDateDepuisLe(): string
    {
        $time_debut = time() - ($this->depose_depuis_nb_jours * 86400);
        return date('Y-m-d', $time_debut);
    }

    /**
     * @param $element_name
     * @return array|bool|string
     */
    protected function getFromLocalOrGlobalConfig($element_name): array|bool|string
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
    protected function rechercheFactureTravaux(
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
        return $this->cppWrapper->traiterFactureRecue($idFacture, $idNouveauStatut, $motif);
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
}
