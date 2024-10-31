<?php

declare(strict_types=1);

namespace Pastell\Helpers;

use function is_array;

final class ArrayHelper
{
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
