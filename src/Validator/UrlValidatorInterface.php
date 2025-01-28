<?php

declare(strict_types=1);

namespace Pastell\Validator;

interface UrlValidatorInterface
{
    public function isValid(string $url): bool;
    public function validate(string $url): void;
}
