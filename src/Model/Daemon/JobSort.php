<?php

declare(strict_types=1);

namespace Pastell\Model\Daemon;

use Recuperateur;

final readonly class JobSort
{
    public function __construct(
        public JobSortColumn $column,
        public SortDirection $direction = SortDirection::DESC,
    ) {
    }

    public static function fromRecuperateur(Recuperateur $recuperateur): ?self
    {
        $column = JobSortColumn::tryFromParam($recuperateur->get('sort_by', ''));
        if ($column === null) {
            return null;
        }

        return new self(
            $column,
            SortDirection::fromParam($recuperateur->get('sort_dir', '')),
        );
    }
}
