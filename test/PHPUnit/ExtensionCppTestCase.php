<?php

use Pastell\Service\Droit\DroitType;
use Pastell\Service\Droit\DroitService;

class ExtensionCppTestCase extends PastellTestCase
{
    public const TMP_EXTRACTED = __DIR__ . "/fixtures/fixtures_chorus/tmp_extracted";
    public const FICHIER_FACTURE_XML = __DIR__ . "/fixtures/fixtures_chorus/Facture-IN_DP_E2_CPP_FACTURE_MIN.xml";
    public const FICHIER_FACTURE_PDF = __DIR__ . "/fixtures/fixtures_chorus/Facture-PDF.pdf";
    public const FICHIER_PIVOT = __DIR__ . "/fixtures/fixtures_chorus/facture-pivot.xml";
    public const PIVOT_ZIP = __DIR__ . "/fixtures/fixtures_chorus/pivot.zip";

    private const DATE_DEPUIS_LE = "2019-01-01";
    private const DATE_DEPOT_FACTURE = "2019-07-11";

    private $workspace_path;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->workspace_path) {
            $tmpFolder = new TmpFolder();
            $tmpFolder->delete($this->workspace_path);
        }
    }

    public function reinitDatabase()
    {
        parent::reinitDatabase();

        /** @var RoleSQL $roleSQL */
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);

        $flux_id_list = [
            'facture-cpp',
            'facture-formulaire-pivot',
            'facture-chorus-fournisseur',
            'statut-facture-cpp'
        ];

        foreach ($flux_id_list as $id_flux) {
            $roleSQL->addDroit('admin', DroitService::getDroitFor($id_flux, DroitType::LECTURE));
            $roleSQL->addDroit('admin', DroitService::getDroitFor($id_flux, DroitType::EDITION));
        }
    }

    /**
     * On ne peut pas unzip des fichiers sur le vfs...
     * @throws Exception
     */
    public function reinitFileSystem()
    {
        $tmpFolder = new TmpFolder();
        $this->workspace_path = $tmpFolder->create();
        $this->getObjectInstancier()->setInstance('workspacePath', $this->workspace_path);
    }

    /**
     * @param $flux_id
     * @return mixed
     */
    protected function createCppConnector($flux_id)
    {
        $result = $this->createConnector('cpp', 'CPP');
        $id_ce = $result['id_ce'];

        $this->configureConnector($id_ce, [
            'url_piste_get_token' => 'cpp url token',
            'client_id' => "61cde1ef-41ab-441c-b23f-95991f9d919g",
            'client_secret' => "bd307b18-298e-45a7-a4ef-9169200fad63",
            'url_piste_api' => 'cpp url api',
            'identifiant_structure' => '00000000000727',
            'depose_depuis_nb_jours' => (time() - strtotime(self::DATE_DEPUIS_LE)) / 86440,
        ]);

        $this->associateFluxWithConnector($id_ce, $flux_id, 'PortailFacture');

        return $id_ce;
    }

    /**
     * Create a param-chorus-fournisseur connector, set recup_status_facture and associate it to the flux in parameter
     *
     * @param string $flux_id
     * @param boolean $recup_status_facture
     */
    protected function createParamChorusFournisseurConnector($flux_id, $recup_status_facture)
    {
        $param = $this->createConnector('param-chorus-fournisseur', 'Parametre Chorus Fournisseur');
        $id_ce = $param['id_ce'];

        $this->configureConnector($id_ce, [
            'recup_status_facture' => $recup_status_facture,
        ]);

        $this->associateFluxWithConnector($id_ce, $flux_id, 'ParamChorusFournisseur');
    }

    /**
     * @param $flux_id
     */
    protected function createParamChorusConnector($flux_id)
    {
        $param = $this->createConnector('parametrage-flux-facture-cpp', 'Parametre Chorus');
        $id_ce = $param['id_ce'];

        $this->associateFluxWithConnector($id_ce, $flux_id, 'ParametrageFlux');
    }

    /**
     * @param $date_dernier_statut
     * @param $dernier_statut
     * @return array
     */
    protected function getConsulterHistoriqueFacture($date_dernier_statut, $dernier_statut)
    {
        return  [
            'codeRetour' => 0,
            'libelle' => 'GCU_MSG_01_000',
            'historiquesDesStatuts' =>  [
                'histoStatut' =>  [
                    [
                        'histoStatutCode' => $dernier_statut,
                        'histoStatutDatePassage' => $date_dernier_statut,
                        'histoStatutId' => 8548204
                    ],
                     [
                        'histoStatutCode' => "DEPOSEE",
                        'histoStatutDatePassage' => "2018-04-19 11:08",
                        'histoStatutId' => 8548152
                    ]
                ]
            ],
            'idFacture' => 2194673,
            'modeDepot' => "DEPOT_PDF_API",
            'numeroFacture' => "FAC19-2512",
            'statutCourantCode' => $dernier_statut
        ];
    }

    /**
     * @param string $min_date_depot
     * @param string $statut_facture
     * @return array
     */
    protected function getrechercheFactureParRecipiendaire($min_date_depot = self::DATE_DEPUIS_LE, $statut_facture = 'MISE_A_DISPOSITION'): array
    {
        if ($min_date_depot > self::DATE_DEPOT_FACTURE) {
            return  [
                'listeFactures' =>   []
            ];
        }
        return  [
            'listeFactures' =>   [
                 [
                    'codeDestinataire' => '00000000013456',
                    'codeFournisseur' => '00000000000727',
                    'dateDepot' => self::DATE_DEPOT_FACTURE,
                    'dateFacture' => '2019-07-11',
                    'dateHeureEtatCourant' => '2019-07-11T15:45:39.674+02:00',
                    'designationDestinataire' => 'TAA074DESTINATAIRE',
                    'designationFournisseur' => 'TAA001DESTINATAIRE',
                    'devise' => 'EUR',
                    'factureTelechargeeParDestinataire' => true,
                    'idDestinataire' => '25784152',
                    'idFacture' => '3346947',
                    'montantAPayer' => '10',
                    'montantHT' => '10',
                    'montantTTC' => '20',
                    'numeroFacture' => '20190711-1',
                    'statut' => $statut_facture,
                    'typeDemandePaiement' => 'FACTURE',
                    'typeFacture' => 'FACTURE',
                    'typeIdentifiantFournisseur' => 'SIRET',
                 ]
            ]
        ];
    }

    /**
     * @param string $min_date_depot
     * @return array
     */
    protected function getrechercheFactureTravaux($min_date_depot = self::DATE_DEPUIS_LE)
    {
        if ($min_date_depot > self::DATE_DEPOT_FACTURE) {
            return  [
                'listeFactures' =>   []
            ];
        }
        return  [
            'listeFactures' =>   [
                 [
                    'identifiantDestinataire' => '00000000013456',
                    'identifiantFournisseur' => '00000000000727',
                    'dateDepot' => '2019-07-11',
                    'dateFactureTravaux' => self::DATE_DEPOT_FACTURE,
                    'dateHeureEtatCourant' => '2019-07-11T15:45:39.674+02:00',
                    'designationDestinataire' => 'TAA074DESTINATAIRE',
                    'designationFournisseur' => 'TAA001DESTINATAIRE',
                    'devise' => 'EUR',
                    'factureTelechargeeParDestinataire' => true,
                    'idDestinataire' => '25784152',
                    'idFactureTravaux' => '4100169',
                    'montantAPayer' => '10',
                    'montantHT' => '10',
                    'montantTTC' => '20',
                    'numeroFactureTravaux' => '20190711-1',
                    'statutFactureTravaux' => 'A_ASSOCIER_MOA',
                    'typeDemandePaiement' => 'FACTURE_TRAVAUX',
                    'typeFactureTravaux' => 'PROJET_DECOMPTE_MENSUEL',
                    'typeIdentifiantFournisseur' => 'SIRET',
                 ]
            ]
        ];
    }

    /**
     * @param $statut_cible
     * @return array
     */
    protected function getTraiterFactureRecue($statut_cible)
    {
        return  [
            'codeRetour' => 0,
            'libelle' => 'GCU_MSG_01_000',
            'idFacture' => 2194673,
            'numeroFacture' => "FAC19-2512",
            'dateTraitement' => date("Y-m-d"),
            'nouveauStatut' => $statut_cible
        ];
    }
}
