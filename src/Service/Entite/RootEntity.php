<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

final class RootEntity
{
    public const ID_E = 0;
    public const DENOMINATION = 'Entité racine';

    public static function addDenomination(array $entity_list): array
    {
        foreach ($entity_list as $key => $entity_info) {
            if (self::isRootEntity($entity_info)) {
                $entity_list[$key]['denomination'] = self::DENOMINATION;
            }
        }
        return $entity_list;
    }

    private static function isRootEntity(mixed $entity_info): bool
    {
        return is_array($entity_info)
            && isset($entity_info['id_e'])
            && $entity_info['id_e'] === self::ID_E;
    }
}
