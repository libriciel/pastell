<?php

use Monolog\Logger;

class CPPWrapperTest extends ExtensionCppTestCase
{
    private const MEMORY_KEY = "pastell_token_piste_61cde1ef-41ab-441c-b23f-95991f9d919g";
    private const TOKEN = "Bearer BHv3LJUSWnGl5JRzxm8948mqhvv8P1UQLtCdjj1HgKdm8vQgmkeWQF";

    /** @var CPPWrapper */
    private $cppWrapper;

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

    /**
     * @return CPPWrapperConfig
     */
    private function getDefaultWrapperConfig(): CPPWrapperConfig
    {
        $cppWrapperConfig = new CPPWrapperConfig();

        $cppWrapperConfig->user_login = "TEST";
        $cppWrapperConfig->user_password = "TEST";

        $cppWrapperConfig->url_piste_get_token = "https://sandbox-oauth.aife.economie.gouv.fr/api/oauth/token";
        $cppWrapperConfig->client_id = "61cde1ef-41ab-441c-b23f-95991f9d919g";
        $cppWrapperConfig->client_secret = "bd307b18-298e-45a7-a4ef-9169200fad63";
        $cppWrapperConfig->url_piste_api = "https://sandbox-api.aife.economie.gouv.fr/";
        $cppWrapperConfig->cpro_account = base64_encode(
            $cppWrapperConfig->user_login . ":" . $cppWrapperConfig->user_password
        );
        return $cppWrapperConfig;
    }

    /**
     * @param CPPWrapperConfig|null $cppWrapperConfig
     * @return CPPWrapper
     * @throws CPPException
     */
    private function getCPPWrapper(CPPWrapperConfig $cppWrapperConfig = null): CPPWrapper
    {
        $cppWrapper = new CPPWrapper(
            $this->getObjectInstancier()->getInstance(CurlWrapperFactory::class),
            $this->getObjectInstancier()->getInstance(MemoryCache::class),
            $this->getObjectInstancier()->getInstance(UTF8Encoder::class),
            $this->getObjectInstancier()->getInstance(Logger::class)
        );
        $cppWrapper->setCppWrapperConfig($cppWrapperConfig ?? $this->getDefaultWrapperConfig());
        return $cppWrapper;
    }

    /**
     * @throws Exception
     */
    public function testTestConnexion()
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'TRA_MSG_00.000',
            'listeTauxTva' => [
                [
                    "codeTauxTva" => "TVA1",
                    "libelleTauxTva" => "Art 293B(FranchiseEnBase)",
                    "valeurTauxTva" => 0
                ]
            ]
        ];
        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapper->expects($this->any())->method('get')->willReturn(json_encode($returnData));
        $curlWrapper->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->expects($this->any())->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $this->cppWrapper = $this->getCPPWrapper();

        $this->assertTrue($this->cppWrapper->testConnexion());
    }

    /**
     * When successfully getting the cpp id of the invoice
     * @test
     * @throws Exception
     */
    public function whenGettingTheInvoiceCppId()
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'libelle',
            'listeFactures' => [
                [
                    'idFacture' => 1234
                ]
            ]
        ];
        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)->disableOriginalConstructor()->getMock();
        $curlWrapper->expects($this->any())->method('get')->willReturn(json_encode($returnData));
        $curlWrapper->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->expects($this->any())->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $this->cppWrapper = $this->getCPPWrapper();

        $this->assertEquals(1234, $this->cppWrapper->getCppInvoiceId(1, '1111'));
    }

    /**
     * When no invoice is returned by chorus
     * @test
     * @throws Exception
     */
    public function whenNoInvoiceIsReturned()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Impossible de trouver la facture 1111");
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'libelle',
            'listeFactures' => []
        ];
        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)->disableOriginalConstructor()->getMock();
        $curlWrapper->expects($this->any())->method('get')->willReturn(json_encode($returnData));
        $curlWrapper->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->expects($this->any())->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $this->cppWrapper = $this->getCPPWrapper();

        $this->cppWrapper->getCppInvoiceId(1, '1111');
    }

    /**
     * When multiple invoices are returned by chorus (unlikely to happen)
     * @test
     * @throws Exception
     */
    public function whenMultipleInvoicesAreReturned()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Plusieurs factures ont été trouvé avec le numéro 1111");
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'libelle',
            'listeFactures' => [
                [
                    'idFacture' => 1234
                ],
                [
                    'idFacture' => 12345
                ]
            ]
        ];
        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)->disableOriginalConstructor()->getMock();
        $curlWrapper->expects($this->any())->method('get')->willReturn(json_encode($returnData));
        $curlWrapper->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->expects($this->any())->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $this->cppWrapper = $this->getCPPWrapper();

        $this->cppWrapper->getCppInvoiceId(1, '1111');
    }

    /**
     * @throws Exception
     */
    public function testGetIdentifiantStructureCPP()
    {
        $returnData = [
            'codeRetour' => 0,
            'libelle' => 'libelle',
            'listeStructures' => [
                [
                    'idStructureCPP' => 25783752,
                    'identifiantStructure' => '00000000012887',
                    'designationStructure' => 'TAA070DESTINATAIRE',
                    'statut' => 'ACTIVE'
                ]
            ]
        ];
        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)->disableOriginalConstructor()->getMock();
        $curlWrapper->expects($this->any())->method('get')->willReturn(json_encode($returnData));
        $curlWrapper->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->expects($this->any())->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $this->cppWrapper = $this->getCPPWrapper();

        $this->assertEquals(
            25783752,
            $this->cppWrapper->GetIdentifiantStructureCPPByIdentifiantStructure("00000000012887")
        );
    }

    /**
     * @throws CPPException
     * @throws JsonException
     * @throws CPPWrapperExceptionServices
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
                    'estActif' => true
                ],
                [
                    'idService' => 10136557,
                    'codeService' => 'FACTURES_PUBLIQUES',
                    'libelleService' => 'Service des factures publiques',
                    'dateDbtService' => '2016-12-28 08:30',
                    'estActif' => true
                ]
            ],
            'parametresRetour' => [
                'pageCourante' => 1,
                'pages' => 1,
                'nbResultatsParPage' => 20,
                'total' => 2,
            ]
        ];

        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)->disableOriginalConstructor()->getMock();
        $curlWrapper->method('get')->willReturn(json_encode($returnData, JSON_THROW_ON_ERROR));
        $curlWrapper->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $cppWrapperConfig = $this->getDefaultWrapperConfig();
        $cppWrapperConfig->identifiant_structure_cpp = 25783752;
        $this->cppWrapper = $this->getCPPWrapper($cppWrapperConfig);

        $this->assertEquals($returnData, $this->cppWrapper->getListeService());
    }

    /**
     * @throws CPPException
     * @throws JsonException
     * @throws CPPWrapperExceptionServices
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

        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)->disableOriginalConstructor()->getMock();
        $curlWrapper->method('get')->willReturn(json_encode($returnData, JSON_THROW_ON_ERROR));
        $curlWrapper->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $cppWrapperConfig = $this->getDefaultWrapperConfig();
        $cppWrapperConfig->identifiant_structure_cpp = 25783752;
        $this->cppWrapper = $this->getCPPWrapper($cppWrapperConfig);

        $this->assertEquals($returnData, $this->cppWrapper->getService(10136557));
    }

    /**
     * @throws Exception
     */
    public function testGetIdentifiantStructureCPPWhenFalse()
    {

        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)->disableOriginalConstructor()->getMock();
        $curlWrapper->expects($this->any())->method('get')->willReturn(false);
        $curlWrapper->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->expects($this->any())->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);
        $this->cppWrapper = $this->getCPPWrapper();

        $this->assertFalse($this->cppWrapper->GetIdentifiantStructureCPPByIdentifiantStructure(""));
    }

    /**
     * @throws Exception
     */
    public function testGetToken(): void
    {
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->delete(self::MEMORY_KEY);
        $returnData = [
            'access_token' => '5TqQc6hAsUsmxD5UpSxmV0kXTgUJY7vNX6HWUodz3lfiwmWvERTjVp',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'openid',
        ];
        $curlWrapperToken = $this->getMockBuilder(CurlWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperToken->expects($this->any())->method('get')->willReturn(json_encode($returnData));
        $curlWrapperToken->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactoryToken = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactoryToken->expects($this->any())->method('getInstance')->willReturn($curlWrapperToken);
        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactoryToken);

        $this->cppWrapper = $this->getCPPWrapper();
        $this->assertEquals(1, $this->cppWrapper->testConnexion());

        $token = $this->getObjectInstancier()->getInstance(MemoryCache::class)->fetch(self::MEMORY_KEY);
        $this->assertEquals("Bearer 5TqQc6hAsUsmxD5UpSxmV0kXTgUJY7vNX6HWUodz3lfiwmWvERTjVp", $token);
    }

    public function testGetTokenInvalid()
    {
        $this->expectException(CPPWrapperExceptionGetToken::class);
        $this->expectExceptionMessage(
            'PISTE get token invalid return: {"access_token":"","token_type":"Bearer","expires_in":3600,"scope":"openid"}'
        );

        $this->getObjectInstancier()->getInstance(MemoryCache::class)->delete(self::MEMORY_KEY);
        $returnData = [
            'access_token' => '',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'openid',
        ];
        $curlWrapperToken = $this->getMockBuilder(CurlWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperToken->expects($this->any())->method('get')->willReturn(json_encode($returnData));
        $curlWrapperToken->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactoryToken = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactoryToken->expects($this->any())->method('getInstance')->willReturn($curlWrapperToken);
        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactoryToken);

        $this->cppWrapper = $this->getCPPWrapper();
        $this->cppWrapper->testConnexion();

        $token = $this->getObjectInstancier()->getInstance(MemoryCache::class)->fetch(self::MEMORY_KEY);
        $this->assertEquals(self::TOKEN, $token);
    }

    /**
     * @return array
     */
    public function getRechercheFactureTravauxProvider(): array
    {
        return [
            'FactureNotEmpty' =>
                [
                    "MOA",
                    ["listeFactures" => [['idFactureTravaux' => 1234]]],
                ],
            'FactureEmpty_NoRole' =>
                [
                    "",
                    ["listeFactures" => []],
                ],
        ];
    }

    /**
     * @param $user_role
     * @param $result_expected
     * @throws CPPException
     * @throws Exception
     * @dataProvider getRechercheFactureTravauxProvider
     */
    public function testRechercheFactureTravaux($user_role, $result_expected)
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
                    'idFactureTravaux' => 1234
                ]
            ]
        ];

        $curlWrapper = $this->getMockBuilder(CurlWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapper->expects($this->any())->method('get')->willReturn(json_encode($returnData));
        $curlWrapper->expects($this->any())->method('getLastHttpCode')->willReturn(200);

        $curlWrapperFactory = $this->getMockBuilder(CurlWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $curlWrapperFactory->expects($this->any())->method('getInstance')->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);

        $cppWrapperConfig = $this->getDefaultWrapperConfig();
        $cppWrapperConfig->user_role = $user_role;
        $this->cppWrapper = $this->getCPPWrapper($cppWrapperConfig);

        $this->assertEquals($result_expected, $this->cppWrapper->rechercheFactureTravaux());
    }
}
