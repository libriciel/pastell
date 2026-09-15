<?php

declare(strict_types=1);

namespace Pastell\Helpers;

use function is_array;

final class ArrayHelper
{
    /**
     * Converts a flat list with a `profondeur` (depth) field into a nested tree with `children`.
     */
    public static function buildNestedTree(array $flatList): array
    {
        $hierarchy = [];

        foreach ($flatList as $entry) {
            $depth = $entry['profondeur'];

            if ($depth === 0) {
                $hierarchy[] = $entry;
            } else {
                $parent = &$hierarchy;
                for ($i = 0; $i < $depth; ++$i) {
                    $parent = &$parent[\count($parent) - 1]['children'];
                }
                $parent[] = $entry;
                unset($parent);
            }
        }

        return $hierarchy;
    }

    public static function getArrayKeysByDepth(array $array, int $depth = 0, int $currentLevel = 0): array
    {
        $arrayKeysByDepth = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($currentLevel === $depth) {
                    $arrayKeysByDepth[] = $key;
                } else {
                    $subKeys = self::getArrayKeysByDepth($value, $depth, $currentLevel + 1);
                    foreach ($subKeys as $subKey) {
                        $arrayKeysByDepth[] = $subKey;
                    }
                }
            }
        }
        return $arrayKeysByDepth;
    }
}
