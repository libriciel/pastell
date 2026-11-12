<?php

declare(strict_types=1);

namespace Pastell\Service\Entite;

use EntiteSQL;
use Exception;
use NotFoundException;

final class EntityInfoService
{
    private const COLUMN_CENTRE_DE_GESTION = 'centre_de_gestion';
    private const COLUMN_SIREN = 'siren';
    private const COLUMN_IS_ACTIVE = 'is_active';
    private const COLUMN_TYPE = 'type';

    public function __construct(
        private readonly EntiteSQL $entiteSQL,
    ) {
    }

    public function getDenomination(int $id_e): string
    {
        if ($id_e === RootEntity::ID_E) {
            return RootEntity::DENOMINATION;
        }
        $entity_info = $this->entiteSQL->getInfo($id_e);
        return $entity_info ? $entity_info['denomination'] : '';
    }

    public function getCentreDeGestion(int $id_e): ?int
    {
        $cdg_id_e = $this->getInheritedValue($id_e, self::COLUMN_CENTRE_DE_GESTION);
        return $cdg_id_e === null ? null : (int)$cdg_id_e;
    }

    public function isActive(int $id_e): bool
    {
        if ($id_e === RootEntity::ID_E) {
            return true;
        }
        $entity_info = $this->entiteSQL->getInfo($id_e);
        return $entity_info && $entity_info[self::COLUMN_IS_ACTIVE];
    }

    public function isCentreDeGestion(int $id_e): bool
    {
        if ($id_e === RootEntity::ID_E) {
            return true;
        }
        $entity_info = $this->entiteSQL->getInfo($id_e);
        return $entity_info && $entity_info[self::COLUMN_TYPE] === EntityType::CENTRE_DE_GESTION->value;
    }

    public function getSiren(int $id_e): string
    {
        return (string)$this->getInheritedValue($id_e, self::COLUMN_SIREN);
    }

    /**
     * @throws NotFoundException
     */
    public function getExtendedInfo(int $id_e): array
    {
        $entity_info = $this->entiteSQL->getInfo($id_e);
        if (!$entity_info) {
            throw new NotFoundException("L'entité {id_e=$id_e} n'existe pas");
        }
        $cdg_id_e = $entity_info[self::COLUMN_CENTRE_DE_GESTION]
            ?: $this->getAncestorValue($id_e, self::COLUMN_CENTRE_DE_GESTION);
        $entity_info['cdg'] = [];
        if ($cdg_id_e) {
            $entity_info['cdg'] = $this->entiteSQL->getInfo($cdg_id_e);
        }
        if ($entity_info['entite_mere']) {
            $entity_info['entite_mere'] = $this->entiteSQL->getInfo($entity_info['entite_mere']);
        }
        $entity_info['filles'] = $this->entiteSQL->getFille($id_e);
        return $entity_info;
    }

    /**
     * @throws Exception
     */
    public function getEntityFromData(array $data): array
    {
        if (!empty($data['id_e'])) {
            $id_e = $data['id_e'];
            $entity_info = $this->entiteSQL->getInfo($id_e);
            if (!$entity_info) {
                throw new Exception("L'identifiant de l'entite n'existe pas : {id_e=$id_e}");
            }
            return $entity_info;
        }

        if (!empty($data['denomination'])) {
            $denomination = $data['denomination'];
            $nb_entity = (int)$this->entiteSQL->getNumberOfEntiteWithName($denomination);
            if ($nb_entity === 0) {
                throw new Exception("La dénomination de l'entité n'existe pas : {denomination=$denomination}");
            }
            if ($nb_entity > 1) {
                throw new Exception('Plusieurs entités portent le même nom, préférez utiliser son identifiant');
            }
            return $this->entiteSQL->getInfoByDenomination($denomination);
        }

        throw new Exception("Aucun paramètre permettant la recherche de l'entité n'a été renseigné");
    }

    private function getInheritedValue(int $id_e, string $column): mixed
    {
        $entity_info = $this->entiteSQL->getInfo($id_e);
        if (!empty($entity_info[$column])) {
            return $entity_info[$column];
        }
        return $this->getAncestorValue($id_e, $column);
    }

    private function getAncestorValue(int $id_e, string $column): mixed
    {
        foreach ($this->entiteSQL->getAncetre($id_e) as $ancestor_info) {
            if ($ancestor_info[$column]) {
                return $ancestor_info[$column];
            }
        }
        return null;
    }
}
