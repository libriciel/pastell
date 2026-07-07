<?php

declare(strict_types=1);

namespace Pastell\Helpers;

use function is_array;

final class ArrayHelper
{
    /**
     * Builds a nested tree with `value`/`name`/`children` keys ready for the Treeselect JS component.
     */
    public static function buildTreeselectOptions(array $flatList): array
    {
        return self::renameKeysRecursive(
            self::buildNestedTree($flatList),
            ['id_e' => 'value', 'denomination' => 'name']
        );
    }

    private static function renameKeysRecursive(array $data, array $mapping): array
    {
        return array_map(static function (array $item) use ($mapping): array {
            $renamed = [];
            foreach ($item as $key => $value) {
                $newKey = $mapping[$key] ?? $key;
                $renamed[$newKey] = ($key === 'children' && \is_array($value))
                    ? self::renameKeysRecursive($value, $mapping)
                    : $value;
            }
            return $renamed;
        }, $data);
    }

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
