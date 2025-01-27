<?php

declare(strict_types=1);

namespace Pastell\Validator;

final class UrlValidator implements UrlValidatorInterface
{
    public function isValid(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!\in_array($scheme, ['http', 'https', null], true)) {
            return false;
        }
        return true;
    }

    public function validate(string $url): void
    {
        if (!$this->isValid($url)) {
            throw new \InvalidArgumentException(
                "Invalid URL: '$url'. Only 'http' and 'https' schemes are allowed."
            );
        }
    }
}
