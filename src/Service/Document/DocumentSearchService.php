<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentActionEntiteSQL;
use DocumentEntiteSQL;
use DocumentIndexSQL;

final class DocumentSearchService
{
    private const CSV_HEADER = ['ENTITE', 'ID_D', 'TYPE', 'TITRE', 'DERNIERE ACTION', 'DATE DERNIERE ACTION'];

    public function __construct(
        private readonly DocumentActionEntiteSQL $documentActionEntiteSQL,
        private readonly DocumentEntiteSQL $documentEntiteSQL,
        private readonly DocumentIndexSQL $documentIndexSQL,
    ) {
    }

    public function search(
        DocumentSearchCriteria $criteria,
        int $offset,
        int $limit,
        string $sort = '',
        string $direction = 'DESC',
    ): array {
        $document_list = $this->documentEntiteSQL->search($criteria, $sort, $direction, $offset, $limit);
        foreach ($document_list as &$document_info) {
            $id_e = (int) $document_info['id_e'];
            $document_info['entite'] = $this->documentActionEntiteSQL->getOtherEntites($id_e, $document_info['id_d']);
            $document_info['last_action_display'] = $this->documentActionEntiteSQL->getLastActionByDate(
                $id_e,
                $document_info['id_d']
            );
        }
        return $document_list;
    }

    public function count(DocumentSearchCriteria $criteria): int
    {
        return $this->documentEntiteSQL->countBySearch($criteria);
    }

    public function getOffset(string $last_id_d, int $id_e, string $type, int $limit): int
    {
        $last_action_date = $this->documentEntiteSQL->getLastActionDate($id_e, $last_id_d);
        if ($last_action_date === null) {
            return 0;
        }
        $nb_document = $this->documentEntiteSQL->countDocumentsWithLastActionAfter($id_e, $type, $last_action_date);
        return intdiv($nb_document, $limit) * $limit;
    }

    public function getIndexValues(string $id_d): array
    {
        return $this->documentIndexSQL->getAll($id_d);
    }

    public function getCsvLines(
        DocumentSearchCriteria $criteria,
        array $indexed_fields,
        string $sort,
        string $direction,
    ): array {
        $document_list = $this->search($criteria, 0, $this->count($criteria), $sort, $direction);

        $line_list = [array_merge(self::CSV_HEADER, array_values($indexed_fields))];
        foreach ($document_list as $document_info) {
            $line = [
                $document_info['denomination'],
                $document_info['id_d'],
                $document_info['type'],
                $document_info['titre'],
                $document_info['last_action'],
                $document_info['last_action_date'],
            ];
            foreach (array_keys($indexed_fields) as $field_name) {
                $line[] = $this->documentIndexSQL->get($document_info['id_d'], $field_name);
            }
            $line_list[] = $line;
        }
        return $line_list;
    }
}
