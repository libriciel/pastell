<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentEntiteSQL;

final class DocumentEntiteService
{
    public function __construct(
        private readonly DocumentEntiteSQL $documentEntiteSQL,
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
        $date = date('Y-m-d', strtotime("-$nb_days days"));
        return $this->documentEntiteSQL->getDocumentsWithLastActionUntil($date, $type, $id_e);
    }

    public function updateLastAction(int $id_e, string $id_d, string $action, string $date): void
    {
        $this->documentEntiteSQL->updateLastAction($id_e, $id_d, $action, $date);
    }
}
