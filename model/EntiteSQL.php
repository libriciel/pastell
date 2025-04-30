<?php

class EntiteSQL extends SQL
{
    public const TYPE_COLLECTIVITE = "collectivite";
    public const TYPE_CENTRE_DE_GESTION = "centre_de_gestion";

    public const ENTITE_RACINE_DENOMINATION = "Entité racine";
    public const ID_E_ENTITE_RACINE = 0;

    public function getInfo($id_e)
    {
        $sql = "SELECT * FROM entite WHERE id_e=?";
        return $this->queryOne($sql, $id_e);
    }

    public function exists($id_e)
    {
        return (bool) $this->getInfo($id_e);
    }

    public function getBySiren($siren)
    {
        $sql = "SELECT id_e FROM entite WHERE siren=?";
        return $this->queryOne($sql, $siren);
    }

    public function getIdByDenomination($denomination)
    {
        $sql = "SELECT id_e FROM entite WHERE denomination=?";
        return $this->queryOne($sql, $denomination);
    }

    public function getDenomination($id_e)
    {
        if ($id_e == 0) {
            return self::ENTITE_RACINE_DENOMINATION;
        }
        $info = $this->getInfo($id_e);
        if (! $info) {
            return "";
        }
        return $info['denomination'];
    }

    public function getEntiteMere($id_e)
    {
        $sql = "SELECT entite_mere FROM entite WHERE id_e=?";
        return $this->queryOne($sql, $id_e);
    }

    public function getAncetre($id_e)
    {
        $sql = "SELECT * FROM entite_ancetre " .
                " JOIN entite ON entite_ancetre.id_e_ancetre=entite.id_e " .
                " WHERE entite_ancetre.id_e=? ORDER BY niveau DESC";
        return $this->query($sql, $id_e);
    }

    public function getCDG($id_e)
    {
        return $this->getHeritedInfo($id_e, self::TYPE_CENTRE_DE_GESTION);
    }

    private function getHeritedInfo($id_e, $colname)
    {
        $info = $this->getInfo($id_e);
        if ($info[$colname]) {
            return $info[$colname];
        }

        $ancetre = $this->getAncetre($id_e);
        foreach ($ancetre as $id => $info) {
            if ($info[$colname]) {
                return $info[$colname];
            }
        }
        return false;
    }

    public function getExtendedInfo($id_e)
    {
        $result = $this->getInfo($id_e);
        $cdg_id_e = $this->getCDG($id_e);
        $result['cdg'] = [];
        if ($cdg_id_e) {
            $result['cdg'] = $this->getInfo($cdg_id_e) ;
        }
        if ($result['entite_mere']) {
            $result['entite_mere'] = $this->getInfo($result['entite_mere']) ;
        }
        $result['filles'] = $this->getFille($id_e);
        return $result;
    }

    public function getFille($id_e)
    {
        $sql = "SELECT * FROM entite WHERE entite_mere=? ORDER BY denomination";
        return $this->query($sql, $id_e);
    }

    public function getAllChildren(int $entityId)
    {
        $children = $this->getFille($entityId);
        foreach ($children as $child) {
            $newChildren = $this->getAllChildren($child['id_e']);
            $children = array_merge($children, $newChildren);
        }

        usort($children, static function ($a, $b) {
            return strcasecmp($a['denomination'], $b['denomination']);
        });

        return $children;
    }

    public function getFilleInfoNavigation($id_e, array $liste_collectivite = [])
    {
        if ($id_e != 0 || ! $liste_collectivite || ($liste_collectivite[0] == 0)) {
            return $this->getNavigationFilleWithType(
                $id_e,
                [self::TYPE_COLLECTIVITE,self::TYPE_CENTRE_DE_GESTION]
            );
        }
        $liste_fille = [];

        foreach ($liste_collectivite as $id_e_fille) {
            $info_fille = $this->getInfo($id_e_fille);
            if ($info_fille['is_active']) {
                $liste_fille[] = $info_fille;
            }
        }
        return $liste_fille;
    }


    public function getSiren($id_e)
    {
        return $this->getHeritedInfo($id_e, 'siren');
    }

    public function getAll()
    {
        $sql = "SELECT * FROM entite";
        return $this->query($sql);
    }


    public function getAncetreId($id_e)
    {
        $ancetre = $this->getAncetre($id_e);
        array_pop($ancetre);
        $result = [0];
        foreach ($ancetre as $entite) {
            $result[] = $entite['id_e'];
        }
        return $result;
    }

    private function getNavigationFilleWithType($id_e, array $type): array
    {
        foreach ($type as $i => $t) {
            $type[$i] = "'$t'";
        }
        $sql = "SELECT * FROM entite " .
                " WHERE entite_mere=? " .
                " AND type IN (" . implode(",", $type) . ")" .
                " AND is_active = 1 " .
                " ORDER BY denomination";

        return $this->query($sql, $id_e);
    }

    public function getAncetreNav($id_e, $listeCollectivite)
    {
        $all_ancetre = $this->getAncetre($id_e);

        array_pop($all_ancetre);

        if (in_array(0, $listeCollectivite)) {
            return $all_ancetre;
        }

        $allmere = [];
        foreach ($all_ancetre as $mere) {
            $allmere[] = $mere['id_e'];
        }
        foreach ($allmere as $mere_id_e) {
            if (! in_array($mere_id_e, $listeCollectivite)) {
                array_shift($all_ancetre);
            } else {
                return $all_ancetre;
            }
        }
        return $all_ancetre;
    }

        // ajout de la methode pour la suppression des entites par API.
        // Les controles avant suppression sont à completer dans la methode appelante.
    /**
     * @param $id_e
     * @throws UnrecoverableException
     */
    public function removeEntite($id_e)
    {

        // L'entite possède-t-elle des filles
        $entiteFille = $this->getFille($id_e);
        if ($entiteFille) {
            throw new UnrecoverableException("Suppression impossible : l'entité {id_e=$id_e} possède des entités filles");
        }

        // Des documents sont-ils définis sur l'entité
        $sql = "SELECT id_e FROM document_entite where id_e=?";
        $documentSurEntite = $this->queryOne($sql, $id_e);
        if ($documentSurEntite) {
            throw new UnrecoverableException("Suppression impossible : des documents sont définis sur l'entité {id_e=$id_e}");
        }

        // Des utilisateurs sont-ils définis sur l'entité
        $sql = "SELECT id_e FROM utilisateur where id_e=?";
        $utilisateurSurEntite = $this->queryOne($sql, $id_e);
        if ($utilisateurSurEntite) {
            throw new UnrecoverableException("Suppression impossible : des utilisateurs sont définis sur l'entité {id_e=$id_e}");
        }

        // Des connecteurs sont-ils définis sur l'entité
        $sql = "SELECT id_e FROM connecteur_entite where id_e=?";
        $connecteurSurEntite = $this->queryOne($sql, $id_e);
        if ($connecteurSurEntite) {
            throw new UnrecoverableException("Suppression impossible : des connecteurs sont définis sur l'entité {id_e=$id_e}");
        }

        $this->deleteEntite($id_e);
    }

    private function deleteEntite($id_e)
    {
        // Suppression de l'ancetre entité
        $sql = "DELETE FROM entite_ancetre where id_e=?";
        $this->query($sql, $id_e);
        // Suppression de l'entité
        $sql = "DELETE FROM entite WHERE id_e=?";
        $this->query($sql, $id_e);
    }

    public function delete($id_e)
    {
        $this->deleteEntite($id_e);
    }

    public function updateEntiteMere($id_e, $id_entite_mere)
    {
        $sql = "UPDATE entite SET entite_mere = ? WHERE id_e = ?";
        return $this->query($sql, $id_entite_mere, $id_e);
    }

    public function setActive($id_e, $active)
    {
        $sql = "UPDATE entite SET is_active=? WHERE id_e=?";
        $this->query($sql, $active, $id_e);
    }

    public function getInfoByDenomination($denomination)
    {
        $sql = "SELECT * FROM entite WHERE denomination=?";
        return $this->queryOne($sql, $denomination);
    }

    public function getNumberOfEntiteWithName($denomination)
    {
        $sql = "SELECT count(*) FROM entite WHERE denomination=?";
        return $this->queryOne($sql, $denomination);
    }


    /**
     * @param array $data Données en vrac dans un tableau contenant ou un id_e ou une dénomination ou un is_active
     * @return mixed Toutes les informations de la ou les entités trouvées
     * @throws Exception
     */
    public function getEntiteFromData(array $data)
    {

        if (! empty($data['id_e'])) {
            $id_e = $data['id_e'];
            $infoEntiteExistante = $this->getInfo($id_e);
            if (! $infoEntiteExistante) {
                throw new Exception("L'identifiant de l'entite n'existe pas : {id_e=$id_e}");
            }
            return $infoEntiteExistante;
        }

        if (! empty($data['denomination'])) {
            $denomination = $data['denomination'];

            $numberOfEntite = $this->getNumberOfEntiteWithName($denomination);

            if ($numberOfEntite == 0) {
                throw new Exception("La dénomination de l'entité n'existe pas : {denomination=$denomination}");
            }

            if ($numberOfEntite > 1) {
                throw new Exception("Plusieurs entités portent le même nom, préférez utiliser son identifiant");
            }

            return $this->getInfoByDenomination($denomination);
        }

        if (isset($data['is_active'])) {
            return $this->getByIsActive($data['is_active']);
        }

        throw new Exception("Aucun paramètre permettant la recherche de l'entité n'a été renseigné");
    }

    public static function getAllType()
    {
        return [
            self::TYPE_COLLECTIVITE => "Collectivité",
            self::TYPE_CENTRE_DE_GESTION => "Centre de gestion"
        ];
    }

    public static function getNom($type)
    {
        $type_nom = self::getAllType();
        if (empty($type_nom[$type])) {
            return $type;
        }
        return $type_nom[$type];
    }

    private function getByIsActive(bool $is_active): array
    {
        $sql = "SELECT * FROM entite WHERE is_active = ?";
        return $this->query($sql, $is_active);
    }

    public function create(
        string $name,
        string $siren = '',
        string $type = self::TYPE_COLLECTIVITE,
        int $mere = 0,
        int $cdg = 0,
    ): int {
        $date = \date(\Date::DATE_ISO);
        $query = <<<EOT
INSERT INTO entite(denomination,siren,type,entite_mere,date_inscription,centre_de_gestion)
VALUES (?,?,?,?,?,?);
EOT;

        $this->query($query, $name, $siren, $type, $mere, $date, $cdg);
        return (int)$this->lastInsertId();
    }

    public function update(
        int $entityId,
        string $name,
        string $siren = '',
        string $type = self::TYPE_COLLECTIVITE,
        int $mere = 0,
        int $cdg = 0,
    ): void {
        $query = <<<EOT
UPDATE entite
SET denomination=?, siren=?, type=?, entite_mere=?, centre_de_gestion=?
WHERE id_e=?;
EOT;
        $this->query($query, $name, $siren, $type, $mere, $cdg, $entityId);
    }

    public function updateAncestor(int $entityId, int $ancestor = 0): void
    {
        $deleteQuery = 'DELETE FROM entite_ancetre WHERE id_e=?;';
        $this->query($deleteQuery, $entityId);
        $insertQuery = 'INSERT INTO entite_ancetre(id_e_ancetre,id_e,niveau) VALUES (?,?,?);';
        $selectQuery = 'SELECT entite_mere FROM entite WHERE id_e=?;';
        $niveau = 0;
        $this->query($insertQuery, $entityId, $entityId, $niveau++);
        while ($ancestor !== 0) {
            $this->query($insertQuery, $ancestor, $entityId, $niveau++);
            $ancestor = (int)$this->queryOne($selectQuery, $ancestor);
        }
        $this->query($insertQuery, 0, $entityId, $niveau);
    }

    public function updateAllAncestors(): void
    {
        $sql = 'DELETE FROM entite_ancetre;';
        $this->query($sql);

        $sql = 'INSERT INTO entite_ancetre(id_e_ancetre,id_e,niveau) VALUES (0,0,0);';
        $this->query($sql);

        $sql = 'SELECT entite_mere,id_e FROM entite;';
        $allEntite = $this->query($sql);
        foreach ($allEntite as $entite) {
            $this->updateAncestor($entite['id_e'], $entite['entite_mere']);
        }
    }

    public function isActive(int $entityId): bool
    {
        if ($entityId === 0) {
            return true;
        }

        $info = $this->getInfo($entityId);
        if ($info === false) {
            return false;
        }

        return (bool)$info['is_active'];
    }

    public function isCDG(int $entityId): bool
    {
        if ($entityId === 0) {
            return true;
        }

        $info = $this->getInfo($entityId);
        if ($info === false) {
            return false;
        }

        return $info['type'] === self::TYPE_CENTRE_DE_GESTION;
    }

    public function getTreeEntity(?int $id_eMere): array
    {
        $listeEntiteMere = [];
        $i = 0;
        while ($id_eMere !== 0) {
            $sql = 'SELECT denomination, entite_mere FROM entite WHERE id_e = ?';
            $mere = $this->query($sql, $id_eMere);
            if (isset($mere[0])) {
                $listeEntiteMere[] = $mere[0]['denomination'];
                $id_eMere = $mere[0]['entite_mere'];
                $i++;
            } else {
                $id_eMere = 0;
            }
        }
        $listeEntiteMere[$i] = 'Entité racine';
        return array_reverse($listeEntiteMere);
    }
}
