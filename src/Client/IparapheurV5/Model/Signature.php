<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

class Signature
{
    public string $signatureEncoding;
    public string $signatureMethod;
    public string $signatureValue;
    public string $signatureValidationRules;
}
