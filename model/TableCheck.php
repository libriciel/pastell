<?php

/**
 * @deprecated 4.1.26 Use SQLQuery::getTablesMarkedAsCrashed instead.
 */
class TableCheck extends SQL
{
    public function getTablesMarkedAsCrashed(): array
    {
        $sql = "SHOW TABLE STATUS WHERE comment != ''";
        return $this->query($sql) ?: [];
    }
}
