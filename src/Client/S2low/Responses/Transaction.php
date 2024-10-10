<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Responses;

class Transaction
{
    public function __construct(
        public string $id,
        public string $subject,
        public string $number,
        public string $date,
        public string $nature_descr,
        public string $classification,
        public string $type
    ) {
    }
}
