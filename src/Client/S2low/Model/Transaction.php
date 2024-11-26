<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Model;

final class Transaction
{
    public string $id;
    public string $subject;
    public string $number;
    public string $date;
    public string $natureDescr;
    public string $classification;
    public string $type;
}
