<?php

namespace Pastell\System\Check;

use Pastell\System\CheckInterface;
use Pastell\System\HealthCheckItem;
use SQLQuery;

class CrashedTablesCheck implements CheckInterface
{
    public function __construct(
        private readonly SQLQuery $sqlQuery,
    ) {
    }

    public function check(): array
    {
        return [$this->checkCrashedTables()];
    }

    private function checkCrashedTables(): HealthCheckItem
    {
        $crashedTable = $this->sqlQuery->getTablesMarkedAsCrashed();
        $crashedTableResult = 'Aucune';
        $success = true;
        $details = [];
        if (!empty($crashedTable)) {
            $crashedTableResult = '';
            $success = false;
            foreach ($crashedTable as $table) {
                $details[] = new HealthCheckItem($table['Name'], $table['Comment']);
            }
        }

        return (new HealthCheckItem('Table(s) crashée(s)', $crashedTableResult))
            ->setSuccess($success)
            ->setDetails($details);
    }
}
