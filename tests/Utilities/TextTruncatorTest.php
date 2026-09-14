<?php

declare(strict_types=1);

namespace Pastell\Tests\Utilities;

use Pastell\Utilities\TextTruncator;
use PHPUnit\Framework\TestCase;

class TextTruncatorTest extends TestCase
{
    public static function truncateProvider(): \Generator
    {
        yield ['abcdefgh', 6, 'a…h'];
        yield ['aébècêdë', 8, 'a…ë'];
        yield ['👋aébècêdë', 12, '👋…dë'];
    }

    /**
     * @dataProvider truncateProvider
     */
    public function testTruncate(string $text, int $bytes, string $expectedResult): void
    {
        $textTruncated = TextTruncator::truncate($text, $bytes);
        self::assertSame($expectedResult, $textTruncated);
        self::assertLessThanOrEqual($bytes, \strlen($textTruncated));
    }
}
