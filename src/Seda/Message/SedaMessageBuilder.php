<?php

declare(strict_types=1);

namespace Pastell\Seda\Message;

use DonneesFormulaire;
use DonneesFormulaireException;
use FileContentType;
use FluxData;
use GenerateurSedaFillFiles;
use Pastell\Seda\Message\Part\ArchiveUnit;
use Pastell\Seda\Message\Part\File;
use Pastell\Seda\Message\Part\Keyword;
use Pastell\Seda\SedaVersion;
use Pastell\Service\Document\DocumentPastellMetadataService;
use Pastell\Service\SimpleTwigRenderer;
use SimpleXMLWrapperException;
use TmpFolder;
use UnrecoverableException;
use ZipArchive;

class SedaMessageBuilder
{
    private ?string $zipDirectory = null;
    /** @var callable $idGeneratorFunction */
    private $idGeneratorFunction;

    private DonneesFormulaire $donneesFormulaire;
    private string $algorithm;
    private string $algorithmIdentifier;
    private FluxData $fluxData;
    private SedaMessage $message;
    private array $filenameList = [];

    public function __construct(
        private readonly TmpFolder $tmpFolder,
        private readonly DocumentPastellMetadataService $documentPastellMetadataService,
    ) {
        $this->message = new SedaMessage();
        $this->setIdGeneratorFunction(fn() => 'id_' . \uuid_create(\UUID_TYPE_RANDOM));
    }

    public function __destruct()
    {
        if ($this->zipDirectory !== null) {
            $this->tmpFolder->delete($this->zipDirectory);
        }
    }

    public function setIdGeneratorFunction(callable $idGeneratorFunction): void
    {
        $this->idGeneratorFunction = $idGeneratorFunction;
    }

    public function getDonneesFormulaire(): DonneesFormulaire
    {
        if (!isset($this->donneesFormulaire)) {
            throw new \RuntimeException('DonneesFormulaire is not set');
        }
        return $this->donneesFormulaire;
    }


    public function setDonneesFormulaire(DonneesFormulaire $donneesFormulaire): self
    {
        $this->donneesFormulaire = $donneesFormulaire;
        return $this;
    }

    public function setHashAlgorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    public function setAlgorithmIdentifier(string $identifier): self
    {
        $this->algorithmIdentifier = $identifier;
        return $this;
    }

    public function getFluxData(): FluxData
    {
        if (!isset($this->fluxData)) {
            throw new \RuntimeException('FluxData is not set');
        }
        return $this->fluxData;
    }

    public function setFluxData(FluxData $fluxData): self
    {
        $this->fluxData = $fluxData;
        return $this;
    }

    public function getMessage(): SedaMessage
    {
        return $this->message;
    }

    public function setVersion(SedaVersion $version): static
    {
        $this->message->setVersion($version);
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    public function buildHeaders(array $dataFromBordereau): self
    {
        foreach ($dataFromBordereau as $i => $data) {
            $dataFromBordereau[$i] = $this->getStringWithMetatadaReplacement($data);
        }

        $this->message->comment = $dataFromBordereau['commentaire'] ?? null;
        $this->message->title = $dataFromBordereau['titre'] ?? null;
        $this->message->archivalAgreement = $dataFromBordereau['archival_agreement'] ?? null;
        $this->message->archivalProfile = $dataFromBordereau['ArchivalProfile'] ?? null;
        $this->message->language = $dataFromBordereau['Language'] ?? null;
        $this->message->descriptionLanguage = $dataFromBordereau['DescriptionLanguage'] ?? null;
        $this->message->descriptionLevel = $dataFromBordereau['DescriptionLevel'] ?? null;
        $this->message->description = $dataFromBordereau['archiveunits_title'] ?? null;
        $this->message->custodialHistory = $dataFromBordereau['CustodialHistory'] ?? null;
        $this->message->serviceLevel = $dataFromBordereau['ServiceLevel'] ?? null;
        $this->message->startDate = $dataFromBordereau['StartDate'] ?? null;
        $this->message->endDate = $dataFromBordereau['EndDate'] ?? null;
        $this->message->originatingAgencyIdentifier = $dataFromBordereau['OriginatingAgencyIdentifier'] ?? null;
        $this->message->submissionAgencyIdentifier = $dataFromBordereau['SubmissionAgencyIdentifier'] ?? null;
        $this->message
            ->transferringAgencyArchiveIdentifier = $dataFromBordereau['TransferringAgencyArchiveIdentifier'] ?? null;
        $this->message
            ->originatingAgencyArchiveIdentifier = $dataFromBordereau['OriginatingAgencyArchiveIdentifier'] ?? null;
        $this->message->externalReferenceName = $dataFromBordereau['ArchiveUnit_ExternalReferenceName'] ?? null;
        $this->message->externalReference = $dataFromBordereau['ArchiveUnit_ExternalReference'] ?? null;

        if ($this->message->getVersion() === SedaVersion::VERSION_1_0) {
            $appraisalRuleFinalAction = [
                'Conserver' => 'conserver',
                'Détruire' => 'detruire',
            ];
        } else {
            $appraisalRuleFinalAction = [
                'Conserver' => 'Keep',
                'Détruire' => 'Destroy',
            ];
        }

        $this->message
            ->setArchivalAgency(
                $dataFromBordereau['archival_agency_identifier'] ?? null,
                $dataFromBordereau['archival_agency_name'] ?? null,
            )
            ->setTransferringAgency(
                $dataFromBordereau['transferring_agency_identifier'] ?? null,
                $dataFromBordereau['transferring_agency_name'] ?? null,
            )
            ->setOriginationAgency(
                $dataFromBordereau['originating_agency_identifier'] ?? null,
                $dataFromBordereau['originating_agency_name'] ?? null,
            )
            ->setAccessRule(
                $dataFromBordereau['AccessRule_Rule'] ?? null,
                $dataFromBordereau['AccessRule_StartDate'] ?? null,
            )
            ->setAppraisalRule(
                $dataFromBordereau['AppraisalRule_Rule'] ?? null,
                $appraisalRuleFinalAction[$dataFromBordereau['AppraisalRule_FinalAction'] ?? null] ?? null,
                $dataFromBordereau['AppraisalRule_StartDate'] ?? null,
            )
        ;
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    public function buildCustom(array $dataFromAdvancedData): self
    {
        foreach ($dataFromAdvancedData as $i => $data) {
            $this->message->addCustom(
                $i,
                $this->getStringWithMetatadaReplacement($data),
            );
        }
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    public function buildKeywords(string $keywordsData): self
    {
        $keywords_data = $this->getStringWithMetatadaReplacement($keywordsData);
        $keywords = \explode("\n", $keywords_data);
        foreach ($keywords as $keywordLine) {
            $keywordLine = \trim($keywordLine);
            if (!$keywordLine) {
                continue;
            }
            $keywordProperties = \str_getcsv($keywordLine);
            $this->message->addKeyword(
                $keywordProperties[0],
                $keywordProperties[1] ?? null,
                $keywordProperties[2] ?? null,
            );
        }
        return $this;
    }

    /**
     * @throws UnrecoverableException
     * @throws SimpleXMLWrapperException
     * @throws DonneesFormulaireException
     */
    public function buildFiles(string $dataFromFiles): self
    {
        $sedaGeneriqueFilleFiles = new GenerateurSedaFillFiles($dataFromFiles);
        $files = $this->getFiles($sedaGeneriqueFilleFiles);
        foreach ($files as $file) {
            $this->message->addFile($file);
        }

        return $this;
    }

    /**
     * @throws UnrecoverableException
     * @throws DonneesFormulaireException
     * @throws SimpleXMLWrapperException
     */
    public function buildArchiveUnit(string $dataFromFiles): self
    {
        $sedaGeneriqueFilleFiles = new GenerateurSedaFillFiles($dataFromFiles);

        $archiveUnits = $this->getArchiveUnits($sedaGeneriqueFilleFiles);
        foreach ($archiveUnits as $archiveUnit) {
            $this->message->addArchiveUnit($archiveUnit);
        }

        return $this;
    }

    /**
     * @return File[]
     * @throws UnrecoverableException
     * @throws DonneesFormulaireException
     */
    private function getFiles(GenerateurSedaFillFiles $sedaGeneriqueFilleFiles, string $parentId = ''): array
    {
        $files = [];
        $specificInfo = $this->getSpecificInfoDefinition($sedaGeneriqueFilleFiles, $parentId);

        foreach ($sedaGeneriqueFilleFiles->getFiles($parentId) as $localFile) {
            $field = $this->getStringWithMetatadaReplacement((string)$localFile['field_expression']);

            if (!\is_array($this->getDonneesFormulaire()->get($field))) {
                continue;
            }
            foreach ($this->getDonneesFormulaire()->get($field) as $filenum => $filename) {
                $file = new File(($this->idGeneratorFunction)());
                $file->filename = $filename;
                $file->messageDigest = $this->getDonneesFormulaire()->getFileDigest($field, $filenum, $this->algorithm);
                $file->algorithmIdentifier = $this->algorithmIdentifier;
                $file->uri = $this->normalizeUri($filename, $file->messageDigest);
                $file->size = (string)$this->getDonneesFormulaire()->getFileSize($field, $filenum);
                if (empty($localFile['do_not_put_mime_type'])) {
                    $file->mimeType = $this->getDonneesFormulaire()->getContentType($field, $filenum);
                }
                $sedaInfoFromSpecificInfo = $this->getSedaInfoFromSpecificInfo(
                    $this->getSedaInfoFromSpecificInfoWithLocalDescription(
                        $specificInfo,
                        $this->getDonneesFormulaire()->getFilePath($field, $filenum),
                        false
                    )
                );
                $file
                    ->setAppraisalRule(
                        $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_Rule'] ?? null,
                        $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_FinalAction'] ?? null,
                        $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_StartDate'] ?? null,
                    )
                    ->setAccessRestrictionRule(
                        $sedaInfoFromSpecificInfo['AccessRestrictionRule_AccessRule'] ?? null,
                        $sedaInfoFromSpecificInfo['AccessRestrictionRule_StartDate'] ?? null,
                    )
                    ->setContentDescription(
                        $sedaInfoFromSpecificInfo['Description'] ?? null,
                        $sedaInfoFromSpecificInfo['DescriptionLevel'] ?? null,
                        $sedaInfoFromSpecificInfo['Language'] ?? null,
                        $sedaInfoFromSpecificInfo['CustodialHistory'] ?? null,
                        $sedaInfoFromSpecificInfo['Keywords'] ?? null,
                    );
                $description = (string)$localFile['description'];
                $description = \str_replace('#FILE_NUM#', (string)$filenum, $description);
                $file->title = $this->getStringWithMetatadaReplacement($description);
                $this->getFluxData()->setFileList(
                    $localFile['field_expression'],
                    $file->uri,
                    $this->getDonneesFormulaire()->getFilePath($field, $filenum)
                );
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @return ArchiveUnit[]
     * @throws UnrecoverableException
     * @throws DonneesFormulaireException
     */
    private function getArchiveUnits(
        GenerateurSedaFillFiles $sedaGeneriqueFilleFiles,
        string $parentId = ''
    ): array {
        $archiveUnits = [];
        $archiveUnit = null;
        if ($parentId !== '') {
            $archiveUnit = new ArchiveUnit(($this->idGeneratorFunction)());
            $specificInfo = $this->getSpecificInfo($sedaGeneriqueFilleFiles, $parentId);
            $archiveUnit
                ->setAppraisalRule(
                    $specificInfo['ArchiveUnit_AppraisalRule_Rule'] ?? null,
                    $specificInfo['ArchiveUnit_AppraisalRule_FinalAction'] ?? null,
                    $specificInfo['ArchiveUnit_AppraisalRule_StartDate'] ?? null,
                )
                ->setAccessRestrictionRule(
                    $specificInfo['AccessRestrictionRule_AccessRule'] ?? null,
                    $specificInfo['AccessRestrictionRule_StartDate'] ?? null,
                )
                ->setContentDescription(
                    $specificInfo['Description'] ?? null,
                    $specificInfo['DescriptionLevel'] ?? null,
                    $specificInfo['Language'] ?? null,
                    $specificInfo['CustodialHistory'] ?? null,
                    $specificInfo['Keywords'] ?? null,
                )
                ->setFiles($this->getFiles($sedaGeneriqueFilleFiles, $parentId));
            $archiveUnit->title = $this->getStringWithMetatadaReplacement(
                $sedaGeneriqueFilleFiles->getDescription($parentId)
            );
            $archiveUnits[] = $archiveUnit;
        }

        foreach ($sedaGeneriqueFilleFiles->getFiles($parentId) as $files) {
            $field = $this->getStringWithMetatadaReplacement((string)$files['field_expression']);
            if (\preg_match('/#ZIP#/', $field)) {
                $archiveFromZip = $this->getArchiveUnitFromZip(
                    (string)$files['description'],
                    $field,
                    0,
                    $this->getSpecificInfoDefinition($sedaGeneriqueFilleFiles, $parentId),
                    (!empty($files['do_not_put_mime_type'])),
                    $archiveUnit
                );
                if ($archiveFromZip !== null && $archiveFromZip !== $archiveUnit) {
                    foreach ($archiveFromZip->getFiles() as $file) {
                        $this->message->addFile($file);
                    }
                    foreach ($archiveFromZip->getArchiveUnits() as $unit) {
                        $archiveUnits[] = $unit;
                    }
                }
            }
        }

        $archiveUnitsFromRoot = [];
        foreach ($sedaGeneriqueFilleFiles->getArchiveUnit($parentId) as $localArchiveUnit) {
            if ((string)$localArchiveUnit['field_expression']) {
                $field_expression_result = $this->getStringWithMetatadaReplacement(
                    (string)$localArchiveUnit['field_expression']
                );
                if (!$field_expression_result) {
                    continue;
                }
            }
            $archiveUnitsFromCurrent = $this->getArchiveUnits(
                $sedaGeneriqueFilleFiles,
                (string)$localArchiveUnit['id']
            );
            if ($archiveUnit === null) {
                $archiveUnitsFromRoot[] = $archiveUnitsFromCurrent;
            } else {
                foreach ($archiveUnitsFromCurrent as $toto) {
                    $archiveUnit->addArchiveUnit($toto);
                }
            }
        }
        if ($archiveUnitsFromRoot !== []) {
            $archiveUnits = \array_merge($archiveUnits, ...$archiveUnitsFromRoot);
        }
        return $archiveUnits;
    }

    /**
     * @return Keyword[]
     * @throws UnrecoverableException
     */
    private function getInputDataKeywords(string $keywords_data): array
    {
        $result = [];
        $keywords_data = $this->getStringWithMetatadaReplacement($keywords_data);
        $keywords = \explode("\n", $keywords_data);
        foreach ($keywords as $keyword_line) {
            $keyword_line = \trim($keyword_line);
            if (!$keyword_line) {
                continue;
            }
            $keyword_properties = \str_getcsv($keyword_line);
            $keyword = new Keyword();
            $keyword->keywordContent = $keyword_properties[0];
            $keyword->keywordReference = $keyword_properties[1] ?? null;
            $keyword->keywordType = $keyword_properties[2] ?? null;
            $result[] = $keyword;
        }
        return $result;
    }

    /**
     * @throws UnrecoverableException
     */
    private function getSedaInfoFromSpecificInfo(array $specificInfo): array
    {
        $sedaArchiveUnits = [];
        if (!empty($specificInfo['Description'])) {
            $sedaArchiveUnits['Description'] = $this->getStringWithMetatadaReplacement($specificInfo['Description']);
        }
        if (!empty($specificInfo['DescriptionLevel'])) {
            $sedaArchiveUnits['DescriptionLevel'] = $this->getStringWithMetatadaReplacement(
                $specificInfo['DescriptionLevel']
            );
        }
        if (!empty($specificInfo['Language'])) {
            $sedaArchiveUnits['Language'] = $this->getStringWithMetatadaReplacement(
                $specificInfo['Language']
            );
        }
        if (!empty($specificInfo['CustodialHistory'])) {
            $sedaArchiveUnits['CustodialHistory'] = $this->getStringWithMetatadaReplacement(
                $specificInfo['CustodialHistory']
            );
        }
        if (!empty($specificInfo['AccessRestrictionRule_AccessRule'])) {
            $sedaArchiveUnits['AccessRestrictionRule_AccessRule'] = $this->getStringWithMetatadaReplacement(
                $specificInfo['AccessRestrictionRule_AccessRule']
            );
        }
        if (!empty($specificInfo['AccessRestrictionRule_StartDate'])) {
            $sedaArchiveUnits['AccessRestrictionRule_StartDate'] = $this->getStringWithMetatadaReplacement(
                $specificInfo['AccessRestrictionRule_StartDate']
            );
        }
        if (!empty($specificInfo['ArchiveUnit_AppraisalRule_FinalAction'])) {
            $sedaArchiveUnits['ArchiveUnit_AppraisalRule_FinalAction'] = $this->getStringWithMetatadaReplacement(
                $specificInfo['ArchiveUnit_AppraisalRule_FinalAction']
            );
        }
        if (!empty($specificInfo['ArchiveUnit_AppraisalRule_Rule'])) {
            $sedaArchiveUnits['ArchiveUnit_AppraisalRule_Rule'] = $this->getStringWithMetatadaReplacement(
                $specificInfo['ArchiveUnit_AppraisalRule_Rule']
            );
        }
        if (!empty($specificInfo['ArchiveUnit_AppraisalRule_StartDate'])) {
            $sedaArchiveUnits['ArchiveUnit_AppraisalRule_StartDate'] = $this->getStringWithMetatadaReplacement(
                $specificInfo['ArchiveUnit_AppraisalRule_StartDate']
            );
        }
        if (!empty($specificInfo['Keywords'])) {
            $sedaArchiveUnits['Keywords'] = $this->getInputDataKeywords($specificInfo['Keywords']);
        }
        return $sedaArchiveUnits;
    }

    /**
     * @throws UnrecoverableException
     */
    private function getSpecificInfo(GenerateurSedaFillFiles $sedaGeneriqueFilleFiles, string $nodeId): array
    {
        $specificInfo = $this->getSpecificInfoDefinition($sedaGeneriqueFilleFiles, $nodeId);
        if (!$specificInfo) {
            return $specificInfo;
        }
        $specificInfo = $this->getSedaInfoFromSpecificInfoWithLocalDescription($specificInfo, '', true);
        return $this->getSedaInfoFromSpecificInfo(
            $specificInfo
        );
    }

    /**
     * @throws UnrecoverableException
     */
    private function getSpecificInfoDefinition(GenerateurSedaFillFiles $sedaGeneriqueFilleFiles, string $nodeId): array
    {
        $sedaArchiveUnits = [];
        if (!$nodeId) {
            return $sedaArchiveUnits;
        }
        return $sedaGeneriqueFilleFiles->getArchiveUnitSpecificInfo($nodeId);
    }

    /**
     * @throws UnrecoverableException
     */
    private function getStringWithMetatadaReplacement(string $expression): string
    {
        $donneesFormulaire = $this->getDonneesFormulaire();
        return (new SimpleTwigRenderer())->render(
            $expression,
            $donneesFormulaire,
            $this->documentPastellMetadataService->getMetadataPastellByDocument($donneesFormulaire->id_d)
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws \Exception
     */
    private function getArchiveUnitFromZip(
        string $description,
        string $field_expression,
        int $filenum = 0,
        array $specificInfo = [],
        bool $doNotPutMimeType = false,
        ?ArchiveUnit $archiveUnit = null,
    ): ?ArchiveUnit {
        $field = \str_replace('#ZIP#', '', $field_expression);

        $zipFilePath = $this->getDonneesFormulaire()->getFilePath($field, $filenum);
        if (!$zipFilePath) {
            return null;
        }

        $this->zipDirectory = $this->tmpFolder->create();

        $zip = new ZipArchive();
        $handle = $zip->open($zipFilePath);
        if ($handle !== true) {
            throw new UnrecoverableException("Impossible d'ouvrir le fichier zip (code erreur : $handle)");
        }
        $zip->extractTo($this->zipDirectory);
        $zip->close();
        return $this->getArchiveUnitFromFolder(
            $description,
            $this->zipDirectory,
            $field,
            $this->zipDirectory,
            $specificInfo,
            $doNotPutMimeType,
            $archiveUnit
        );
    }

    /**
     * @throws UnrecoverableException
     */
    private function getArchiveUnitFromFolder(
        string $description,
        string $folder,
        string $field,
        string $rootDirectory,
        array $specificInfo,
        bool $doNotPutMimeType = false,
        ?ArchiveUnit $archiveUnit = null,
    ): ArchiveUnit {
        $localDescription = $this->getLocalDescription(
            $description,
            $this->getRelativePath($rootDirectory, $folder),
            true
        );

        if ($archiveUnit === null) {
            $archiveUnit = new ArchiveUnit(($this->idGeneratorFunction)());
            $archiveUnit->title = $this->getStringWithMetatadaReplacement($localDescription);

            $sedaInfoFromSpecificInfo = $this->getSedaInfoFromSpecificInfo(
                $this->getSedaInfoFromSpecificInfoWithLocalDescription(
                    $specificInfo,
                    $this->getRelativePath($rootDirectory, $folder),
                    true
                )
            );

            $archiveUnit
                ->setAppraisalRule(
                    $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_Rule'] ?? null,
                    $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_FinalAction'] ?? null,
                    $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_StartDate'] ?? null,
                )
                ->setAccessRestrictionRule(
                    $sedaInfoFromSpecificInfo['AccessRestrictionRule_AccessRule'] ?? null,
                    $sedaInfoFromSpecificInfo['AccessRestrictionRule_StartDate'] ?? null,
                )
                ->setContentDescription(
                    $sedaInfoFromSpecificInfo['Description'] ?? null,
                    $sedaInfoFromSpecificInfo['DescriptionLevel'] ?? null,
                    $sedaInfoFromSpecificInfo['Language'] ?? null,
                    $sedaInfoFromSpecificInfo['CustodialHistory'] ?? null,
                    $sedaInfoFromSpecificInfo['Keywords'] ?? null,
                );
        }

        $dirContent = \array_diff(\scandir($folder), $this->excludeFileList());

        $files = [];
        foreach ($dirContent as $fileOrFolder) {
            $filepath = $folder . '/' . $fileOrFolder;
            if (\is_dir($filepath)) {
                $archiveUnit->addArchiveUnit(
                    $this->getArchiveUnitFromFolder(
                        $description,
                        $filepath,
                        $field,
                        $rootDirectory,
                        $specificInfo,
                        $doNotPutMimeType
                    )
                );
            } elseif (\is_file($filepath)) {
                $relativePath = $this->getRelativePath($rootDirectory, $filepath);
                $file = new File(($this->idGeneratorFunction)());
                $realFileName = \basename($relativePath);
                $file->filename = $realFileName;
                $file->messageDigest = \hash_file($this->algorithm, $filepath);
                $file->algorithmIdentifier = $this->algorithmIdentifier;
                $file->uri = $this->normalizeUri($relativePath, $file->messageDigest);
                $file->size = (string)\filesize($filepath);
                $sedaInfoFromSpecificInfo = $this->getSedaInfoFromSpecificInfo(
                    $this->getSedaInfoFromSpecificInfoWithLocalDescription(
                        $specificInfo,
                        $relativePath,
                        false
                    )
                );
                $file
                    ->setAppraisalRule(
                        $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_Rule'] ?? null,
                        $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_FinalAction'] ?? null,
                        $sedaInfoFromSpecificInfo['ArchiveUnit_AppraisalRule_StartDate'] ?? null,
                    )
                    ->setAccessRestrictionRule(
                        $sedaInfoFromSpecificInfo['AccessRestrictionRule_AccessRule'] ?? null,
                        $sedaInfoFromSpecificInfo['AccessRestrictionRule_StartDate'] ?? null,
                    )
                    ->setContentDescription(
                        $sedaInfoFromSpecificInfo['Description'] ?? null,
                        $sedaInfoFromSpecificInfo['DescriptionLevel'] ?? null,
                        $sedaInfoFromSpecificInfo['Language'] ?? null,
                        $sedaInfoFromSpecificInfo['CustodialHistory'] ?? null,
                        $sedaInfoFromSpecificInfo['Keywords'] ?? null,
                    );

                $fileContentType = new FileContentType();
                if (!$doNotPutMimeType) {
                    $file->mimeType = $fileContentType->getContentType($filepath);
                }

                $localDescription = $this->getLocalDescription($description, $relativePath, false);
                $file->title = $this->getStringWithMetatadaReplacement($localDescription);
                $files[] = $file;
                $this->getFluxData()->setFileList(
                    $field,
                    $file->uri,
                    $filepath
                );
            }
        }
        $archiveUnit->setFiles($files);
        return $archiveUnit;
    }

    private function getSedaInfoFromSpecificInfoWithLocalDescription(
        array $specificInfo,
        string $filepath,
        bool $isDirectory
    ): array {
        $result = [];
        foreach ($specificInfo as $id => $expression) {
            $result[$id] = $this->getLocalDescription($expression, $filepath, $isDirectory);
        }
        return $result;
    }

    private function getRelativePath(string $rootFolder, string $localFolder): string
    {
        $relativePath = \preg_replace("#$rootFolder#", '', $localFolder);
        return \ltrim($relativePath, '/');
    }

    private function getLocalDescription(string $description, string $filepath, bool $isDirectory): string
    {
        return \str_replace(
            ['#FILEPATH#', '#FILENAME#', '#IS_DIR#', '#IS_FILE#'],
            [$filepath, \basename($filepath), $isDirectory ? 'true' : 'false', $isDirectory ? 'false' : 'true'],
            $description
        );
    }

    private function excludeFileList(): array
    {
        return ['.', '..', '__MACOSX', '.DS_Store', '.gitkeep'];
    }

    protected function normalizeUri(string $filepath, string $digest): string
    {
        $i = 1;
        $pathInfo = pathinfo($filepath);
        while (in_array($filepath, $this->filenameList, true)) {
            $filepath = sprintf(
                '%s_%d.%s',
                $pathInfo['filename'],
                $i++,
                $pathInfo['extension']
            );
        }
        $this->filenameList[] = $filepath;
        return $filepath;
    }
}
