<?php

use Pastell\Service\Entite\EntityType;

class EntiteSQL extends SQL
{
    public const STATE_ACTIVE = 1;

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

    public function getFille(int $id_e): array
    {
        $sql = <<<SQL
SELECT * FROM entite WHERE entite_mere = ? ORDER BY denomination
SQL;
        return $this->query($sql, $id_e);
    }

    public function getAll(): array
    {
        $sql = <<<SQL
SELECT * FROM entite
SQL;
        return $this->query($sql);
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

    public function create(
        string $name,
        string $siren = '',
        ?string $type = null,
        int $mere = 0,
        int $cdg = 0,
    ): int {
        $type ??= EntityType::COLLECTIVITE->value;
        $sql = <<<SQL
INSERT INTO entite(denomination, siren, type, entite_mere, date_inscription, centre_de_gestion)
VALUES (?, ?, ?, ?, ?, ?)
SQL;
        $this->query($sql, $name, $siren, $type, $mere, $this->getNow(), $cdg);
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

    public function getSiblingsWithPermission(int $id_e, int $id_u): array
    {
        $id_e_parent = $this->getEntiteMere($id_e);
        $sql = <<<SQL
SELECT DISTINCT entite.* FROM entite
JOIN entite_ancetre ON entite.id_e=entite_ancetre.id_e
JOIN utilisateur_role ON entite_ancetre.id_e_ancetre=utilisateur_role.id_e
JOIN utilisateur ON utilisateur_role.id_u = utilisateur.id_u
WHERE entite.entite_mere=? AND utilisateur.id_u=? AND utilisateur_role.role NOT LIKE 'aucun droit'
AND (entite.is_active=1 OR entite.id_e = ?)
ORDER BY denomination
SQL;
        return $this->query($sql, [$id_e_parent, $id_u, $id_e]);
    }
}
