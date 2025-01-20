<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Model;

class PesAllerListResponse
{
    public string $statusId;
    public string $authorityId;
    public string $offset;
    public string $limit;
    /**
     * @var array{array{id: string}}
     */
    public array $transactions;
}
