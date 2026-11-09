<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentActionEntiteSQL;
use DocumentEntiteSQL;

final class DocumentEntiteService
{
    public function __construct(
        private readonly DocumentEntiteSQL $documentEntiteSQL,
        private readonly DocumentActionEntiteSQL $documentActionEntiteSQL,
    ) {
    }

    public function getEntites(string $id_d): array
    {
        return $this->documentEntiteSQL->getEntites($id_d);
    }

    public function getEntiteId(string $id_d): ?int
    {
        return $this->documentEntiteSQL->getEntiteId($id_d);
    }

    public function getDocuments(int $id_e): array
    {
        return $this->documentEntiteSQL->getDocuments($id_e);
    }

    public function getDocumentsByType(int $id_e, string $type): array
    {
        return $this->documentEntiteSQL->getDocumentsByType($id_e, $type);
    }

    public function countDocuments(int $id_e, ?string $type = null): int
    {
        if ($type === null) {
            return $this->documentEntiteSQL->countDocuments($id_e);
        }
        return $this->documentEntiteSQL->countDocumentsByType($id_e, $type);
    }

    public function countDocumentLinks(int $id_e): int
    {
        return $this->documentEntiteSQL->countByEntity($id_e);
    }

    public function countByLastAction(?int $id_e, ?string $type): array
    {
        return $this->documentEntiteSQL->countByLastAction($id_e, $type);
    }

    /**
     * @param array<string, string> $filters
     */
    public function countByIndexFilters(int $id_e, string $type, array $filters): int
    {
        return $this->documentEntiteSQL->countByIndexFilters($id_e, $type, $filters);
    }

    public function getDocumentsWithLastActionOlderThan(int $nb_days, ?string $type, ?int $id_e): array
    {
        return $this->documentEntiteSQL->getDocumentsWithLastActionUntil($this->getDateDaysAgo($nb_days), $type, $id_e);
    }

    public function updateLastAction(int $id_e, string $id_d, string $action, string $date): void
    {
        $this->documentEntiteSQL->updateLastAction($id_e, $id_d, $action, $date);
    }

    public function getCurrentAction(int $id_e, string $id_d): ?string
    {
        return $this->documentEntiteSQL->getLastAction($id_e, $id_d);
    }

    public function getInfo(int $id_e, string $id_d): ?array
    {
        $document_info = $this->documentEntiteSQL->getInfo($id_e, $id_d);
        if ($document_info !== null) {
            $document_info['last_action_display'] = $this->documentActionEntiteSQL->getLastActionByDate($id_e, $id_d);
        }
        return $document_info;
    }

    public function getDocumentsByLastAction(int $id_e, string $type, string $last_action): array
    {
        return $this->documentEntiteSQL->getDocumentsByLastAction($id_e, $type, $last_action);
    }

    public function getDocumentsByLastActionOlderThan(int $id_e, string $type, string $last_action, int $nb_days): array
    {
        return $this->documentEntiteSQL->getDocumentsByLastActionUntil(
            $id_e,
            $type,
            $last_action,
            $this->getDateDaysAgo($nb_days)
        );
    }

    public function getDocumentsThroughActionOlderThan(int $id_e, string $type, string $action, int $nb_days): array
    {
        return $this->documentEntiteSQL->getDocumentsThroughActionUntil(
            $id_e,
            $type,
            $action,
            $this->getDateDaysAgo($nb_days)
        );
    }

    private function getDateDaysAgo(int $nb_days): string
    {
        return date('Y-m-d', strtotime("-$nb_days days"));
    }
}
