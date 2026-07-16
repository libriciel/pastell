<?php

declare(strict_types=1);

namespace Pastell\Step;

use DonneesFormulaire;

final class AnnexeList
{
    public const string MAPPING_KEY = 'autre_document_attache';

    public static function getElements(array $mapping): array
    {
        $elements = $mapping[self::MAPPING_KEY] ?? null;
        if (empty($elements)) {
            $elements = self::MAPPING_KEY;
        }
        return array_values(array_filter((array)$elements));
    }

    public static function getAll(array $elements, DonneesFormulaire $donneesFormulaire): array
    {
        $annexe_list = [];
        foreach ($elements as $element) {
            foreach ((array)($donneesFormulaire->get($element) ?: []) as $num => $filename) {
                $annexe_list[] = ['element' => $element, 'num' => (int)$num, 'filename' => (string)$filename];
            }
        }
        return $annexe_list;
    }

    public static function getFilenames(array $elements, DonneesFormulaire $donneesFormulaire): array
    {
        return array_column(self::getAll($elements, $donneesFormulaire), 'filename');
    }
}
