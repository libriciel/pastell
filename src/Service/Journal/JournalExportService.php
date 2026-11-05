<?php

declare(strict_types=1);

namespace Pastell\Service\Journal;

use Generator;
use JournalSQL;

final class JournalExportService
{
    public function __construct(
        private readonly JournalSQL $journalSQL,
    ) {
    }

    public function streamRows(
        int $id_e,
        string $type,
        string $id_d,
        int $id_u,
        string $recherche,
        string $date_debut,
        string $date_fin,
        int $offset = 0,
        int $limit = -1,
        bool $tri_croissant = false,
        bool $buffered = false
    ): Generator {
        foreach (
            $this->journalSQL->streamList(
                $id_e,
                $type,
                $id_d,
                $id_u,
                $recherche,
                $date_debut,
                $date_fin,
                $offset,
                $limit,
                $tri_croissant,
                $buffered
            ) as $row
        ) {
            unset($row['preuve']);
            yield $row;
        }
    }
}
