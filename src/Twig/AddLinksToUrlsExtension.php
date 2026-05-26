<?php

declare(strict_types=1);

namespace Pastell\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AddLinksToUrlsExtension extends AbstractExtension
{
    public const PASTELL_ADD_LINKS_FILTER = 'pastell_add_links_to_urls';

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                self::PASTELL_ADD_LINKS_FILTER,
                [$this, 'addLinks'],
                ['is_safe' => ['html'], 'pre_escape' => 'html'],
            ),
        ];
    }

    public function addLinks(string $text): string
    {
        return preg_replace('#(https?://\S+)#', '<a href="\1">\1</a>', $text);
    }
}
