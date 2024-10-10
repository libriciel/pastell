<?php

namespace Pastell\Client\S2low\Responses;

class ActeListResponse
{
    public function __construct(
        public string $status_id,
        public array $transactions,
    ) {
    }
}