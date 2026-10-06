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

    /**
     * @return Generator<array>
     */
    public function streamRows(
        $id_e,
        $type,
        $id_d,
        $id_u,
        $recherche,
        $date_debut,
        $date_fin,
        $offset = 0,
        $limit = -1,
        bool $tri_croissant = false
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
                $tri_croissant
            ) as $row
        ) {
            unset($row['preuve']);
            yield $row;
        }
    }
}
