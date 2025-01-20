<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Model;

class PesAllerListQuery
{
    public int $statusId;
    public int $offset;
    public int $limit;
    public string $minDate;
    public string $maxDate;
}
