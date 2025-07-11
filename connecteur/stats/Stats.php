<?php

use Pastell\Service\Document\DocumentSize;

class Stats extends Connecteur
{
    public const ENTITY_ID_FIELD = 'entity_id';
    public const INCLUDE_CHILDREN_FIELDS = 'include_children';
    public const TYPE_FIELD = 'module_type';
    public const START_DATE_FIELD = 'start_date';
    public const END_DATE_FIELD = 'end_date';
    public const CSV_GENERATION_DATE_FIELD = 'csv_generation_date';
    public const CSV_FILE_FIELD = 'csv_file';
    private DonneesFormulaire $connecteurConfig;
    /**
     * @var DocumentSQL
     */
    private $documentSQL;
    /**
     * @var EntiteSQL
     */
    private $entiteSQL;
    /**
     * @var DocumentSize
     */
    private $documentSize;
    /**
     * @var DocumentTypeFactory
     */
    private $documentTypeFactory;
    private int $entityId;
    private bool $includeChildren;
    /**
     * @var string
     */
    private $moduleType;
    /**
     * @var string
     */
    private $startDate;
    /**
     * @var string
     */
    private $endDate;


    public function __construct(
        DocumentSQL $documentSQL,
        EntiteSQL $entiteSQL,
        DocumentSize $documentSize,
        DocumentTypeFactory $documentTypeFactory
    ) {
        $this->documentSQL = $documentSQL;
        $this->entiteSQL = $entiteSQL;
        $this->documentSize = $documentSize;
        $this->documentTypeFactory = $documentTypeFactory;
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->connecteurConfig = $donneesFormulaire;
        $this->entityId = (int)$donneesFormulaire->get(self::ENTITY_ID_FIELD);
        $this->includeChildren = (bool)$donneesFormulaire->get(self::INCLUDE_CHILDREN_FIELDS);
        $this->moduleType = $donneesFormulaire->get(self::TYPE_FIELD);
        $this->startDate = $this->connecteurConfig->get(self::START_DATE_FIELD);
        $this->endDate = $this->connecteurConfig->get(self::END_DATE_FIELD);
    }

    /**
     * @throws Exception
     */
    public function getStats(): bool
    {
        $documentType = $this->documentTypeFactory->getFluxDocumentType($this->moduleType);
        $this->connecteurConfig->setData(self::CSV_GENERATION_DATE_FIELD, date('Y-m-d'));
        $filename = sprintf(
            '%s_%s_%s-%s.csv',
            $this->entiteSQL->getDenomination($this->entityId),
            $this->moduleType,
            $this->startDate,
            $this->endDate
        );
        $this->connecteurConfig->addFileFromData(
            self::CSV_FILE_FIELD,
            $filename,
            ''
        );

        $file = new SplFileObject($this->connecteurConfig->getFilePath(self::CSV_FILE_FIELD), 'wb');
        $file->fputcsv(
            ['id_e', 'Entité', 'Nombre', 'Taille en octet', 'Taille arrondie', 'État', 'État label'],
            escape: ''
        );

        $this->writeEntityUsageToFile($this->entityId, $documentType, $file);

        if ($this->includeChildren) {
            $allChildren = $this->entiteSQL->getAllChildren($this->entityId);
            foreach ($allChildren as $children) {
                $this->writeEntityUsageToFile($children['id_e'], $documentType, $file);
            }
        }

        $file = null;

        return true;
    }

    private function writeEntityUsageToFile(int $entityId, DocumentType $documentType, SplFileObject $file): void
    {
        $documents = $this->documentSQL->getDocumentsLastActionByTypeEntityAndCreationDate(
            $entityId,
            $this->moduleType,
            $this->startDate,
            $this->endDate
        );

        $data = $this->getUsageFromDocuments($entityId, $documents, $documentType);

        foreach ($data['documentsInfo'] as $state => $documentsInfo) {
            $file->fputcsv(
                [
                    $data['id_e'],
                    $data['denomination'],
                    $documentsInfo['number'],
                    $documentsInfo['size'],
                    $this->documentSize->getHumanReadableSize($documentsInfo['size']),
                    $state,
                    $documentsInfo['action_label'],
                ],
                escape: ''
            );
        }
    }

    private function getUsageFromDocuments(int $entityId, array $documents, DocumentType $documentType): array
    {
        $entity = [
            'id_e' => $entityId,
            'denomination' => $this->entiteSQL->getDenomination($entityId),
            'documentsInfo' => [],
        ];
        foreach ($documents as $document) {
            if (!array_key_exists($document['last_action'], $entity['documentsInfo'])) {
                $entity['documentsInfo'][$document['last_action']]['number'] = 0;
                $entity['documentsInfo'][$document['last_action']]['size'] = 0;
                $entity['documentsInfo'][$document['last_action']]['action_label'] = $documentType->getAction()
                    ->getActionName($document['last_action']);
            }
            ++$entity['documentsInfo'][$document['last_action']]['number'];
            $entity['documentsInfo'][$document['last_action']]['size'] += $this->documentSize->getSize(
                $document['id_d']
            );
        }
        return $entity;
    }

    /**
     * @throws Exception
     */
    public function fieldVerification(): void
    {
        $entity_id = $this->connecteurConfig->get('entity_id');
        $module_type = $this->connecteurConfig->get('module_type');
        if ($entity_id === false) {
            throw new \RuntimeException('Veuillez renseigner une entité');
        }
        if (!$module_type) {
            throw new \RuntimeException('Veuillez renseigner un type de dossier');
        }
    }
}
