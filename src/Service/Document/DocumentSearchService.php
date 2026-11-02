<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentActionEntiteSQL;
use DocumentEntiteSQL;

final class DocumentSearchService
{
    public function __construct(
        private readonly DocumentActionEntiteSQL $documentActionEntiteSQL,
        private readonly DocumentEntiteSQL $documentEntiteSQL,
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
}
