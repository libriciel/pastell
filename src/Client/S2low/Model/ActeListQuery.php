<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Model;

class ActeListQuery
{
    public int $statusId;
    public int $limit;
    public int $offset;
    public string $minDate;
    public string $maxDate;
}
