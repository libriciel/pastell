<?php

declare(strict_types=1);

namespace Pastell\Tests\Validator;

use Pastell\Validator\UrlValidator;
use PHPUnit\Framework\TestCase;

class UrlValidatorTest extends TestCase
{
    public static function urlProvider(): \Generator
    {
        yield ['http://test', true];
        yield ['https://test', true];
        yield ['//test', true];
        yield ['/test', true];
        yield ['htp://test', false];
        yield ['file://test', false];
        yield ['phar://test', false];
        yield ['test', true];
        yield ['', true];
        yield [' ', true];
    }

    /**
     * @dataProvider urlProvider
     */
    public function testIsValid(string $url, bool $expected): void
    {
        $validator = new UrlValidator();
        self::assertSame($expected, $validator->isValid($url));
    }
}
