<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector;

use CurlUtilitiesTestTrait;
use CurlWrapperFactory;
use DonneesFormulaireException;
use FluxDataTestSedaGenerique;
use NotFoundException;
use Pastell\Connector\AbstractSedaGeneratorConnector;
use Pastell\Seda\Message\SedaMessageBuilder;
use Pastell\Service\Document\DocumentPastellMetadataService;
use Pastell\Step\SAE\Enum\SAEActionsEnum;
use PastellTestCase;
use TmpFolder;
use TypeDossierLoader;
use UnrecoverableException;

abstract class AbstractSedaGeneratorConnectorTestCase extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    private TmpFolder $tmpFolder;
    private string $tmp_folder = '';

    abstract public function getSedaMessageBuilder(): SedaMessageBuilder;

    abstract public function getSedaConnectorId(): string;

    abstract public function getExpectedCallDirectory(): string;

    /**
     * @throws \Exception
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->tmpFolder = new TmpFolder();
        $this->tmp_folder = $this->tmpFolder->create();
        \mkdir($this->tmp_folder . '/workspace');
    }

    public function __destruct()
    {
        $this->tmpFolder->delete($this->tmp_folder);
    }

    public function getEmulatedDisk(): string
    {
        return $this->tmp_folder;
    }

    public function getTmpFolder(): TmpFolder
    {
        return $this->tmpFolder;
    }
    public function getPastellMetadataService(): DocumentPastellMetadataService
    {
        return $this->getObjectInstancier()->getInstance(DocumentPastellMetadataService::class);
    }

    private function setCurl(callable $returnCallback): void
    {
        $this->mockCurl([
            "http://seda-generator:8080/generate" => "OK",
        ]);

        $curlWrapper = $this->getObjectInstancier()->getInstance(CurlWrapperFactory::class)->getInstance();
        $curlWrapper
            ->method('setJsonPostData')
            ->willReturnCallback($returnCallback);
    }

    protected function createSedaGeneriqueConnector(): int
    {
        $sedaMessageBuilder = $this->getSedaMessageBuilder();
        $sedaMessageBuilder->setIdGeneratorFunction(function () {
            static $i = 0;
            $i++;
            return "NOT_TESTABLE_$i";
        });
        $this->getObjectInstancier()->setInstance($this->getSedaMessageBuilder()::class, $sedaMessageBuilder);
        $id_ce = $this->createConnector($this->getSedaConnectorId(), 'SEDA generique')['id_ce'];
        $this->configureConnector($id_ce, [
            'seda_generator_url' => 'http://seda-generator:8080/',
        ]);
        return (int)$id_ce;
    }

    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws \Exception
     */
    private function createDossier(): string
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData([
            'numero_de_lacte' => '12',
            'date_de_lacte' => '2020-12-04',
            'acte_nature' => 'Délibération',
            'classification' => '1.1 Titulaire de la fonction publique territoriale',
            'objet' => 'Nomination du colonel Moutarde',
        ]);
        $donneesFormulaire->addFileFromData('arrete', 'document.pdf', 'foo bar');
        $donneesFormulaire->addFileFromData(
            'autre_document_attache',
            'document.pdf',
            'foo bar baz',
            0
        );
        $donneesFormulaire->addFileFromData(
            'autre_document_attache',
            'annexe2.pdf',
            'buz',
            1
        );

        $donneesFormulaire->addFileFromData(
            'bordereau',
            'borderau.pdf',
            'bordcontent'
        );
        $donneesFormulaire->addFileFromCopy(
            'aractes',
            'aractes.xml',
            __DIR__ . '/fixtures/seda-generator/202010281531-ar-actes.xml'
        );

        $donneesFormulaire->addFileFromCopy(
            'file_zip',
            '7756W3_9.zip',
            __DIR__ . '/fixtures/seda-generator/7756W3_9.zip'
        );

        $donneesFormulaire->addFileFromCopy(
            'file_xml',
            'PESALLER.xml',
            __DIR__ . '/fixtures/seda-generator/HELIOS_SIMU_ALR2_1595923133_1646706116.xml'
        );
        return $id_d;
    }

    public function caseProvider(): iterable
    {
        $all_dir = \scandir(__DIR__ . '/fixtures/seda-test-cases');
        $all_dir = \array_diff($all_dir, ['.', '..']);
        foreach ($all_dir as $dir) {
            yield $dir => [__DIR__ . '/fixtures/seda-test-cases/' . $dir, $dir];
        }
    }

    /**
     * @throws DonneesFormulaireException
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws \Exception
     * @dataProvider caseProvider
     */
    public function testAllCase(string $folder, string $testName): void
    {
        $this->setCurl(function (array $json_data) use ($testName) {
            $json_content = \json_encode($json_data, \JSON_THROW_ON_ERROR);
//            \mkdir($this->getExpectedCallDirectory() . '/' . $testName);
//            \file_put_contents(
//                $this->getExpectedCallDirectory() . '/' . $testName . '/expected_call.json',
//                \json_encode($json_data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
//            );
            $this->assertJsonStringEqualsJsonFile(
                $this->getExpectedCallDirectory() . '/' . $testName . '/expected_call.json',
                $json_content
            );
            return true;
        });

        $id_ce = $this->createSedaGeneriqueConnector();

        if (\str_contains($testName, 'with-sha512')) {
            $this->configureConnector($id_ce, [
               AbstractSedaGeneratorConnector::SEDA_GENERATOR_HASH_ALGORITHM_ID => 1,
            ]);
        }

        $connecteurConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce);

        $connecteurConfig->addFileFromCopy('files', 'file.xml', $folder . '/files.xml');
        $connecteurConfig->addFileFromCopy('data', 'data.json', $folder . '/data.json');

        /** @var AbstractSedaGeneratorConnector $sedaGeneriqueConnector */
        $sedaGeneriqueConnector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $id_d = $this->createDossier();
        $docDonneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $sedaGeneriqueConnector->setDocDonneesFormulaire($docDonneesFormulaire);

        $bordereau = $sedaGeneriqueConnector->getBordereau(new FluxDataTestSedaGenerique());
        $this->assertStringContainsString('OK', $bordereau);
    }

    /**
     * @throws DonneesFormulaireException
     * @throws \JsonException
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws \Exception
     */
    public function testWhenAKeywordIsAssociatedWithAFile(): void
    {
        $this->setCurl(function (array $json_data) {
            static::assertJsonStringEqualsJsonString(
                '{"Keywords":[],"ArchiveUnits":[],"Files":[]}',
                \json_encode($json_data, \JSON_THROW_ON_ERROR)
            );
            return true;
        });
        $id_ce = $this->createSedaGeneriqueConnector();
        $connecteurConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce);
        $connecteurConfig->addFileFromData(
            'data',
            'data.json',
            \json_encode(
                [
                    'commentaire' => '{{ arrete }}',
                ],
                \JSON_THROW_ON_ERROR
            )
        );
        $connecteurConfig->addFileFromCopy(
            'files',
            'file.xml',
            __DIR__ . '/fixtures/seda-test-cases/empty/files.xml'
        );

        /** @var AbstractSedaGeneratorConnector $sedaGeneriqueConnector */
        $sedaGeneriqueConnector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $id_d = $this->createDossier();
        $docDonneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $sedaGeneriqueConnector->setDocDonneesFormulaire($docDonneesFormulaire);

        $this->expectExceptionMessage(
            'Erreur sur le template {{ arrete }} : Array to string conversion'
        );
        $this->expectException(UnrecoverableException::class);
        $sedaGeneriqueConnector->getBordereau(new FluxDataTestSedaGenerique());
    }

    /**
     * @throws DonneesFormulaireException
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws \Exception
     */
    public function testWhenGeneratorReturnANon200HttpCode(): void
    {
        $this->mockCurl(['http://seda-generator:8080/generate' => 'KO'], 503);
        $id_ce = $this->createSedaGeneriqueConnector();
        /** @var AbstractSedaGeneratorConnector $sedaGeneriqueConnector */
        $sedaGeneriqueConnector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $id_d = $this->createDossier();
        $docDonneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $sedaGeneriqueConnector->setDocDonneesFormulaire($docDonneesFormulaire);

        $connecteurConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce);
        $connecteurConfig->addFileFromCopy('files', 'file.xml', __DIR__ . '/fixtures/seda-test-cases/empty/files.xml');
        $connecteurConfig->addFileFromCopy('data', 'data.json', __DIR__ . '/fixtures/seda-test-cases/empty/data.json');

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('SedaGenerator did not return a 200 response. Code HTTP: 503.');
        $sedaGeneriqueConnector->getBordereau(new FluxDataTestSedaGenerique());
    }

    /**
     * @throws UnrecoverableException
     * @throws \Exception
     */
    public function testWhenConnectionIsOk(): void
    {
        $this->mockCurl(['http://seda-generator:8080/version' => '{"version":"0.3.1"}']);
        $id_ce = $this->createSedaGeneriqueConnector();
        /** @var AbstractSedaGeneratorConnector $sedaGeneriqueConnector */
        $sedaGeneriqueConnector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $this->assertSame('{"version":"0.3.1"}', $sedaGeneriqueConnector->testConnexion());
    }

    /**
     * @throws UnrecoverableException
     * @throws \Exception
     */
    public function testWhenConnectionIsNotOk(): void
    {
        $this->mockCurl(['http://seda-generator:8080/version' => 'KO'], 404);
        $id_ce = $this->createSedaGeneriqueConnector();
        /** @var AbstractSedaGeneratorConnector $sedaGeneriqueConnector */
        $sedaGeneriqueConnector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('SedaGenerator did not return a 200 response. Code HTTP: 404.');
        $sedaGeneriqueConnector->testConnexion();
    }

    /**
     * @throws UnrecoverableException
     * @throws \Exception
     */
    public function testWhithURLinGlobalConnector(): void
    {
        $this->mockCurl(['http://seda-generator-in-global:8080/version' => '{"version":"0.6.1"}']);

        $id_ce = $this->createConnector('generateur-seda', 'SEDA generique', 0)['id_ce'];
        $this->configureConnector(
            $id_ce,
            [
                'seda_generator_url' => 'http://seda-generator-in-global:8080/',
            ],
            0
        );
        $this->associateFluxWithConnector($id_ce, 'test', 'Generateur SEDA', 0);

        $id_ce = $this->createSedaGeneriqueConnector();
        $this->configureConnector($id_ce, ['seda_generator_url' => '']);

        /** @var AbstractSedaGeneratorConnector $sedaGeneriqueConnector */
        $sedaGeneriqueConnector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $this->assertSame('{"version":"0.6.1"}', $sedaGeneriqueConnector->testConnexion());
    }

    /**
     * @throws UnrecoverableException
     * @throws \Exception
     */
    public function testWithoutURL(): void
    {
        $id_ce = $this->createSedaGeneriqueConnector();
        $this->configureConnector($id_ce, ['seda_generator_url' => '']);

        /** @var AbstractSedaGeneratorConnector $sedaGeneriqueConnector */
        $sedaGeneriqueConnector = $this->getConnecteurFactory()->getConnecteurById($id_ce);

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "L'URL du générateur n'a pas été trouvé. Avez-vous pensé à créer un connecteur global Generateur SEDA et à l'associer ?"
        );
        $sedaGeneriqueConnector->testConnexion();
    }

    /**
     * @throws \TypeDossierException
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws \JsonException
     */
    public function testGenerateArchiveWithTemplateAndAdvancedData(): void
    {
        $fixtureFolder = __DIR__ . '/fixtures/seda-with-template-and-advanced-data/';
        $exceptedFolder = $this->getExpectedCallDirectory() . '/seda-with-template-and-advanced-data/';

        $this->mockCurl([
            'http://seda-generator:8080/generateWithTemplate' => file_get_contents(
                $fixtureFolder . 'bordereau.xml'
            ),
        ]);

        $typeDossierSAETemplate = 'studio-sae-for-template-gps';
        $typeDossierLoader = $this->getObjectInstancier()->getInstance(TypeDossierLoader::class);
        $typeDossierLoader->createTypeDossierDefinitionFile($typeDossierSAETemplate);

        $document = $this->createDocument($typeDossierSAETemplate);
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $donneesFormulaire->setTabData([
            'titre' => 'test sae whith template gps',
            'gps_altitude' => '598',
            'gps_altitude_ref' => '0',
            'gps_latitude' => '45 18 46.922',
            'gps_latitude_ref' => 'N',
            'gps_longitude' => '5 23 32.229',
            'gps_longitude_ref' => 'E'
        ]);
        $donneesFormulaire->addFileFromCopy(
            'fichier',
            'vide.pdf',
            $fixtureFolder . 'vide.pdf'
        );

        $saeConnector = $this->createConnector('fakeSAE', 'SAE');
        $this->associateFluxWithConnector($saeConnector['id_ce'], $typeDossierSAETemplate, 'SAE');

        $id_ce = $this->createSedaGeneriqueConnector();
        $connecteurConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce);
        $connecteurConfig->addFileFromCopy(
            'files',
            'file.xml',
            $fixtureFolder . 'connecteur-files.xml'
        );
        $connecteurConfig->addFileFromCopy(
            'data',
            'data.json',
            $fixtureFolder . 'connecteur-data.json'
        );
        $connecteurConfig->addFileFromCopy(
            'template',
            'template_gps.xml.twig',
            $fixtureFolder . 'seda_2.2-asalae_template_gps.xml.twig'
        );
        $connecteurConfig->addFileFromCopy(
            'advanced_data',
            'advanced_data_gps.json',
            $fixtureFolder . 'advanced_data_gps.json'
        );
        $this->associateFluxWithConnector($id_ce, $typeDossierSAETemplate, 'Bordereau SEDA');

        /** @var AbstractSedaGeneratorConnector $connector */
        $connector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $connector->setDocDonneesFormulaire($donneesFormulaire);
        $message = $connector->getMessage(
            new \FluxDataSedaDefault($donneesFormulaire),
            json_decode($connecteurConfig->getFileContent('data'), true, 512, JSON_THROW_ON_ERROR),
            $connecteurConfig->getFileContent('files'),
            json_decode($connecteurConfig->getFileContent('advanced_data'), true, 512, JSON_THROW_ON_ERROR),
        );

        $json_content = \json_encode($message, \JSON_THROW_ON_ERROR);
        //\mkdir($exceptedFolder);
        //\file_put_contents(
        //    $exceptedFolder . 'expected_call.json',
        //    \json_encode($message, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        //);
        static::assertJsonStringEqualsJsonFile(
            $exceptedFolder . 'expected_call.json',
            $json_content
        );

        $typeDossierLoader->unload();
    }
}
