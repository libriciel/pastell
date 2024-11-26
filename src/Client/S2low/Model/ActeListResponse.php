<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Model;

class ActeListResponse
{
    public string $statusId;
    public string $authorityId;
    public string $limit;
    public string $offset;
    /**
     * @var Transaction[] $transactions
     */
    public array $transactions;
}
