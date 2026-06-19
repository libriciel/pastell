<?php

declare(strict_types=1);

use Monolog\Logger;

class CPPWrapperTest extends ExtensionCppTestCase
{
    use CurlUtilitiesTestTrait;

    private const CLIENT_ID = 'client_id';
    private const MEMORY_KEY = 'pastell_token_piste_' . self::CLIENT_ID;
    private const TOKEN = 'Bearer theToken';
    private const PISTE_TOKEN = 'https://sandbox-oauth.aife.economie.gouv.fr/api/oauth/token';
    private const PISTE_API_BASE = 'https://sandbox-api.aife.economie.gouv.fr';
    private const PISTE_TVA_ENDPOINT = self::PISTE_API_BASE . '/cpro/transverses/v1/recuperer/tauxtva';

    protected function setUp(): void
    {
        parent::setUp();
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->store(self::MEMORY_KEY, self::TOKEN);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->delete(self::MEMORY_KEY);
    }

    private function getDefaultWrapperConfig(): CPPWrapperConfig
    {
        $cppWrapperConfig = new CPPWrapperConfig();

        $cppWrapperConfig->user_login = 'TEST';
        $cppWrapperConfig->user_password = 'TEST';

        $cppWrapperConfig->url_piste_get_token = self::PISTE_TOKEN;
        $cppWrapperConfig->client_id = self::CLIENT_ID;
        $cppWrapperConfig->client_secret = 'secret';
        $cppWrapperConfig->url_piste_api = self::PISTE_API_BASE;
        $cppWrapperConfig->cpro_account = base64_encode(
            $cppWrapperConfig->user_login . ':' . $cppWrapperConfig->user_password
        );
        return $cppWrapperConfig;
    }

    /**
     * @throws CPPException
     */
    private function getCPPWrapper(?CPPWrapperConfig $cppWrapperConfig = null): CPPWrapper
    {
        $cppWrapper = new CPPWrapper(
            $this->getObjectInstancier()->getInstance(CurlWrapperFactory::class),
            $this->getObjectInstancier()->getInstance(MemoryCache::class),
            $this->getObjectInstancier()->getInstance(Logger::class)
        );
        $cppWrapper->setCppWrapperConfig($cppWrapperConfig ?? $this->getDefaultWrapperConfig());
        return $cppWrapper;
    }

    /**
     * @throws CPPException
     * @throws JsonException
     * @throws Exception
     */
    public function testTestConnexion(): void
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'TRA_MSG_00.000',
            'listeTauxTva' => [
                [
                    'codeTauxTva' => 'TVA1',
                    'libelleTauxTva' => 'Art 293B(FranchiseEnBase)',
                    'valeurTauxTva' => 0,
                ],
            ],
        ];

        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => json_encode($returnData, JSON_THROW_ON_ERROR),
            ],
        );

        static::assertTrue($this->getCPPWrapper()->testConnexion());
    }

    /**
     * @throws CPPException
     * @throws JsonException
     * @throws Exception
     */
    public function testWhenGettingTheInvoiceCppId(): void
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'libelle',
            'listeFactures' => [
                [
                    'idFacture' => 1234
                ],
            ],
        ];

        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => '',
                self::PISTE_API_BASE . '/cpro/factures/v1/rechercher/recipiendaire' => json_encode(
                    $returnData,
                    JSON_THROW_ON_ERROR
                ),
            ],
        );

        static::assertSame(
            1234,
            $this->getCPPWrapper()->getCppInvoiceId(1, '1111')
        );
    }

    /**
     * @throws CPPException
     * @throws JsonException
     */
    public function testWhenNoInvoiceIsReturned(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Impossible de trouver la facture 1111');
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'libelle',
            'listeFactures' => []
        ];

        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => '',
                self::PISTE_API_BASE . '/cpro/factures/v1/rechercher/recipiendaire' => json_encode(
                    $returnData,
                    JSON_THROW_ON_ERROR
                ),
            ],
        );

        $this->getCPPWrapper()->getCppInvoiceId(1, '1111');
    }

    /**
     * @throws CPPException
     * @throws JsonException
     */
    public function testWhenMultipleInvoicesAreReturned(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Plusieurs factures ont été trouvé avec le numéro 1111');
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'libelle',
            'listeFactures' => [
                [
                    'idFacture' => 1234,
                ],
                [
                    'idFacture' => 12345,
                ],
            ],
        ];

        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => '',
                self::PISTE_API_BASE . '/cpro/factures/v1/rechercher/recipiendaire' => json_encode(
                    $returnData,
                    JSON_THROW_ON_ERROR
                ),
            ],
        );

        $this->getCPPWrapper()->getCppInvoiceId(1, '1111');
    }

    /**
     * @throws CPPException
     * @throws JsonException
     * @throws Exception
     */
    public function testGetIdentifiantStructureCPP(): void
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'libelle',
            'listeStructures' => [
                [
                    'idStructureCPP' => 25783752,
                    'identifiantStructure' => '00000000012887',
                    'designationStructure' => 'TAA070DESTINATAIRE',
                    'statut' => 'ACTIVE',
                ],
            ],
        ];

        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => '',
                self::PISTE_API_BASE . '/cpro/structures/v1/rechercher' => json_encode(
                    $returnData,
                    JSON_THROW_ON_ERROR
                ),
            ],
        );

        static::assertSame(
            25783752,
            $this->getCPPWrapper()->getIdentifiantStructureCPPByIdentifiantStructure('00000000012887')
        );
    }

    /**
     * @throws CPPException
     * @throws CPPWrapperServicesException
     * @throws JsonException
     */
    public function testGetListeService(): void
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'TRA_MSG_00.000',
            'listeServices' => [
                [
                    'idService' => 10136558,
                    'codeService' => 'SERVICE_DESTINATAIRETAA070',
                    'libelleService' => 'SERVICE_DESTINATAIRETAA070',
                    'dateDbtService' => '2016-12-28 08:30',
                    'estActif' => true,
                ],
                [
                    'idService' => 10136557,
                    'codeService' => 'FACTURES_PUBLIQUES',
                    'libelleService' => 'Service des factures publiques',
                    'dateDbtService' => '2016-12-28 08:30',
                    'estActif' => true,
                ],
            ],
            'parametresRetour' => [
                'pageCourante' => 1,
                'pages' => 1,
                'nbResultatsParPage' => 20,
                'total' => 2,
            ],
        ];

        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => '',
                self::PISTE_API_BASE . '/cpro/structures/v1/rechercher/services' => json_encode(
                    $returnData,
                    JSON_THROW_ON_ERROR
                ),
            ],
        );

        $cppWrapperConfig = $this->getDefaultWrapperConfig();
        $cppWrapperConfig->identifiant_structure_cpp = 25783752;
        $cppWrapper = $this->getCPPWrapper($cppWrapperConfig);

        static::assertSame($returnData, $cppWrapper->getListeService());
    }

    /**
     * @throws CPPException
     * @throws CPPWrapperServicesException
     * @throws JsonException
     */
    public function testGetService(): void
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'TRA_MSG_00.000',
            'parametres' => [
                'dateCreation' => '2016-12-28T08:31:19+01:00',
                'dateDebutValidite' => '2016-12-28T08:30:47+01:00',
                'numeroEngagement' => false,
            ],
            'informationsGenerales' => [
                'codeService' => 'FACTURES_PUBLIQUES',
                'nomService' => 'Service des factures publiques',
                'descriptionService' => 'Service des factures publiques',
            ],
            'adressePostale' => [
                'adresse' => '1 rue Test',
                'complementAdresse1' => 'Batiment A',
                'complementAdresse2' => 'Etage 1',
                'codePostal' => '75000',
                'ville' => 'Test',
                'pays' => 'France',
                'telephone' => '234567890',
                'indicatifTelephone' => '+33',
                'fax' => '234567890',
                'indicatifFax' => '+33',
            ]
        ];

        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => '',
                self::PISTE_API_BASE . '/cpro/structures/v1/consulter/service' => json_encode(
                    $returnData,
                    JSON_THROW_ON_ERROR
                ),
            ],
        );

        $cppWrapperConfig = $this->getDefaultWrapperConfig();
        $cppWrapperConfig->identifiant_structure_cpp = 25783752;
        $cppWrapper = $this->getCPPWrapper($cppWrapperConfig);

        static::assertSame($returnData, $cppWrapper->getService(10136557));
    }


    /**
     * @throws CPPException
     * @throws Exception
     */
    public function testGetIdentifiantStructureCPPWhenFalse(): void
    {
        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => '',
            ]
        );

        static::assertFalse($this->getCPPWrapper()->getIdentifiantStructureCPPByIdentifiantStructure(''));
    }

    /**
     * @throws CPPException
     * @throws JsonException
     * @throws Exception
     */
    public function testGetToken(): void
    {
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->delete(self::MEMORY_KEY);
        $returnData = [
            'access_token' => 'theToken',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'openid',
        ];
        $this->mockCurl(
            [
                self::PISTE_TOKEN  => json_encode($returnData, JSON_THROW_ON_ERROR),
                self::PISTE_TVA_ENDPOINT => 'ok',
            ]
        );

        static::assertTrue($this->getCPPWrapper()->testConnexion());

        $token = $this->getObjectInstancier()->getInstance(MemoryCache::class)->fetch(self::MEMORY_KEY);
        static::assertSame(self::TOKEN, $token);
    }

    /**
     * @throws CPPException
     * @throws JsonException
     * @throws Exception
     */
    public function testGetTokenInvalid(): void
    {
        $this->expectException(CPPWrapperExceptionGetToken::class);
        $this->expectExceptionMessage(
            'PISTE get token invalid return: {"access_token":"","token_type":"Bearer","expires_in":42,"scope":"openid"}'
        );

        $this->getObjectInstancier()->getInstance(MemoryCache::class)->delete(self::MEMORY_KEY);
        $returnData = [
            'access_token' => '',
            'token_type' => 'Bearer',
            'expires_in' => 42,
            'scope' => 'openid',
        ];

        $this->mockCurl(
            [
                self::PISTE_TOKEN => json_encode($returnData, JSON_THROW_ON_ERROR),
            ]
        );
        $this->getCPPWrapper()->testConnexion();

        $token = $this->getObjectInstancier()->getInstance(MemoryCache::class)->fetch(self::MEMORY_KEY);
        static::assertSame(self::TOKEN, $token);
    }

    public static function getRechercheFactureTravauxProvider(): \Generator
    {
        yield 'FactureNotEmpty' => [
            'MOA',
            [
                'listeFactures' => [
                    [
                        'idFactureTravaux' => 1234,
                    ],
                ],
            ],
        ];

        yield 'FactureEmpty_NoRole' =>
        [
            '',
            [
                'listeFactures' => [],
            ],
        ];
    }

    /**
     * @dataProvider getRechercheFactureTravauxProvider
     * @throws CPPException
     * @throws CPPWrapperExceptionRechercheFactureTravaux
     * @throws JsonException
     */
    public function testRechercheFactureTravaux(string $userRole, array $expected): void
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'TRA_MSG_00.000',
            'parametresRetour' => [
                'pageCourante' => 1,
                'pages' => 1,
            ],
            'listeFacturesTravaux' => [
                [
                    'idFactureTravaux' => 1234,
                ],
            ],
        ];

        $this->mockCurl(
            [
                self::PISTE_TVA_ENDPOINT => '',
                self::PISTE_API_BASE . '/cpro/facturesTravaux/v1/rechercher' => json_encode(
                    $returnData,
                    JSON_THROW_ON_ERROR
                ),
            ]
        );

        $cppWrapperConfig = $this->getDefaultWrapperConfig();
        $cppWrapperConfig->user_role = $userRole;
        $cppWrapper = $this->getCPPWrapper($cppWrapperConfig);

        static::assertSame($expected, $cppWrapper->rechercheFactureTravaux());
    }
}
