<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use DonneesFormulaireFactory;
use Exception;
use FluxEntiteSQL;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Pastell\Client\IparapheurV5\ApiClientFactory;
use Pastell\Command\Connector\MigrateIparapheurSoapRest;
use Pastell\Connector\IparapheurRest\IpRestApiException;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\ConnecteurCreationService;
use Pastell\Service\Connecteur\ConnecteurDeletionService;
use PastellTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use UnrecoverableException;

class MigrateIparapheurSoapRestTest extends PastellTestCase
{
    private int $soapConnectorId;

    protected function setUp(): void
    {
        parent::setUp();

        $soapConnector = $this->createConnector(MigrateIparapheurSoapRest::IPARAPHEUR_SOAP, 'Test SOAP iParapheur');
        $this->soapConnectorId = (int)$soapConnector['id_ce'];

        $this->configureConnector($this->soapConnectorId, [
            'iparapheur_wsdl' => 'https://test.example.com/ws-iparapheur?wsdl',
            'iparapheur_login' => 'test_user',
            'iparapheur_password' => 'test_password',
            'iparapheur_type' => 'pades',
            'metadata_field' => 'test_metadata',
            'nb_jour_max' => '30',
            'multi_doc' => '1'
        ]);

        $this->associateFluxWithConnector(
            $this->soapConnectorId,
            'ls-document-pdf',
            'signature',
            self::ID_E_COL,
            0
        );
    }

    public function migrationScenariosProvider(): array
    {
        return [
            'success' => [
                'tenants' => [
                    ['id' => 'tenant-1', 'name' => 'Tenant Test']
                ],
                'desks' => [
                    ['id' => 'desk-1', 'name' => 'Bureau Test', 'tenantId' => 'tenant-1']
                ],
                'types' => [
                    ['id' => 'type-1', 'name' => 'pades'] // Doit matcher exactement 'pades' du SOAP (case sensitive)
                ],
                'exception' => null,
                'expectedSuccess' => true,
                'expectedErrorPattern' => null,
            ],
            'multiple_tenants' => [
                'tenants' => [
                    ['id' => 'tenant-1', 'name' => 'Tenant 1'],
                    ['id' => 'tenant-2', 'name' => 'Tenant 2'],
                ],
                'desks' => null,
                'types' => null,
                'exception' => null,
                'expectedSuccess' => false,
                'expectedErrorPattern' => '/Plusieurs tenants trouvés/',
            ],
            'multiple_desks' => [
                'tenants' => [
                    ['id' => 'tenant-1', 'name' => 'Tenant Test']
                ],
                'desks' => [
                    ['id' => 'desk-1', 'name' => 'Bureau 1', 'tenantId' => 'tenant-1'],
                    ['id' => 'desk-2', 'name' => 'Bureau 2', 'tenantId' => 'tenant-1'],
                    ['id' => 'desk-3', 'name' => 'Bureau 3', 'tenantId' => 'tenant-1'],
                ],
                'types' => null,
                'exception' => null,
                'expectedSuccess' => false,
                'expectedErrorPattern' => '/Plusieurs desks trouvés/',
            ],
            'case_sensitive_type' => [
                'tenants' => [
                    ['id' => 'tenant-1', 'name' => 'Tenant Test']
                ],
                'desks' => [
                    ['id' => 'desk-1', 'name' => 'Bureau Test', 'tenantId' => 'tenant-1']
                ],
                'types' => [
                    ['id' => 'type-1', 'name' => 'PADES'] // PADES (majuscules) ne matche pas "pades" (minuscules) - comparaison sensible à la casse
                ],
                'exception' => null,
                'expectedSuccess' => false,
                'expectedErrorPattern' => '/Type pades non trouvé/',
            ],
            'api_exception' => [
                'tenants' => null,
                'desks' => null,
                'types' => null,
                'exception' => new IpRestApiException('Erreur de connexion API'),
                'expectedSuccess' => false,
                'expectedErrorPattern' => '/Erreur de connexion API/',
            ],
        ];
    }

    /**
     * @dataProvider migrationScenariosProvider
     */
    public function testMigrateConnector(
        ?array $tenants,
        ?array $desks,
        ?array $types,
        ?Exception $exception,
        bool $expectedSuccess,
        ?string $expectedErrorPattern
    ): void {

        $apiClientFactory = $this->getObjectInstancier()->getInstance(ApiClientFactory::class);
        $apiClientFactory->setClientInterface($this->buildMockClient($tenants, $desks, $types, $exception));

        $command = new MigrateIparapheurSoapRest(
            $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class),
            $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class),
            $this->getObjectInstancier()->getInstance(ConnecteurCreationService::class),
            $this->getObjectInstancier()->getInstance(ConnecteurAssociationService::class),
            $this->getObjectInstancier()->getInstance(ConnecteurDeletionService::class),
            $this->getObjectInstancier()->getInstance(ConnecteurFactory::class),
            $apiClientFactory,
            $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class)
        );

        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['yes', 'no']); // yes pour migration, no pour deletion
        $exitCode = $commandTester->execute([
            '--id_ce' => (string)$this->soapConnectorId,
        ]);

        $output = $commandTester->getDisplay();

        if ($expectedSuccess) {
            static::assertSame(Command::SUCCESS, $exitCode);
            static::assertStringContainsString('Résumé: 1 succès, 0 erreur(s)', $output);

            $connecteurEntiteSQL = $this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class);
            $restConnectors = $connecteurEntiteSQL->getAllEntiteConnectorById(MigrateIparapheurSoapRest::IPARAPHEUR_REST);
            static::assertNotEmpty($restConnectors);

            $fluxEntiteSQL = $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class);
            $newRestConnectorId = $restConnectors[0]['id_ce'];
            $associations = $fluxEntiteSQL->getUsedByConnecteur($newRestConnectorId);
            static::assertCount(1, $associations);
        } else {
            static::assertStringContainsString('Résumé: 0 succès, 1 erreur(s)', $output);
            if ($expectedErrorPattern !== null) {
                static::assertMatchesRegularExpression($expectedErrorPattern, $output);
            }
        }
    }

    private function buildMockClient(?array $tenants, ?array $desks, ?array $types, ?Exception $exception): ClientInterface
    {
        $clientInterface = $this->getMockBuilder(ClientInterface::class)->getMock();

        if ($exception !== null) {
            $clientInterface->method('sendRequest')
                ->willThrowException($exception);
            return $clientInterface;
        }

        $clientInterface->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request) use ($tenants, $desks, $types): ResponseInterface {
                $path = $request->getUri()->getPath();

                if (str_contains($path, '/token')) {
                    return new HttpResponse(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/../../Connector/IparapheurRest/fixtures/authenticate_ok.json')
                    );
                }

                if (str_ends_with($path, '/tenant')) {
                    if ($tenants === null) {
                        throw new IpRestApiException('Erreur de connexion API');
                    }

                    $content = [];
                    foreach ($tenants as $tenant) {
                        $content[] = [
                            'id' => $tenant['id'],
                            'name' => $tenant['name']
                        ];
                    }

                    return new HttpResponse(
                        200,
                        ['Content-type' => 'application/json'],
                        json_encode([
                            'content' => $content,
                            'pageable' => [
                                'sort' => ['empty' => false, 'unsorted' => false, 'sorted' => true],
                                'offset' => 0,
                                'pageSize' => 10,
                                'pageNumber' => 0,
                                'unpaged' => false,
                                'paged' => true
                            ],
                            'last' => true,
                            'totalElements' => count($content),
                            'totalPages' => 1,
                            'size' => 10,
                            'number' => 0,
                            'sort' => ['empty' => false, 'unsorted' => false, 'sorted' => true],
                            'numberOfElements' => count($content),
                            'first' => true,
                            'empty' => empty($content)
                        ], JSON_THROW_ON_ERROR)
                    );
                }

                if (str_contains($path, '/desk') && !str_contains($path, '/types')) {
                    $deskContent = [];
                    foreach ($desks ?? [] as $desk) {
                        $deskContent[] = [
                            'id' => $desk['id'],
                            'name' => $desk['name'],
                            'tenantId' => $desk['tenantId']
                        ];
                    }

                    return new HttpResponse(
                        200,
                        ['Content-type' => 'application/json'],
                        json_encode([
                            'totalPages' => 1,
                            'totalElements' => count($deskContent),
                            'pageable' => [
                                'unpaged' => false,
                                'pageNumber' => 0,
                                'pageSize' => 10,
                                'paged' => true,
                                'offset' => 0,
                                'sort' => [
                                    'unsorted' => false,
                                    'sorted' => true,
                                    'empty' => false
                                ]
                            ],
                            'numberOfElements' => count($deskContent),
                            'size' => 10,
                            'content' => $deskContent,
                            'number' => 0,
                            'sort' => [
                                'unsorted' => false,
                                'sorted' => true,
                                'empty' => false
                            ],
                            'first' => true,
                            'last' => true,
                            'empty' => empty($deskContent)
                        ], JSON_THROW_ON_ERROR)
                    );
                }

                if (str_contains($path, '/types/creation-allowed')) {
                    $typeContent = [];
                    foreach ($types ?? [] as $type) {
                        $typeContent[] = [
                            'id' => $type['id'],
                            'name' => $type['name'],
                            'description' => $type['name']
                        ];
                    }

                    return new HttpResponse(
                        200,
                        ['Content-type' => 'application/json'],
                        json_encode([
                            'totalPages' => 1,
                            'totalElements' => count($typeContent),
                            'pageable' => [
                                'unpaged' => false,
                                'pageNumber' => 0,
                                'pageSize' => 10,
                                'paged' => true,
                                'offset' => 0,
                                'sort' => [
                                    'unsorted' => false,
                                    'sorted' => true,
                                    'empty' => false
                                ]
                            ],
                            'numberOfElements' => count($typeContent),
                            'size' => 10,
                            'content' => $typeContent,
                            'number' => 0,
                            'sort' => [
                                'unsorted' => false,
                                'sorted' => true,
                                'empty' => false
                            ],
                            'first' => true,
                            'last' => true,
                            'empty' => empty($typeContent)
                        ], JSON_THROW_ON_ERROR)
                    );
                }

                throw new UnrecoverableException('Unknown path: ' . $request->getMethod() . ' ' . $path);
            });

        return $clientInterface;
    }
}
