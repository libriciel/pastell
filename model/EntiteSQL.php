<?php

use Pastell\Service\Entite\EntityType;
use Pastell\Service\Entite\RootEntity;

class EntiteSQL extends SQL
{
    /**
     * @deprecated 4.1.26 Use EntityType::COLLECTIVITE instead
     */
    public const TYPE_COLLECTIVITE = 'collectivite';
    /**
     * @deprecated 4.1.26 Use EntityType::CENTRE_DE_GESTION instead
     */
    public const TYPE_CENTRE_DE_GESTION = 'centre_de_gestion';

    /**
     * @deprecated 4.1.26 Use RootEntity::DENOMINATION instead
     */
    public const ENTITE_RACINE_DENOMINATION = RootEntity::DENOMINATION;
    /**
     * @deprecated 4.1.26 Use RootEntity::ID_E instead
     */
    public const ID_E_ENTITE_RACINE = RootEntity::ID_E;

    public const NB_COLLECTIVITE_DISPLAY = 20;

    /**
     * Untyped parameter: legacy callers may pass null or a non-numeric string coming from the request.
     */
    public function getInfo($id_e): array|false
    {
        $sql = <<<SQL
SELECT * FROM entite WHERE id_e = ?
SQL;
        return $this->queryOne($sql, $id_e);
    }

    public function exists(int $id_e): bool
    {
        return (bool)$this->getInfo($id_e);
    }

    public function getBySiren(string $siren): int|false
    {
        $sql = <<<SQL
SELECT id_e FROM entite WHERE siren = ?
SQL;
        return $this->queryOne($sql, $siren);
    }

    public function getIdByDenomination(string $denomination): int|false
    {
        $sql = <<<SQL
SELECT id_e FROM entite WHERE denomination = ?
SQL;
        return $this->queryOne($sql, $denomination);
    }

    /**
     * @deprecated 4.1.26 Use EntityInfoService::getDenomination() instead
     */
    public function getDenomination(int $id_e): string
    {
        if ($id_e === RootEntity::ID_E) {
            return RootEntity::DENOMINATION;
        }
        $entity_info = $this->getInfo($id_e);
        if (!$entity_info) {
            return '';
        }
        return $entity_info['denomination'];
    }

    public function getEntiteMere(int $id_e): int|false
    {
        $sql = <<<SQL
SELECT entite_mere FROM entite WHERE id_e = ?
SQL;
        $entite_mere = $this->queryOne($sql, $id_e);
        return $entite_mere === false ? false : (int)$entite_mere;
    }

    public function getAncetre(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM entite_ancetre
JOIN entite ON entite_ancetre.id_e_ancetre = entite.id_e
WHERE entite_ancetre.id_e = ?
ORDER BY niveau DESC
SQL;
        return $this->query($sql, $id_e);
    }

    /**
     * @deprecated 4.1.26 Use EntityInfoService::getCentreDeGestion() instead
     */
    public function getCDG(int $id_e): mixed
    {
        return $this->getInheritedInfo($id_e, 'centre_de_gestion');
    }

    private function getInheritedInfo(int $id_e, string $column): mixed
    {
        $entity_info = $this->getInfo($id_e);
        if ($entity_info[$column]) {
            return $entity_info[$column];
        }

        foreach ($this->getAncetre($id_e) as $ancestor_info) {
            if ($ancestor_info[$column]) {
                return $ancestor_info[$column];
            }
        }
        return false;
    }

    /**
     * @deprecated 4.1.26 Use EntityInfoService::getExtendedInfo() instead
     */
    public function getExtendedInfo(int $id_e): array
    {
        $entity_info = $this->getInfo($id_e);
        $cdg_id_e = $this->getCDG($id_e);
        $entity_info['cdg'] = [];
        if ($cdg_id_e) {
            $entity_info['cdg'] = $this->getInfo($cdg_id_e);
        }
        if ($entity_info['entite_mere']) {
            $entity_info['entite_mere'] = $this->getInfo($entity_info['entite_mere']);
        }
        $entity_info['filles'] = $this->getFille($id_e);
        return $entity_info;
    }

    public function getFille(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM entite WHERE entite_mere = ? ORDER BY denomination
SQL;
        return $this->query($sql, $id_e);
    }

    /**
     * @deprecated 4.1.26 Use EntityHierarchyService::getAllChildren() instead
     */
    public function getAllChildren(int $entityId): array
    {
        $children = $this->getFille($entityId);
        foreach ($children as $child) {
            $children = array_merge($children, $this->getAllChildren($child['id_e']));
        }

        usort($children, static function (array $a, array $b): int {
            return strcasecmp($a['denomination'], $b['denomination']);
        });

        return $children;
    }

    /**
     * @deprecated 4.1.26 Use EntityHierarchyService::getNavigationChildren() instead
     */
    public function getFilleInfoNavigation(int $id_e, array $liste_collectivite = []): array
    {
        if (
            $id_e !== RootEntity::ID_E
            || !$liste_collectivite
            || $liste_collectivite[0] == RootEntity::ID_E
        ) {
            return $this->getActiveChildrenByType(
                $id_e,
                [EntityType::COLLECTIVITE->value, EntityType::CENTRE_DE_GESTION->value]
            );
        }

        $children = [];
        foreach ($liste_collectivite as $child_id_e) {
            $child_info = $this->getInfo($child_id_e);
            if ($child_info['is_active']) {
                $children[] = $child_info;
            }
        }
        return $children;
    }

    /**
     * @deprecated 4.1.26 Use EntityInfoService::getSiren() instead
     */
    public function getSiren(int $id_e): mixed
    {
        return $this->getInheritedInfo($id_e, 'siren');
    }

    public function getAll(): array
    {
        $sql = <<<SQL
SELECT * FROM entite
SQL;
        return $this->query($sql);
    }

    /**
     * @deprecated 4.1.26 Use EntityHierarchyService::getAncestorIds() instead
     */
    public function getAncetreId(int $id_e): array
    {
        $ancestor_list = $this->getAncetre($id_e);
        array_pop($ancestor_list);
        $ancestor_id_list = [RootEntity::ID_E];
        foreach ($ancestor_list as $ancestor_info) {
            $ancestor_id_list[] = $ancestor_info['id_e'];
        }
        return $ancestor_id_list;
    }

    /**
     * @deprecated 4.1.26 Use EntityHierarchyService::getNavigationAncestors() instead
     */
    public function getAncetreNav(int $id_e, array $liste_collectivite): array
    {
        $ancestor_list = $this->getAncetre($id_e);
        array_pop($ancestor_list);

        if (in_array(RootEntity::ID_E, $liste_collectivite)) {
            return $ancestor_list;
        }

        $ancestor_id_list = array_column($ancestor_list, 'id_e');
        foreach ($ancestor_id_list as $ancestor_id_e) {
            if (in_array($ancestor_id_e, $liste_collectivite)) {
                return $ancestor_list;
            }
            array_shift($ancestor_list);
        }
        return $ancestor_list;
    }

    /**
     * @deprecated 4.1.26 Use EntiteDeletionService::deleteEmptyEntity() instead
     * @throws UnrecoverableException
     */
    public function removeEntite(int $id_e): void
    {
        if ($this->getFille($id_e)) {
            throw new UnrecoverableException(
                "Suppression impossible : l'entité {id_e=$id_e} possède des entités filles"
            );
        }

        $sql = <<<SQL
SELECT id_e FROM document_entite WHERE id_e = ?
SQL;
        if ($this->queryOne($sql, $id_e)) {
            throw new UnrecoverableException(
                "Suppression impossible : des documents sont définis sur l'entité {id_e=$id_e}"
            );
        }

        $sql = <<<SQL
SELECT id_e FROM utilisateur WHERE id_e = ?
SQL;
        if ($this->queryOne($sql, $id_e)) {
            throw new UnrecoverableException(
                "Suppression impossible : des utilisateurs sont définis sur l'entité {id_e=$id_e}"
            );
        }

        $sql = <<<SQL
SELECT id_e FROM connecteur_entite WHERE id_e = ?
SQL;
        if ($this->queryOne($sql, $id_e)) {
            throw new UnrecoverableException(
                "Suppression impossible : des connecteurs sont définis sur l'entité {id_e=$id_e}"
            );
        }

        $this->delete($id_e);
    }

    public function delete(int $id_e): void
    {
        $this->deleteAncestors($id_e);
        $sql = <<<SQL
DELETE FROM entite WHERE id_e = ?
SQL;
        $this->query($sql, $id_e);
    }

    public function updateEntiteMere(int $id_e, int $id_entite_mere): void
    {
        $sql = <<<SQL
UPDATE entite SET entite_mere = ? WHERE id_e = ?
SQL;
        $this->query($sql, $id_entite_mere, $id_e);
    }

    public function setActive(int $id_e, bool $active): void
    {
        $sql = <<<SQL
UPDATE entite SET is_active = ? WHERE id_e = ?
SQL;
        $this->query($sql, (int)$active, $id_e);
    }

    public function getInfoByDenomination(string $denomination): array|false
    {
        $sql = <<<SQL
SELECT * FROM entite WHERE denomination = ?
SQL;
        return $this->queryOne($sql, $denomination);
    }

    public function getNumberOfEntiteWithName(string $denomination): int
    {
        $sql = <<<SQL
SELECT count(*) FROM entite WHERE denomination = ?
SQL;
        return (int)$this->queryOne($sql, $denomination);
    }

    /**
     * @deprecated 4.1.26 Use EntityInfoService::getEntityFromData() instead
     * @throws Exception
     */
    public function getEntiteFromData(array $data): array
    {
        if (!empty($data['id_e'])) {
            $id_e = $data['id_e'];
            $entity_info = $this->getInfo($id_e);
            if (!$entity_info) {
                throw new Exception("L'identifiant de l'entite n'existe pas : {id_e=$id_e}");
            }
            return $entity_info;
        }

        if (!empty($data['denomination'])) {
            $denomination = $data['denomination'];
            $nb_entity = $this->getNumberOfEntiteWithName($denomination);
            if ($nb_entity === 0) {
                throw new Exception("La dénomination de l'entité n'existe pas : {denomination=$denomination}");
            }
            if ($nb_entity > 1) {
                throw new Exception('Plusieurs entités portent le même nom, préférez utiliser son identifiant');
            }
            return $this->getInfoByDenomination($denomination);
        }

        if (isset($data['is_active'])) {
            return $this->getByIsActive((bool)$data['is_active']);
        }

        throw new Exception("Aucun paramètre permettant la recherche de l'entité n'a été renseigné");
    }

    /**
     * @deprecated 4.1.26 Use EntityType instead
     */
    public static function getAllType(): array
    {
        return [
            EntityType::COLLECTIVITE->value => EntityType::COLLECTIVITE->label(),
            EntityType::CENTRE_DE_GESTION->value => EntityType::CENTRE_DE_GESTION->label(),
        ];
    }

    /**
     * @deprecated 4.1.26 Use EntityType::getLabel() instead
     */
    public static function getNom(string $type): string
    {
        return EntityType::getLabel($type);
    }

    private function getByIsActive(bool $is_active): array
    {
        $sql = <<<SQL
SELECT * FROM entite WHERE is_active = ?
SQL;
        return $this->query($sql, (int)$is_active);
    }

    public function create(
        string $name,
        string $siren = '',
        ?string $type = null,
        int $mere = 0,
        int $cdg = 0,
    ): int {
        $type ??= EntityType::COLLECTIVITE->value;
        $date = \date(\Date::DATE_ISO);
        $sql = <<<SQL
INSERT INTO entite(denomination, siren, type, entite_mere, date_inscription, centre_de_gestion)
VALUES (?, ?, ?, ?, ?, ?)
SQL;
        $this->query($sql, $name, $siren, $type, $mere, $date, $cdg);
        return (int)$this->lastInsertId();
    }

    public function update(
        int $entityId,
        string $name,
        string $siren = '',
        ?string $type = null,
        int $mere = 0,
        int $cdg = 0,
    ): void {
        $type ??= EntityType::COLLECTIVITE->value;
        $sql = <<<SQL
UPDATE entite
SET denomination = ?, siren = ?, type = ?, entite_mere = ?, centre_de_gestion = ?
WHERE id_e = ?
SQL;
        $this->query($sql, $name, $siren, $type, $mere, $cdg, $entityId);
    }

    /**
     * @deprecated 4.1.26 Use EntityHierarchyService::updateAncestors() instead
     */
    public function updateAncestor(int $entityId, int $ancestor = 0): void
    {
        $this->deleteAncestors($entityId);
        $level = 0;
        $this->insertAncestor($entityId, $entityId, $level++);
        while ($ancestor !== RootEntity::ID_E) {
            $this->insertAncestor($ancestor, $entityId, $level++);
            $ancestor = (int)$this->getEntiteMere($ancestor);
        }
        $this->insertAncestor(RootEntity::ID_E, $entityId, $level);
    }

    /**
     * @deprecated 4.1.26 Use EntityHierarchyService::updateAllAncestors() instead
     */
    public function updateAllAncestors(): void
    {
        $this->deleteAllAncestors();
        $this->insertAncestor(RootEntity::ID_E, RootEntity::ID_E, 0);

        $sql = <<<SQL
SELECT entite_mere, id_e FROM entite
SQL;
        foreach ($this->query($sql) as $entity_info) {
            $this->updateAncestor($entity_info['id_e'], $entity_info['entite_mere']);
        }
    }

    /**
     * @deprecated 4.1.26 Use EntityInfoService::isActive() instead
     */
    public function isActive(int $entityId): bool
    {
        if ($entityId === RootEntity::ID_E) {
            return true;
        }

        $entity_info = $this->getInfo($entityId);
        if ($entity_info === false) {
            return false;
        }

        return (bool)$entity_info['is_active'];
    }

    /**
     * @deprecated 4.1.26 Use EntityInfoService::isCentreDeGestion() instead
     */
    public function isCDG(int $entityId): bool
    {
        if ($entityId === RootEntity::ID_E) {
            return true;
        }

        $entity_info = $this->getInfo($entityId);
        if ($entity_info === false) {
            return false;
        }

        return $entity_info['type'] === EntityType::CENTRE_DE_GESTION->value;
    }

    /**
     * @deprecated 4.1.26 Use EntityHierarchyService::getAncestorDenominations() instead
     */
    public function getTreeEntity(?int $id_eMere): array
    {
        $sql = <<<SQL
SELECT denomination, entite_mere FROM entite WHERE id_e = ?
SQL;
        $denomination_list = [];
        while ($id_eMere !== RootEntity::ID_E) {
            $parent_info = $this->queryOne($sql, $id_eMere);
            if (!$parent_info) {
                break;
            }
            $denomination_list[] = $parent_info['denomination'];
            $id_eMere = $parent_info['entite_mere'];
        }
        $denomination_list[] = RootEntity::DENOMINATION;
        return array_reverse($denomination_list);
    }

    public function getAllByType(string $type): array
    {
        $sql = <<<SQL
SELECT * FROM entite WHERE type = ? ORDER BY denomination
SQL;
        return $this->query($sql, $type);
    }

    public function getAllCollectivite(int $offset, string $search, int $limit = self::NB_COLLECTIVITE_DISPLAY): array
    {
        $offset = max(0, $offset);
        // LIMIT cannot be bound: emulated prepared statements would quote the values.
        $sql = <<<SQL
SELECT id_e, denomination, siren, type, is_active FROM entite
WHERE entite_mere = 0 AND type != 'fournisseur' AND type != 'citoyen' AND denomination LIKE ?
ORDER BY is_active DESC, denomination
LIMIT $offset, $limit
SQL;
        return $this->query($sql, "%$search%");
    }

    public function getNbCollectivite(string $search): int
    {
        $sql = <<<SQL
SELECT count(*) FROM entite
WHERE entite_mere = 0 AND type != 'fournisseur' AND type != 'citoyen' AND denomination LIKE ?
SQL;
        return (int)$this->queryOne($sql, "%$search%");
    }

    public function getActiveChildrenByType(int $id_e, array $type_list): array
    {
        if (!$type_list) {
            return [];
        }
        $placeholders = $this->getPlaceholders($type_list);
        $sql = <<<SQL
SELECT * FROM entite
WHERE entite_mere = ? AND type IN ($placeholders) AND is_active = 1
ORDER BY denomination
SQL;
        return $this->query($sql, [$id_e, ...$type_list]);
    }

    public function deleteAncestors(int $id_e): void
    {
        $sql = <<<SQL
DELETE FROM entite_ancetre WHERE id_e = ?
SQL;
        $this->query($sql, $id_e);
    }

    public function deleteAllAncestors(): void
    {
        $sql = <<<SQL
DELETE FROM entite_ancetre
SQL;
        $this->query($sql);
    }

    public function insertAncestor(int $ancestor_id_e, int $id_e, int $level): void
    {
        $sql = <<<SQL
INSERT INTO entite_ancetre(id_e_ancetre, id_e, niveau) VALUES (?, ?, ?)
SQL;
        $this->query($sql, $ancestor_id_e, $id_e, $level);
    }
}
