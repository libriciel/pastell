<?php

declare(strict_types=1);

namespace Pastell\Connector;

use ConnecteurFactory;
use CurlWrapperFactory;
use DonneesFormulaire;
use DonneesFormulaireException;
use FluxData;
use Pastell\Seda\Message\SedaMessageBuilder;
use Pastell\Seda\SedaVersion;
use SEDAConnecteur;
use SimpleXMLWrapperException;
use TmpFolder;
use UnrecoverableException;

abstract class AbstractSedaGeneratorConnector extends SEDAConnecteur
{
    private const SEDA_GENERATOR_VERSION_PATH = '/version';
    private const SEDA_GENERATOR_GENERATE_PATH = '/generate';
    private const SEDA_GENERATOR_GENERATE_PATH_WITH_TEMPLATE = '/generateWithTemplate';
    private const SEDA_GENERATOR_URL_ID = 'seda_generator_url';
    public const SEDA_GENERATOR_HASH_ALGORITHM_ID = 'hash_algorithm';
    private const SEDA_GENERATOR_GLOBAL_TYPE = 'Generateur SEDA';

    private DonneesFormulaire $connecteurConfig;

    private ?string $bordereau = null;
    abstract public function getVersion(): SedaVersion;

    public function __construct(
        private readonly CurlWrapperFactory $curlWrapperFactory,
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly TmpFolder $tmpFolder,
        private readonly SedaMessageBuilder $sedaMessageBuilder,
    ) {
    }

    public function setConnecteurConfig(DonneesFormulaire $connecteurConfig): void
    {
        $this->connecteurConfig = $connecteurConfig;
    }

    /**
     * @throws UnrecoverableException
     */
    public function getHashAlgorithm(): string
    {
        return match ((int)$this->connecteurConfig->get(self::SEDA_GENERATOR_HASH_ALGORITHM_ID)) {
            0 => 'sha256',
            1 => 'sha512',
            default => throw new UnrecoverableException('Algorithme non supporté'),
        };
    }

    /**
     * @throws UnrecoverableException
     */
    public function getAlgorithmIdentifier(string $algorithm): string
    {
        return match ($algorithm) {
            'sha256' => 'http://www.w3.org/2001/04/xmlenc#sha256',
            'sha512' => 'http://www.w3.org/2001/04/xmlenc#sha512',
            default => throw new UnrecoverableException('Algorithme non supporté'),
        };
    }

    private function getSedaGeneratorURL(): string
    {
        $url_from_entity_connector = $this->connecteurConfig->get(self::SEDA_GENERATOR_URL_ID);
        if ($url_from_entity_connector) {
            return $url_from_entity_connector;
        }
        $globalConnectorConfig = $this->connecteurFactory->getGlobalConnecteurConfig(self::SEDA_GENERATOR_GLOBAL_TYPE);
        if (!$globalConnectorConfig) {
            return '';
        }
        return $globalConnectorConfig->get(self::SEDA_GENERATOR_URL_ID);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testConnexion(): string
    {
        if (!$this->getSedaGeneratorURL()) {
            throw new UnrecoverableException(
                "L'URL du générateur n'a pas été trouvé. Avez-vous pensé à créer un connecteur global Generateur SEDA et à l'associer ?"
            );
        }
        $curlWrapper = $this->curlWrapperFactory->getInstance();
        $result = $curlWrapper->get($this->getURLEndpoint(self::SEDA_GENERATOR_VERSION_PATH));
        if ($curlWrapper->getLastHttpCode() !== 200) {
            throw new UnrecoverableException(
                'SedaGenerator did not return a 200 response. ' . $curlWrapper->getFullMessage()
            );
        }
        return $result;
    }

    public function getPastellToSeda(): array
    {
        return [
            'archival_agency_identifier' => [
                'position' => 10,
                'seda' => 'ArchivalAgency.Identifier',
                'libelle' => "Identifiant du service d'archives",
                'commentaire' => 'ArchivalAgency - Identifier',
            ],
            'transferring_agency_identifier' => [
                'position' => 20,
                'seda' => 'TransferringAgency.Identifier',
                'libelle' => 'Identifiant du service versant',
                'commentaire' => 'TransferringAgency - Identifier',
            ],
            'commentaire' => [
                'position' => 30,
                'seda' => 'Comment',
                'libelle' => 'Commentaire du transfert',
                'commentaire' => 'Comment',
            ],
            'titre' => [
                'position' => 40,
                'seda' => 'Title',
                'libelle' => "Nom de l'archive",
                'commentaire' => 'Archive - Name (seda 1.0) / ArchiveUnit - Title (seda 2.1)',
            ],
            'archival_agreement' => [
                'position' => 50,
                'seda' => 'ArchivalAgreement',
                'libelle' => "Identifiant de l'accord de versement",
                'commentaire' => 'ArchivalAgreement',
            ],
            'ArchivalProfile' => [
                'position' => 60,
                'seda' => 'ArchivalProfile',
                'libelle' => "Identifiant du profil d'archivage",
                'commentaire' => 'ArchivalProfile',
            ],
            'ServiceLevel' => [
                'position' => 70,
                'seda' => 'ServiceLevel',
                'libelle' => 'Identifiant du niveau de service demandé',
            ],
            'DescriptionLanguage' => [
                'position' => 80,
                'seda' => 'DescriptionLanguage',
                'libelle' => 'Langue de la description',
                'commentaire' => 'DescriptionLanguage (forme attendue: fra (seda 1.0) / fr (seda 2.1))',
            ],
            'Language' => [
                'position' => 90,
                'seda' => 'Language',
                'libelle' => 'Langue du contenu',
                'commentaire' => 'Language (forme attendue: fra (seda 1.0) / fr (seda 2.1))',
            ],
            'DescriptionLevel' => [
                'position' => 100,
                'seda' => 'DescriptionLevel',
                'libelle' => 'Niveau de description',
                'commentaire' => 'DescriptionLevel (attendue : Fonds, Subfonds, Class, Collection, Series, Subseries, 
                    RecordGrp, SubGrp, File, Item, OtherLevel)',
            ],
            'archiveunits_title' => [
                'position' => 110,
                'seda' => 'ArchiveUnits.Title',
                'libelle' => "Description de l'unité d'archive principale",
                'commentaire' => 'Archive - Description (seda 1.0) / ArchiveUnit - Description (seda 2.1)',
            ],
            'StartDate' => [
                'position' => 120,
                'seda' => 'StartDate',
                'libelle' => 'Date de début',
                'commentaire' => 'OldestDate (seda 1.0)/ StartDate (seda 2.1) (forme attendue Y-m-d)',
            ],
            'EndDate' => [
                'position' => 130,
                'seda' => 'EndDate',
                'libelle' => 'Date de fin',
                'commentaire' => 'LatestDate (seda 1.0)/ EndDate (seda 2.1)  (forme attendue Y-m-d)',
            ],
            'CustodialHistory' => [
                'position' => 140,
                'seda' => 'CustodialHistory',
                'libelle' => 'Historique de conservation',
                'commentaire' => 'Archive - CustodialHistoryItem (seda 1.0)/ 
                    ArchiveUnit - CustodialHistoryItem (seda 2.1)',
            ],
            'keywords' => [
                'position' => 150,
                'seda' => 'AccessRule.Rule',
                'libelle' => 'Liste de mots-clés',
                'commentaire' =>
                  "Un mot clé par ligne de la forme : 'Contenu du mot-clé','KeywordReference','KeywordType'
                  <br/><br/>Attention, si un élément contient une virgule, il est nécessaire d'entourer l'expression par des 'guillemets'
                  <br/><br/>L'ensemble du champ est analysé avec Twig, puis les lignes sont lues comme des lignes CSV
                  ( , comme séparateur de champs, \" comme clôture de champs et \ comme caractère d'échappement)
                  <br/><br/>Les mots clés sont mis dans le bordereau au niveau ArchiveUnit - Keyword",
            ],
            'AccessRule_Rule' => [
                'position' => 160,
                'seda' => 'AccessRule.Rule',
                'libelle' => 'Délai de communicabilité',
                'commentaire' => 'Archive - AccessRestrictionRule - Code (seda 1.0)/ 
                    AccessRule - Rule (seda 2.1) (forme attendue : de AR038 à AR062)',
            ],
            'AccessRule_StartDate' => [
                'position' => 170,
                'seda' => 'AccessRule.StartDate',
                'libelle' => 'Date de départ du délai de communicabilité',
                'commentaire' => 'AccessRestrictionRule - StartDate (seda 1.0) / 
                    AccessRule - StartDate (forme attentue Y-m-d)',
            ],
            'AppraisalRule_Rule' => [
                'position' => 180,
                'seda' => 'AppraisalRule.Rule',
                'libelle' => "Durée d'utilité administrative (DUA)",
                'commentaire' => 'AppraisalRule - Duration (seda 1.0) / 
                    AppraisalRule - Rule (seda 2.1) (forme attendue encoder en xsd:duration, 
                    voir http://www.datypic.com/sc/xsd/t-xsd_duration.html)',
            ],
            'AppraisalRule_StartDate' => [
                'position' => 190,
                'seda' => 'AppraisalRule.StartDate',
                'libelle' => 'Date de départ de la DUA (Y-m-d)',
                'commentaire' => 'AppraisalRule - StartDate',
            ],
            'AppraisalRule_FinalAction' => [
                'position' => 200,
                'seda' => 'AppraisalRule.FinalAction',
                'libelle' => 'Sort final',
                'commentaire' => 'AppraisalRule - Code (seda 1.0) / 
                    AppraisalRule - FinalAction (seda 2.1) (forme attendue: Conserver OU Détruire)',
            ],
        ];
    }

    private function getURLEndpoint(string $endpoint_path): string
    {
        return \sprintf(
            '%s%s',
            \rtrim($this->getSedaGeneratorURL(), '/'),
            $endpoint_path
        );
    }

    /**
     * @throws DonneesFormulaireException
     * @throws SimpleXMLWrapperException
     * @throws UnrecoverableException
     * @throws \JsonException
     * @throws \Exception
     */
    public function getBordereau(FluxData $fluxData): string
    {
        if ($this->bordereau !== null) {
            return $this->bordereau;
        }

        if (!$this->getSedaGeneratorURL()) {
            throw new UnrecoverableException(
                "L'URL du générateur n'a pas été trouvé. Avez-vous pensé à créer un connecteur global Generateur SEDA et à l'associer ?"
            );
        }
        $curlWrapper = $this->curlWrapperFactory->getInstance();

        $dataFromBordereau = \json_decode(
            $this->connecteurConfig->getFileContent('data') ?: '{}',
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
        $dataFromFiles = $this->connecteurConfig->getFileContent('files') ?: '';
        $dataFromAdvancedData = \json_decode(
            $this->connecteurConfig->getFileContent('advanced_data') ?: '{}',
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
        $message = $this->getMessage($fluxData, $dataFromBordereau, $dataFromFiles, $dataFromAdvancedData);

        if ($this->connecteurConfig->get('template')) {
            $curlWrapper->addPostFile('template', $this->connecteurConfig->getFilePath('template'));
            $tmp_folder = $this->tmpFolder->create();
            try {
                \file_put_contents($tmp_folder . '/data.json', \json_encode($message, \JSON_THROW_ON_ERROR));
                $curlWrapper->addPostFile('json_data', $tmp_folder . '/data.json');
                $url = $this->getURLEndpoint(self::SEDA_GENERATOR_GENERATE_PATH_WITH_TEMPLATE);
                $result = $curlWrapper->get($url);
            } finally {
                $this->tmpFolder->delete($tmp_folder);
            }
        } else {
            $curlWrapper->setJsonPostData(
                $message,
                0
            );
            $url = $this->getURLEndpoint(self::SEDA_GENERATOR_GENERATE_PATH);
            $result = $curlWrapper->get($url);
        }

        if ($curlWrapper->getLastHttpCode() !== 200) {
            throw new UnrecoverableException(
                'SedaGenerator did not return a 200 response. ' . $curlWrapper->getFullMessage()
            );
        }
        if (!$result) {
            throw new UnrecoverableException($curlWrapper->getLastError());
        }
        if (\json_decode($result, true)) {
            $json = \json_decode($result, true);
            if (isset($json['message'])) {
                throw new UnrecoverableException($json['message']);
            }
        }

        $this->bordereau = $result;
        return $result;
    }

    public function validateBordereau(string $bordereau): bool
    {
        return true;
    }

    /**
     * @return \LibXMLError[]
     */
    public function getLastValidationError(): array
    {
        return [];
    }

    /**
     * @throws UnrecoverableException
     * @throws \Exception
     */
    public function generateArchive(FluxData $fluxData, string $archive_path): void
    {
        $tmp_folder = $this->tmpFolder->create();
        try {
            $this->generateArchiveThrow($fluxData, $archive_path, $tmp_folder);
        } catch (\Exception $e) {
            throw new UnrecoverableException($e->getMessage());
        } finally {
            $this->tmpFolder->delete($tmp_folder);
        }
    }

    /**
     * @throws UnrecoverableException
     * @throws DonneesFormulaireException
     * @throws SimpleXMLWrapperException
     * @throws \JsonException
     */
    public function getMessage(
        FluxData $fluxData,
        array $dataFromBordereau,
        string $dataFromFiles,
        array $dataFromAdvancedData
    ): array {
        $algorithm = $this->getHashAlgorithm();
        $message = $this->sedaMessageBuilder
            ->setDonneesFormulaire($this->getDocDonneesFormulaire())
            ->setHashAlgorithm($algorithm)
            ->setAlgorithmIdentifier($this->getAlgorithmIdentifier($algorithm))
            ->setFluxData($fluxData)
            ->setVersion($this->getVersion())
            ->buildHeaders($dataFromBordereau)
            ->buildKeywords($dataFromBordereau['keywords'] ?? '')
            ->buildCustom($dataFromAdvancedData)
            ->buildFiles($dataFromFiles)
            ->buildArchiveUnit($dataFromFiles)
            ->getMessage();

        return \json_decode(
            \json_encode($message, \JSON_THROW_ON_ERROR),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }
}
