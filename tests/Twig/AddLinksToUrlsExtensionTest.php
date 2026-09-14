<?php

declare(strict_types=1);

namespace Pastell\Tests\Twig;

use Pastell\Twig\AddLinksToUrlsExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

final class AddLinksToUrlsExtensionTest extends TestCase
{
    /**
     * @dataProvider urlGeneratorDataProvider
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function testAddLinksToUrlsFilter(string $template, string $expectedResult): void
    {
        $twig = new Environment(new ArrayLoader(['template' => $template]));
        $twig->addExtension(new AddLinksToUrlsExtension());

        self::assertSame($expectedResult, $twig->render('template'));
    }

    public static function urlGeneratorDataProvider(): \Generator
    {
        yield [
            '{{ "https://url.tld" | ' . AddLinksToUrlsExtension::PASTELL_ADD_LINKS_FILTER . ' | raw }}',
            '<a href="https://url.tld">https://url.tld</a>',
        ];
        yield [
            '{{ "test string http://www.url.tld" | ' . AddLinksToUrlsExtension::PASTELL_ADD_LINKS_FILTER . ' | raw }}',
            'test string <a href="http://www.url.tld">http://www.url.tld</a>',
        ];
        yield [
            '{{ "url.tld test" | ' . AddLinksToUrlsExtension::PASTELL_ADD_LINKS_FILTER . ' | raw }}',
            'url.tld test',
        ];
    }
}
