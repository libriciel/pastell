<?php

declare(strict_types=1);

namespace Pastell\Helpers;

use function is_array;

final class ArrayHelper
{
    public static function getArrayKeysByDeph(array $array, int $deph = 0, int $currentLevel = 0): array
    {
        $arrayKeysByDeph = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($currentLevel === $deph) {
                    $arrayKeysByDeph[] = $key;
                } else {
                    $subKeys = self::getArrayKeysByDeph($value, $deph, $currentLevel + 1);
                    foreach ($subKeys as $subKey) {
                        $arrayKeysByDeph[] = $subKey;
                    }
                }
            }
        }
        return $arrayKeysByDeph;
    }
}
