<?php

use Monolog\Logger;

/**
 * Class CPPWrapper
 *
 * La classe CPPWrapper est indépendante de la mécanique Pastell
 *
 */
class CPPWrapper
{
    private const MAX_FACTURE_LIST = 999999;
    private const NB_FACTURE_PAR_PAGE = 1000;
    public const NB_SERVICE_PAR_PAGE = 20;

    private const PISTE_API_VERSION = 'v1';

    private const RECHERCHE_FACTURE_PAR_RECIPIENDAIRE = "factures/%s/rechercher/recipiendaire";
    private const CONSULTER_HISTORIQUE_FACTURE = "factures/%s/consulter/historique";
    private const TELECHARGER_GROUPE_FACTURE = "factures/%s/telecharger/groupe";
    private const TRAITER_FACTURE_RECUE = "factures/%s/traiter/recue";

    private const RECHERCHE_FACTURE_TRAVAUX = "facturesTravaux/%s/rechercher";

    private const RECUPERER_TAUXTVA = "transverses/%s/recuperer/tauxtva";
    private const RECUPERER_STRUCTURE_DESTINATAIRE = "transverses/%s/recuperer/structures/actives/destinataire";
    private const RECHERCHER_STRUCTURE = "structures/%s/rechercher";
    private const RECHERCHER_SERVICE = "structures/%s/rechercher/services";
    private const CONSULTER_SERVICE = "structures/%s/consulter/service";

    public const SOUMETTRE_FACTURE = "factures/%s/soumettre";
    public const DEPOSER_PDF = "factures/%s/deposer/pdf";
    public const DEPOSER_FLUX = "factures/%s/deposer/flux";
    private const RECHERCHE_FACTURE_PAR_FOURNISSEUR = "factures/%s/rechercher/fournisseur";
    private const CONSULTER_CR_DETAILLE = "transverses/%s/consulterCRDetaille";

    /** @var CurlWrapperFactory */
    private $curlWrapperFactory;

    /** @var MemoryCache */
    private $memoryCache;

    /** @var  UTF8Encoder */
    private $utf8Encoder;

    /** @var  CPPWrapperConfig */
    private $cppWrapperConfig;

    private $logger;

    /**
     * CPPWrapper constructor.
     * @param CurlWrapperFactory $curlWrapperFactory
     * @param MemoryCache $memoryCache
     * @param UTF8Encoder $utf8Encoder
     * @param Logger $logger
     */
    public function __construct(
        CurlWrapperFactory $curlWrapperFactory,
        MemoryCache $memoryCache,
        UTF8Encoder $utf8Encoder,
        Logger $logger
    ) {
        $this->curlWrapperFactory = $curlWrapperFactory;
        $this->memoryCache = $memoryCache;
        $this->utf8Encoder = $utf8Encoder;
        $this->logger = $logger;
    }

    /**
     * @param CPPWrapperConfig $cppWrapperConfig
     * @throws CPPException
     */
    public function setCppWrapperConfig(CPPWrapperConfig $cppWrapperConfig)
    {
        $this->cppWrapperConfig = $cppWrapperConfig;

        if (
                !($cppWrapperConfig->url_piste_get_token && $cppWrapperConfig->url_piste_api
                && $cppWrapperConfig->client_id && $cppWrapperConfig->client_secret)
        ) {
            throw new CPPException(
                "Il manque des éléments pour l'authentification PISTE, le connecteur global est-il bien associé ?"
            );
        }
    }

    /**
     * @param $fonction_cpp
     * @param array $data
     * @return array|mixed
     * @throws Exception
     */
    public function call($fonction_cpp, array $data)
    {
        $msg_call = "Chorus Call";
        $msg_response = "Chorus response";

        $curlWrapper = $this->curlWrapperFactory->getInstance();
        $curlWrapper->setProperties(CURLOPT_TIMEOUT, 60);
        assert((bool)$this->cppWrapperConfig);
        $cppWrapperConfig = $this->cppWrapperConfig;
        // Authentification
        if (!($cppWrapperConfig->user_login && $cppWrapperConfig->user_password)) {
            throw new Exception("Erreur: Utilisateur sans Login/Mot de passe");
        }

        $curlWrapper->addHeader('Accept-Charset', 'utf-8');
        $curlWrapper->addHeader('Authorization', $this->getToken());
        $curlWrapper->addHeader('cpro-account', $cppWrapperConfig->cpro_account);

        $url = trim($cppWrapperConfig->url_piste_api, "/") .
            "/cpro/" . sprintf($fonction_cpp, self::PISTE_API_VERSION);

        if ($cppWrapperConfig->proxy) {
            $curlWrapper->setProperties(CURLOPT_PROXY, $cppWrapperConfig->proxy);
        }

        $this->setJsonPostData($curlWrapper, $data);

        $this->logger->debug($msg_call, [$cppWrapperConfig->user_login,$url,$data]);

        $begin_chorus_call = microtime(true);
        $result = $curlWrapper->get($url);
        $end_chorus_call = microtime(true);

        $this->logger->info(
            $msg_call,
            [
                'user_login' => $cppWrapperConfig->user_login,
                'url' => $url,
                'http_response' => $curlWrapper->getLastHttpCode(),
                'time' => round($end_chorus_call - $begin_chorus_call, 3)
                ]
        );

        if (!$result) {
            $error_msg = $curlWrapper->getLastError();
            if (!$error_msg) {
                $error_msg = "Problème de connexion au serveur : Code HTTP " . $curlWrapper->getHTTPCode();
            }
            $this->logger->error(
                $msg_response,
                [$curlWrapper->getLastHttpCode(),$error_msg,$curlWrapper->getLastOutput()]
            );
            throw new Exception($error_msg);
        }
        if ($curlWrapper->getLastHttpCode() != 200) {
            $this->logger->error($msg_response, [$curlWrapper->getLastHttpCode(),$curlWrapper->getLastOutput()]);
            throw new Exception(
                "Utilisateur " . $cppWrapperConfig->user_login . "<br/>" .
                " Erreur code HTTP: " . $curlWrapper->getLastHttpCode() . "<br/>" . $result
            );
        }
        $this->logger->debug($msg_response, [mb_substr($result, 0, 100)]);
        return $this->utf8Encoder->decode(json_decode($result));
    }

    /**
     * @param CurlWrapper $curlWrapper
     * @param array $data
     */
    private function setJsonPostData(CurlWrapper $curlWrapper, array $data)
    {
        $curlWrapper->setProperties(CURLOPT_POST, true);
        if (empty($data)) {
            $curlWrapper->setProperties(CURLOPT_POSTFIELDS, '{}');
        } else {
            $curlWrapper->setProperties(CURLOPT_POSTFIELDS, json_encode($data));
        }
        $curlWrapper->addHeader('Content-Type', 'application/json');
    }

    /**
     * @return string
     * @throws CPPWrapperExceptionGetToken
     */
    private function getToken(): string
    {
        $memory_key = $this->getCacheKey($this->cppWrapperConfig->client_id);
        $token = $this->memoryCache->fetch($memory_key);
        if ($token) {
            return $token;
        }

        $curlWrapperToken = $this->curlWrapperFactory->getInstance();

        if ($this->cppWrapperConfig->proxy) {
            $curlWrapperToken->setProperties(CURLOPT_PROXY, $this->cppWrapperConfig->proxy);
        }

        $post_data_encode = [];
        $post_data_encode[urlencode("grant_type")] = urlencode("client_credentials");
        $post_data_encode[urlencode("client_id")] = urlencode($this->cppWrapperConfig->client_id);
        $post_data_encode[urlencode("client_secret")] = urlencode($this->cppWrapperConfig->client_secret);
        $post_data_encode[urlencode("scope")] = urlencode("openid");

        $curlWrapperToken->setPostDataUrlEncode($post_data_encode);
        $result = $curlWrapperToken->get(trim($this->cppWrapperConfig->url_piste_get_token, "/"));

        if (!$result) {
            $error_msg = $curlWrapperToken->getLastError();
            if (!$error_msg) {
                $error_msg = "Problème de connexion au serveur pour l'obtention du token : Code HTTP "
                    . $curlWrapperToken->getHTTPCode();
            }
            $this->logger->error(
                "PISTE get token response",
                [$curlWrapperToken->getLastHttpCode(), $error_msg,$curlWrapperToken->getLastOutput()]
            );
            throw new CPPWrapperExceptionGetToken("PISTE get token response: " . $error_msg);
        }
        if ($curlWrapperToken->getLastHttpCode() != 200) {
            $this->logger->error(
                "PISTE get token response",
                [$curlWrapperToken->getLastHttpCode(),$curlWrapperToken->getLastOutput()]
            );
            throw new CPPWrapperExceptionGetToken(
                "PISTE get token response - Erreur code HTTP: " .
                $curlWrapperToken->getLastHttpCode() . "<br/>" . $result
            );
        }

        $array_result = $this->utf8Encoder->decode(json_decode($result));
        if (! is_array($array_result)) {
            throw new CPPWrapperExceptionGetToken("PISTE impossible de déchiffrer le token");
        }
        if (
            !$array_result['token_type']
            || !$array_result['access_token']
            || !(is_int($array_result['expires_in']) && $array_result['expires_in'] > 0)
        ) {
            $this->logger->error(
                "PISTE get token invalid return",
                [$result]
            );
            throw new CPPWrapperExceptionGetToken(
                "PISTE get token invalid return: " .
                $result
            );
        }

        $this->logger->debug("PISTE get token response", [mb_substr($result, 0, 100)]);
        $token = $array_result['token_type'] . ' ' . $array_result['access_token'];

        $this->memoryCache->store(
            $memory_key,
            $token,
            $array_result['expires_in']
        );
        return $token;
    }

    /**
     * @param $client_id
     * @return string
     */
    private function getCacheKey($client_id): string
    {
        return "pastell_token_piste_" . $client_id;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function testConnexion(): bool
    {
        $this->call(self::RECUPERER_TAUXTVA, []);
        return true;
    }

    /**
     * @param string $idFournisseur
     * @param string $periodeDateHeureEtatCourantDu
     * @param string $periodeDateHeureEtatCourantAu
     * @return array
     * @throws CPPWrapperExceptionRechercheFactureParRecipiendaire
     * @throws Exception
     */
    public function rechercheFactureParRecipiendaire(
        string $idFournisseur = "",
        string $periodeDateHeureEtatCourantDu = "",
        string $periodeDateHeureEtatCourantAu = ""
    ): array {
        $result = [];
        $result['listeFactures'] = [];
        foreach (['FACTURE', 'FACTURE_TRAVAUX'] as $typeDemandePaiement) {
            $num_page = 0;
            do {
                $num_page++;
                $data = [
                    'typeDemandePaiement' => $typeDemandePaiement,
                    'paramRecherche' => [
                        'nbResultatsParPage' => self::NB_FACTURE_PAR_PAGE,
                        'pageResultatDemandee' => $num_page,
                        //A supprimer
                        'nbResultatsMaximum' => self::MAX_FACTURE_LIST
                    ]
                ];
                if ($this->cppWrapperConfig->fetchDownloadedInvoices !== null) {
                    $data['factureTelechargeeParDestinataire'] = $this->cppWrapperConfig->fetchDownloadedInvoices;
                }
                if ((int)$idFournisseur) {
                    $data['listeFournisseurs'][0] = ['idFournisseur' => (int)$idFournisseur];
                }
                if ((int)$this->cppWrapperConfig->identifiant_structure_cpp) {
                    $data['idDestinataire'] = (int)$this->cppWrapperConfig->identifiant_structure_cpp;
                }
                if ((int)$this->cppWrapperConfig->service_destinataire) {
                    $data['idServiceExecutant'] = (int)$this->cppWrapperConfig->service_destinataire;
                }
                if ($periodeDateHeureEtatCourantDu) {
                    $data['periodeDateHeureEtatCourantDu'] = $periodeDateHeureEtatCourantDu;
                }
                if ($periodeDateHeureEtatCourantAu) { // 2022-01-07T10:11:47.823Z
                    $data['periodeDateHeureEtatCourantAu'] = $periodeDateHeureEtatCourantAu . "T23:59:59";
                }

                $call_result = $this->call(self::RECHERCHE_FACTURE_PAR_RECIPIENDAIRE, $data);

                if (array_key_exists('listeFactures', $call_result)) {
                    foreach ($call_result['listeFactures'] as $facture) {
                        $result['listeFactures'][] = $facture;
                    }
                }
                $codeRetour = $call_result['codeRetour'] ?? '<non défini>';
                // 20000 : TRA_MSG_00.015 - La recherche n'a retourné aucun résultat
                if ($codeRetour == 20000) {
                    break;
                }
                if (!isset($call_result['pageCourante']) || !isset($call_result['pages'])) {
                    $libelle = $call_result['libelle'] ?? '<non défini>';
                    throw new CPPWrapperExceptionRechercheFactureParRecipiendaire(
                        "Réponse de rechercheFactureParRecipiendaire inattendue ! codeRetour=$codeRetour, libelle=$libelle"
                    );
                }
            } while ($call_result['pageCourante'] < $call_result['pages']);
        }
        return $result;
    }

    /**
     * @param $idFacture
     * @param int $nbResultatsMaximum
     * @return array|mixed
     * @throws Exception
     */
    public function consulterHistoriqueFacture($idFacture, int $nbResultatsMaximum = 50)
    {
        $data = [
            'idFacture' => (int)$idFacture,
            'nbResultatsMaximum' => $nbResultatsMaximum
        ];
        return $this->call(self::CONSULTER_HISTORIQUE_FACTURE, $data);
    }

    /**
     * @param $format
     * @param $idFacture
     * @return false|string
     * @throws Exception
     */
    public function telechargerGroupeFacture($format, $idFacture)
    {
        $data = [
            'format' => $format,
            'listeFacture' => [['idFacture' => (int)$idFacture]]
        ];
        $result = $this->call(self::TELECHARGER_GROUPE_FACTURE, $data);
        if (!array_key_exists('fichierResultat', $result)) {
            throw new Exception("Impossible de récupérer la facture");
        }
        return base64_decode($result['fichierResultat']);
    }

    /**
     * @param $idFacture
     * @param $idNouveauStatut
     * @param string $motif
     * @param string $numeroMandat
     * @return array|mixed
     * @throws Exception
     */
    public function traiterFactureRecue($idFacture, $idNouveauStatut, string $motif = "", string $numeroMandat = "")
    {
        $data = [
            'idFacture' => (int)$idFacture,
            'nouveauStatut' => $idNouveauStatut,
            'motif' => $motif,
            'numeroDPMandat' => $numeroMandat
        ];
        return $this->call(self::TRAITER_FACTURE_RECUE, $data);
    }

    /**
     * @return array
     * @throws CPPWrapperExceptionRechercheFactureTravaux
     * @throws Exception
     */
    public function rechercheFactureTravaux(
        string $periodeDateHeureEtatCourantDu = "",
        string $periodeDateHeureEtatCourantAu = ""
    ): array {
        $result = [];
        $result['listeFactures'] = [];

        if (!$this->cppWrapperConfig->user_role) {
            return $result;
        }

        $num_page = 0;
        do {
            $num_page++;
            $data = [
                'roleUtilisateur' => $this->cppWrapperConfig->user_role,
                'rechercheFactureTravaux' => [
                    'nbResultatsParPage' => self::NB_FACTURE_PAR_PAGE,
                    'pageResultatDemandee' => $num_page,
                ]
            ];

            if ((int)$this->cppWrapperConfig->identifiant_structure_cpp) {
                $data['idDestinataire'] = (int)$this->cppWrapperConfig->identifiant_structure_cpp;
            }
            if ((int)$this->cppWrapperConfig->service_destinataire) {
                $data['idServiceExecutant'] = (int)$this->cppWrapperConfig->service_destinataire;
            }
            if ($periodeDateHeureEtatCourantDu) {
                $data['periodeDateHeureEtatCourantDu'] = $periodeDateHeureEtatCourantDu;
            }
            if ($periodeDateHeureEtatCourantAu) { // 2022-01-07T10:11:47.823Z
                $data['periodeDateHeureEtatCourantAu'] = $periodeDateHeureEtatCourantAu . "T23:59:59";
            }

            if ($this->cppWrapperConfig->fetchDownloadedInvoices !== null) {
                $data['flagTelecharge'] = $this->cppWrapperConfig->fetchDownloadedInvoices;
            }

            $call_result = $this->call(self::RECHERCHE_FACTURE_TRAVAUX, $data);

            if (array_key_exists('listeFacturesTravaux', $call_result)) {
                foreach ($call_result['listeFacturesTravaux'] as $facture) {
                    $result['listeFactures'][] = $facture;
                }
            }
            $codeRetour = $call_result['codeRetour'] ?? '<non défini>';
            // 20007 : GFT_MSG_01.075 - La recherche n'a retourné aucun résultat
            if ($codeRetour == 20007) {
                break;
            }
            if (
                !isset($call_result['parametresRetour']['pageCourante'])
                || !isset($call_result['parametresRetour']['pages'])
            ) {
                $libelle = $call_result['libelle'] ?? '<non défini>';
                throw new CPPWrapperExceptionRechercheFactureTravaux(
                    "Réponse de rechercheFactureTravaux inattendue ! codeRetour=$codeRetour, libelle=$libelle"
                );
            }
        } while ($call_result['parametresRetour']['pageCourante'] < $call_result['parametresRetour']['pages']);

        return $result;
    }

    /**
     * @return array|mixed
     * @throws Exception
     */
    public function recupererStructuresActivesPourDestinataire()
    {
        return $this->call(self::RECUPERER_STRUCTURE_DESTINATAIRE, []);
    }

    /*Spec Chorus:
    * 1) L'attribut "ResteindreStructuresPrivees" est non renseigné ou est égal à "true" alors :
     * Le système retourne dans la liste des structures :
     * - Toutes les structures publiques correspondant aux critères de recherches,
     * - Seules les structures privées rattachées à l'utilisateur et correspondants aux critères de recherches
    * OU 2) L'attribut "ResteindreStructuresPrivees" est renseigné et est égal à "false", alors
     * l'ensemble des structures publiques et privées correspondant aux critères de recherches sont retournées.
    */
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
        if (! $identifiant_structure) {
            return false;
        }
        $data = [
            'structure' => ['identifiantStructure' => "$identifiant_structure"],
            'restreindreStructuresPrivees' => $restreindre_structures,
        ];
        $result = $this->call(self::RECHERCHER_STRUCTURE, $data);
        if (empty($result['listeStructures'][0]['idStructureCPP'])) {
            if (empty($result['listeStructures']['idStructureCPP'])) {
                return false;
            }
            return $result['listeStructures']['idStructureCPP'];
        }
        return $result['listeStructures'][0]['idStructureCPP'];
    }

    /**
     * @throws CPPWrapperServicesException
     * @throws Exception
     */
    public function getListeService(
        int $pageCourante = 1,
        int $nbResultatsParPage = self::NB_SERVICE_PAR_PAGE
    ): array {

        if (!$this->cppWrapperConfig->identifiant_structure_cpp) {
            throw new CPPWrapperServicesException(
                "Impossible de récupérer la liste des services si l'identifiant structure CPP n'est pas renseigné"
            );
        }
        $data = [
            'idStructure' => (int)$this->cppWrapperConfig->identifiant_structure_cpp,
            'parametresRechercherServicesStructure' => [
                'nbResultatsParPage' => $nbResultatsParPage,
                'pageResultatDemandee' => $pageCourante,
                'triColonne' => 'CodeService',
                'triSens' => 'Descendant'
            ]
        ];
        return $this->call(self::RECHERCHER_SERVICE, $data);
    }

    /**
     * @throws CPPWrapperServicesException
     * @throws Exception
     */
    public function getService(int $idService): array
    {
        if (!$this->cppWrapperConfig->identifiant_structure_cpp) {
            throw new CPPWrapperServicesException(
                "Impossible de récupérer le service si l'identifiant structure CPP n'est pas renseigné"
            );
        }
        $data = [
            'idStructure' => (int)$this->cppWrapperConfig->identifiant_structure_cpp,
            'idService' => $idService
        ];
        return $this->call(self::CONSULTER_SERVICE, $data);
    }


    /* WTF : Chorus gère un xieme identifiant pour les factures côté fournisseur */
    /**
     * @param $numero_flux_depot
     * @return mixed
     * @throws Exception
     */
    public function getInfoByNumeroFluxDepot($numero_flux_depot)
    {
        $data = ['numeroFluxDepot' => $numero_flux_depot];

        $result = $this->call(self::RECHERCHE_FACTURE_PAR_FOURNISSEUR, $data);

        if (empty($result['listeFactures'][0])) {
            throw new Exception("Impossible de trouver la facture $numero_flux_depot");
        }
        return $result['listeFactures'][0];
    }

    /**
     * @param $numero_flux_depot
     * @return array|mixed
     * @throws Exception
     */
    public function consulterCompteRenduImport($numero_flux_depot)
    {
        return $this->call(self::CONSULTER_CR_DETAILLE, ['numeroFluxDepot' => $numero_flux_depot]);
    }

    /**
     * Get the CPP invoice id from the invoice number and the CPP supplier id
     *
     * @param int $supplierCppId
     * @param string $invoiceNumber
     * @return int The CPP invoice id
     * @throws Exception when the invoice cannot be found on chorus
     */
    public function getCppInvoiceId(int $supplierCppId, string $invoiceNumber): int
    {
        $data = [
            'listeFournisseurs' => [['idFournisseur' => $supplierCppId]],
            'numeroFacture' => $invoiceNumber
        ];
        $result = $this->call(self::RECHERCHE_FACTURE_PAR_RECIPIENDAIRE, $data);
        if (empty($result['listeFactures'][0])) {
            throw new Exception("Impossible de trouver la facture $invoiceNumber");
        } elseif (count($result['listeFactures']) > 1) {
            throw new Exception("Plusieurs factures ont été trouvé avec le numéro $invoiceNumber");
        }
        return $result['listeFactures'][0]['idFacture'];
    }
}
