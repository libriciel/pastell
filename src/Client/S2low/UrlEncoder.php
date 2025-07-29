<?php

namespace Pastell\Client\S2low;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

class UrlEncoder implements EncoderInterface
{
    public function encode(mixed $data, string $format, array $context = []): string
    {
        $str = '';

        if (! is_iterable($data)) {
            throw new \RuntimeException('Unable to create url from data');
        }

        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                //TODO
                continue;
            }
            $str .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
        }

        return rtrim($str, '&');
    }

    public function supportsEncoding(string $format): bool
    {
        return $format === 'url';
    }
}
