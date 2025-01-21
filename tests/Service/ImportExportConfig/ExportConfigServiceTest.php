<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\ImportExportConfig;

use EntiteSQL;
use FakeTdT;
use TransformationGenerique;
use FluxEntiteHeritageSQL;
use Pastell\Service\Entite\EntityCreationService;
use Pastell\Service\ImportExportConfig\ExportConfigService;
use Pastell\Service\ImportExportConfig\ImportConfigService;
use PastellTestCase;

class ExportConfigServiceTest extends PastellTestCase
{
    /**
     * @throws \Exception
     */
    public function testAll(): void
    {
        $fluxEntiteSQl = $this->getObjectInstancier()->getInstance(\FluxEntiteSQL::class);
        $fluxEntiteSQl->deleteConnecteur(1, 'fournisseur-invitation', 'mail-fournisseur-invitation');
        $id_ce = $this->createConnector('test', 'foo', 2)['id_ce'];
        $this->configureConnector($id_ce, [
            'champs1' => 'bar',
        ], 2);
        $connectorConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce);
        $connectorConfig->addFileFromData('champs6', 'foo.txt', 'barbaz');

        $this->associateFluxWithConnector($id_ce, 'test', 'test', 2);
        $this->associateFluxWithConnector(2, 'actes-generique', 'TdT', 2);

        $entityCreationService = $this->getObjectInstancier()->getInstance(EntityCreationService::class);
        $id_e_herite = $entityCreationService->create(
            'Entite qui hérite',
            '',
            EntiteSQL::TYPE_COLLECTIVITE,
            self::ID_E_COL
        );

        /** @var FluxEntiteHeritageSQL $fluxEntiteHeritageSQL */
        $fluxEntiteHeritageSQL = $this->getObjectInstancier()->getInstance(FluxEntiteHeritageSQL::class);
        $fluxEntiteHeritageSQL->setInheritanceAllFlux($id_e_herite);

        $id_e_herite_actes = $entityCreationService->create(
            'Entite qui hérite que de actes',
            '',
            EntiteSQL::TYPE_COLLECTIVITE,
            self::ID_E_COL
        );
        /** @var FluxEntiteHeritageSQL $fluxEntiteHeritageSQL */
        $fluxEntiteHeritageSQL = $this->getObjectInstancier()->getInstance(FluxEntiteHeritageSQL::class);
        $fluxEntiteHeritageSQL->setInheritance($id_e_herite_actes, 'actes_generique');

        /** @var ExportConfigService $exportConfigService */
        $exportConfigService = $this->getObjectInstancier()->getInstance(ExportConfigService::class);
        $exportedInfo = $exportConfigService->getInfo(1, [
            ExportConfigService::INCLUDE_CONNECTOR => true,
            ExportConfigService::INCLUDE_ENTITY => true,
            ExportConfigService::INCLUDE_CHILD => true,
            ExportConfigService::INCLUDE_ASSOCIATION => true,
            ]);
        $id_e_root = $entityCreationService->create(
            "Entité d'importation",
            '',
        );
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);

        $importConfigService->import($exportedInfo, $id_e_root);
        /** @var EntiteSQL $entiteSQL */
        $entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);

        $fille = $entiteSQL->getFille($id_e_root);
        self::assertEquals('Bourg-en-Bresse', $fille[0]['denomination']);
        $petiteFille = $entiteSQL->getFille($fille[0]['id_e']);
        self::assertEquals('CCAS', $petiteFille[0]['denomination']);

        /** @var \ConnecteurEntiteSQL $connecteurEntiteSQL */
        $connecteurEntiteSQL = $this->getObjectInstancier()->getInstance(\ConnecteurEntiteSQL::class);
        $connectorList = $connecteurEntiteSQL->getAll($petiteFille[0]['id_e']);
        self::assertEquals('foo', $connectorList[0]['libelle']);

        $connectorConfig = $this->getConnecteurFactory()->getConnecteurConfig($connectorList[0]['id_ce']);
        self::assertEquals('bar', $connectorConfig->get('champs1'));

        self::assertEquals('barbaz', $connectorConfig->getFileContent('champs6'));
        self::assertEquals('foo.txt', $connectorConfig->getFileName('champs6'));

        $connectorConfig = $this->getConnecteurFactory()
            ->getConnecteurConfigByType($petiteFille[0]['id_e'], 'test', 'test');
        self::assertEquals('bar', $connectorConfig->get('champs1'));

        $connector = $this->getConnecteurFactory()
            ->getConnecteurByType($petiteFille[0]['id_e'], 'actes-generique', 'Tdt');
        self::assertInstanceOf(FakeTdT::class, $connector);
        self::assertTrue($fluxEntiteHeritageSQL->hasInheritanceAllFlux($petiteFille[1]['id_e']));
        self::assertTrue($fluxEntiteHeritageSQL->hasInheritance($petiteFille[2]['id_e'], 'actes_generique'));
    }

    public function testWhenIdEntityNotFound(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::ENTITY_CHILD => [
                    [
                    'entite_mere' => 42,
                    'siren' => '',
                    'denomination' => 'Foo',
                    'type' => 'collectivite',
                    'id_e' => 12,
                    ]
                ]
            ],
            0
        );
        /** @var EntiteSQL $entiteSQL */
        $entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);
        $info = $entiteSQL->getInfoByDenomination('Foo');
        $this->assertEquals('Foo', $info['denomination']);
        $this->assertEquals(0, $info['entite_mere']);
        $this->assertEquals(
            [0 => "L'entité mère de Foo est inconnue, l'entité sera attachée à l'entité 0."],
            $importConfigService->getLastErrors()
        );
    }

    /**
     * @throws \DonneesFormulaireException
     * @throws \JsonException
     */
    public function testWhenIdEntityNotFoundOnConnector(): void
    {
        $id_e = 12;
        /** @var \ConnecteurEntiteSQL $connecteurEntiteSQL */
        $connecteurEntiteSQL = $this->getObjectInstancier()->getInstance(\ConnecteurEntiteSQL::class);
        $numberOfConnectors = count($connecteurEntiteSQL->getAllLocalByIde($id_e));
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::CONNECTOR_INFO => [
                    [
                        'id_e' => $id_e,
                        'libelle' => 'Bar',
                        'id_connecteur' => 'test',
                        'type' => 'test',
                        'id_ce' => 42,
                        'data' => json_encode(['metadata' => ['champs1' => 'Foo']], JSON_THROW_ON_ERROR),
                    ]
                ]
            ],
            0
        );
        $this->assertCount($numberOfConnectors, $connecteurEntiteSQL->getAllLocalByIde($id_e));
        $this->assertEquals(
            [0 => "Le connecteur Bar est attaché à une entité inconnue : il n'a pas été importé."],
            $importConfigService->getLastErrors()
        );
    }

    public function testWhenEntiteIdNotFoundOnAssociation(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::ASSOCIATION_INFO => [
                    12 => [
                        'actes-generique' => [
                            'Bordereau SEDA' => [
                                0 => [
                                    'num_same_type' => 0,
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            0
        );
        $this->assertEquals(
            [0 => "L'entité du fichier d'import id_e=12 n'est pas présente : ces associations n'ont pas été importées."],
            $importConfigService->getLastErrors()
        );
    }

    public function testWhenConnectorIdNotFoundOnAssociation(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::ENTITY_INFO => [
                    'denomination' => 'Foo',
                    'id_e' => 12,
                    'siren' => '000000000',
                    'entite_mere' => 0,
                    'type' => 'collectivite',
                ],
                ExportConfigService::ASSOCIATION_INFO => [
                    12 => [
                        'actes-generique' => [
                            'Bordereau SEDA' => [
                                0 => [
                                    'id_ce' => 42,
                                    'num_same_type' => 0,
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            0
        );
        $this->assertEquals(
            [0 => "La définition du connecteur id_ce=42 n'est pas présente : l'association n'a pas été importée."],
            $importConfigService->getLastErrors()
        );
    }

    public function testWhenEntityIdNotFoundOnConnectorInheritance(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::ASSOCIATION_HERITAGE_INFO => [
                    12 => ['actes-generique'],
                ],
            ],
            0
        );
        $this->assertEquals(
            [0 => "L'entité du fichier d'import id_e=12 n'est pas présente : les héritages d'associations n'ont pas été importées."],
            $importConfigService->getLastErrors()
        );
    }

    public function testWhenImportOnlyEntiteFille(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::ENTITY_INFO => [
                    'denomination' => 'Carcassonne',
                    'id_e' => 10,
                    'siren' => '000000000',
                    'entite_mere' => 0,
                    'type' => 'collectivite',
                    'is_active' => 1,
                ],
                ExportConfigService::ENTITY_CHILD => [
                    0 =>  [
                        'denomination' => 'Arcachon',
                        'id_e' => 12,
                        'siren' => '000000000',
                        'entite_mere' => 11,
                        'type' => 'collectivite',
                        'is_active' => 1,
                    ],
                    1 =>  [
                        'denomination' => 'Biarritz',
                        'id_e' => 11,
                        'siren' => '000000000',
                        'entite_mere' => 10,
                        'type' => 'collectivite',
                        'is_active' => 1,
                    ],
                ],
            ],
            0
        );

        $entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);

        self::assertSame(
            'Carcassonne (id_e 3) est fille de Entité racine (id_e 0)',
            sprintf(
                'Carcassonne (id_e %d) est fille de %s (id_e %d)',
                $entiteSQL->getIdByDenomination('Carcassonne'),
                $entiteSQL->getDenomination($entiteSQL->getEntiteMere($entiteSQL->getIdByDenomination('Carcassonne'))),
                $entiteSQL->getEntiteMere($entiteSQL->getIdByDenomination('Carcassonne'))
            )
        );
        self::assertSame(
            'Biarritz (id_e 4) est fille de Carcassonne (id_e 3)',
            sprintf(
                'Biarritz (id_e %d) est fille de %s (id_e %d)',
                $entiteSQL->getIdByDenomination('Biarritz'),
                $entiteSQL->getDenomination($entiteSQL->getEntiteMere($entiteSQL->getIdByDenomination('Biarritz'))),
                $entiteSQL->getEntiteMere($entiteSQL->getIdByDenomination('Biarritz'))
            )
        );
        self::assertSame(
            'Arcachon (id_e 5) est fille de Biarritz (id_e 4)',
            sprintf(
                'Arcachon (id_e %d) est fille de %s (id_e %d)',
                $entiteSQL->getIdByDenomination('Arcachon'),
                $entiteSQL->getDenomination($entiteSQL->getEntiteMere($entiteSQL->getIdByDenomination('Arcachon'))),
                $entiteSQL->getEntiteMere($entiteSQL->getIdByDenomination('Arcachon'))
            )
        );
    }

    /**
     * @throws \DonneesFormulaireException
     * @throws \JsonException
     */
    public function testWhenImportingGlobalConnectorOnRoot(): void
    {
        /** @var \ConnecteurEntiteSQL $connecteurEntiteSQL */
        $connecteurEntiteSQL = $this->getObjectInstancier()->getInstance(\ConnecteurEntiteSQL::class);
        $numberOfConnectors = count($connecteurEntiteSQL->getAllGlobalByIde(0));
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::CONNECTOR_INFO => [
                    [
                        'id_e' => 0,
                        'libelle' => 'Bar',
                        'id_connecteur' => 'test',
                        'type' => 'test',
                        'id_ce' => 42,
                        'data' => json_encode(['metadata' => ['champs1' => 'Foo']], JSON_THROW_ON_ERROR),
                    ]
                ]
            ],
            0
        );
        static::assertCount($numberOfConnectors + 1, $connecteurEntiteSQL->getAllGlobalByIde(0));
    }

    /**
     * @throws \DonneesFormulaireException
     * @throws \JsonException
     */
    public function testWhenImportingGlobalConnectorOnChild(): void
    {
        $id_e_root = 1;
        /** @var \ConnecteurEntiteSQL $connecteurEntiteSQL */
        $connecteurEntiteSQL = $this->getObjectInstancier()->getInstance(\ConnecteurEntiteSQL::class);
        $numberOfConnectors = count($connecteurEntiteSQL->getAllLocalByIde($id_e_root));
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::CONNECTOR_INFO => [
                    [
                        'id_e' => 0,
                        'libelle' => 'Bar',
                        'id_connecteur' => 'test',
                        'type' => 'test',
                        'id_ce' => 42,
                        'data' => json_encode(['metadata' => ['champs1' => 'Foo']], JSON_THROW_ON_ERROR),
                    ]
                ]
            ],
            $id_e_root
        );
        static::assertCount($numberOfConnectors, $connecteurEntiteSQL->getAllLocalByIde($id_e_root));
        static::assertSame(
            [0 => "Le connecteur global Bar ne peut pas être importé sur une entité fille : il n'a pas été importé."],
            $importConfigService->getLastErrors()
        );
    }

    /**
     * @throws \DonneesFormulaireException
     */
    public function testWhenImportOnlyEntiteConnector(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::CONNECTOR_INFO => [
                    [
                        'id_e' => 12,
                        'libelle' => 'Bar',
                        'id_connecteur' => 'test',
                        'type' => 'test',
                        'id_ce' => 42,
                        'data' => json_encode(['metadata' => ['champs1' => 'Foo']]),
                    ]
                ],
            ],
            1
        );
        $connectorConfig = $this->getConnecteurFactory()
            ->getConnecteurConfig(14);
        self::assertEquals('Foo', $connectorConfig->get('champs1'));
    }

    /**
     * @throws \DonneesFormulaireException
     */
    public function testWhenImportingGlobalConnectorAndAssociations(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::CONNECTOR_INFO => [
                    [
                        'id_ce' => 2,
                        'id_e' => 0,
                        'libelle' => 'bar',
                        'id_connecteur' => 'cloudooo',
                        'type' => 'convertisseur-office-pdf',
                        'data' => '{"metadata":{"cloudooo_hostname":"cloudooo","cloudooo_port":"8011"}}',
                    ],
                ],
                ExportConfigService::ASSOCIATION_INFO => [
                    0 => [
                        'global' => [
                            'convertisseur-office-pdf' => [
                                [
                                    'id_fe' => 2,
                                    'id_e' => 0,
                                    'flux' => 'global',
                                    'id_ce' => 2,
                                    'type' => 'convertisseur-office-pdf',
                                    'num_same_type' => 0,
                                    'libelle' => 'Conversion Office PDF',
                                    'id_connecteur' => 'cloudooo',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            0
        );

        static::assertSame(
            [],
            $importConfigService->getLastErrors()
        );

        $globalConnector = $this->getObjectInstancier()
            ->getInstance(\ConnecteurFactory::class)
            ->getGlobalConnecteur('convertisseur-office-pdf');
        self::assertSame(
            $globalConnector->getConnecteurInfo()['libelle'],
            'bar'
        );
    }

    /**
     * @throws \DonneesFormulaireException
     */
    public function testWhenImportingUnknownAssociation(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                    ExportConfigService::ENTITY_INFO => [
                        'denomination' => 'Foo',
                        'id_e' => 12,
                        'siren' => '000000000',
                        'entite_mere' => 0,
                        'type' => 'collectivite',
                    ],
                    ExportConfigService::CONNECTOR_INFO => [
                        [
                            'id_ce' => 2,
                            'id_e' => 12,
                            'libelle' => 'bar',
                            'id_connecteur' => 'generateur-seda',
                            'type' => 'Bordereau SEDA',
                            'data' => '{"metadata":{"seda_generator_url":"http:\/\/seda-generator"}}',
                        ],
                    ],
                    ExportConfigService::ASSOCIATION_INFO => [
                        12 => [
                            'unknown-module' => [
                                'Bordereau SEDA' => [
                                    0 => [
                                        'id_e' => 12,
                                        'flux' => 'unknown-module',
                                        'id_ce' => 2,
                                        'type' => 'Bordereau SEDA',
                                        'libelle' => '',
                                        'id_connecteur' => 'Generateur SEDA',
                                        'num_same_type' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ],
            ],
            0
        );
        static::assertSame(
            [    0 => "Le type de dossier « unknown-module » n'existe pas."],
            $importConfigService->getLastErrors()
        );
    }

    /**
     * @throws \DonneesFormulaireException
     */
    public function testWhenImportingEntityConnectorAndAssociationsByRacine(): void
    {
        $importConfigService = $this->getObjectInstancier()->getInstance(ImportConfigService::class);
        $importConfigService->import(
            [
                ExportConfigService::ENTITY_INFO => [
                    'denomination' => 'Foo',
                    'id_e' => 12,
                    'siren' => '000000000',
                    'entite_mere' => 0,
                    'type' => 'collectivite',
                ],
                ExportConfigService::CONNECTOR_INFO => [
                    [
                        'id_ce' => 15,
                        'id_e' => 0,
                        'libelle' => 'transformation-entite-racine',
                        'id_connecteur' => 'transformation-generique',
                        'type' => 'transformation',
                        'global' => 0,
                        'data' => '{"metadata":{"definition":["definition.json"]},"file":{"definition":["eyJh"]}}',
                    ],
                ],
                ExportConfigService::ASSOCIATION_INFO => [
                    12 => [
                        'ls-document' => [
                            'transformation' => [
                                0 => [
                                    'id_e' => 0,
                                    'flux' => 'ls-document',
                                    'id_ce' => 15,
                                    'type' => 'transformation',
                                    'libelle' => '',
                                    'id_connecteur' => 'transformation-generique',
                                    'num_same_type' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            1
        );

        static::assertSame(
            [],
            $importConfigService->getLastErrors()
        );

        $entiteSQL = $this->getObjectInstancier()->getInstance(EntiteSQL::class);
        $allEntiteFille = $entiteSQL->getFille(1);
        $newEntite = end($allEntiteFille);
        self::assertSame('Foo', $newEntite['denomination']);

        $connecteurEntiteSQL = $this->getObjectInstancier()->getInstance(\ConnecteurEntiteSQL::class);
        $connectorList = $connecteurEntiteSQL->getAll(1);
        $newConnector = end($connectorList);
        self::assertSame('transformation-entite-racine', $newConnector['libelle']);

        $connector = $this->getConnecteurFactory()
            ->getConnecteurByType($newEntite['id_e'], 'ls-document', 'transformation');
        self::assertInstanceOf(TransformationGenerique::class, $connector);
    }
}
