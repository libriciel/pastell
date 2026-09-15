<?php

class ConnecteurFrequenceSQL extends SQL
{
    public function edit(ConnecteurFrequence $connecteurFrequence)
    {

        $attribute_list = $connecteurFrequence->getArrayForSQL();
        $attribute_list = $this->cleanAttributeList($attribute_list);
        if ($connecteurFrequence->id_cf) {
            //$attribute_list = $connecteurFrequence->getArray();
            //unset($attribute_list['id_cf']);
            $sql_part = implode("=?,", array_keys($attribute_list)) . "=?";

            $attribute_list['id_cf'] = $connecteurFrequence->id_cf;

            $sql = "UPDATE connecteur_frequence SET $sql_part WHERE id_cf=?";
            $this->query(
                $sql,
                array_values($attribute_list)
            );
            return $connecteurFrequence->id_cf;
        } else {
            //$attribute_list = $connecteurFrequence->getArray();
            //unset($attribute_list['id_cf']);
            $sql_part1 = implode(",", array_keys($attribute_list));
            $sql = "INSERT INTO connecteur_frequence($sql_part1) VALUES ";
            $sql .= "(" . implode(",", array_fill(0, 9, "?")) . ")";
            $this->query(
                $sql,
                array_values($attribute_list)
            );
            return $this->lastInsertId();
        }
    }

    public function cleanAttributeList($attribute_list)
    {

        if ((!$attribute_list['type_connecteur']) || (!$attribute_list['famille_connecteur'])) {
            $attribute_list['famille_connecteur'] = '';
            $attribute_list['id_connecteur'] = '';
            $attribute_list['id_ce'] = '';
            $attribute_list['action_type'] = '';
            $attribute_list['type_document'] = '';
            $attribute_list['action'] = '';
        } else {
            if (!$attribute_list['id_connecteur']) {
                $attribute_list['id_ce'] = '';
            }
            if (!$attribute_list['action_type']) {
                $attribute_list['type_document'] = '';
                $attribute_list['action'] = '';
            }
            if ($attribute_list['action_type'] == 'connecteur') {
                $attribute_list['type_document'] = '';
            }
        }
        return $attribute_list;
    }

    public function getInfo($id_cf)
    {
        $sql = "SELECT connecteur_frequence.*,connecteur_entite.libelle, entite.denomination FROM connecteur_frequence " .
                " LEFT JOIN connecteur_entite ON connecteur_frequence.id_ce=connecteur_entite.id_ce" .
                " LEFT JOIN entite ON entite.id_e=connecteur_entite.id_e" .
                " WHERE id_cf = ?";
        return $this->queryOne($sql, $id_cf);
    }

    /**
     * @return ConnecteurFrequence[]
     */
    public function getAll()
    {
        $result = $this->getAllArray();
        foreach ($result as $i => $line) {
            $result[$i] = new ConnecteurFrequence($line);
        }
        return $result;
    }

    public function getAllArray()
    {
        $sql = "SELECT connecteur_frequence.*,connecteur_entite.libelle, entite.denomination FROM connecteur_frequence " .
            " LEFT JOIN connecteur_entite ON connecteur_frequence.id_ce=connecteur_entite.id_ce" .
            " LEFT JOIN entite ON entite.id_e=connecteur_entite.id_e" .
            " ORDER BY type_connecteur,famille_connecteur,connecteur_frequence.id_connecteur,connecteur_frequence.id_ce,action_type,type_document,action";
        return $this->query($sql);
    }

    /**
     * @return string[]
     */
    public function getDistinctVerrou(): array
    {
        $sql = "SELECT DISTINCT id_verrou FROM connecteur_frequence WHERE id_verrou != '' ORDER BY id_verrou";
        return array_column($this->query($sql), 'id_verrou');
    }

    public function getConnecteurFrequence($id_cf)
    {
        $info = $this->getInfo($id_cf);
        if (! $info || ! $info['id_cf']) {
            return null;
        }
        $connecteurFrequence = new ConnecteurFrequence($info);
        /*foreach($connecteurFrequence->getArray() as $key => $value){
            $connecteurFrequence->$key = $info[$key];
        }*/
        return $connecteurFrequence;
    }

    public function delete($id_cf)
    {
        $sql = "DELETE FROM connecteur_frequence WHERE id_cf =?";
        $this->query($sql, $id_cf);
    }

    public function deleteByIdCe(int $id_ce): void
    {
        $sql = <<<SQL
DELETE FROM connecteur_frequence WHERE id_ce = ?;
SQL;
        $this->query($sql, $id_ce);
    }

    public function deleteAll()
    {
        $sql = "DELETE FROM connecteur_frequence";
        $this->query($sql);
    }

    /*
     * Algorihtme :
     *
     * 1 - Recherche du connecteur :
     *      On cherche le premier connecteur qui répond à une liste de critère qu'on enleve progressivement par la fin
     *      (type_connecteur, famille_connecteur, id_connecteur, id_ce) au premier coup
     *      (type_connecteur, famille_connecteur, id_connecteur) au second coup
     *      ...
     *
     *      Dès qu'un ensemble de critère ramène au moins un connecteur, on s'arrete là
     *      (exemple : (type_connecteur, famille_connecteur)
     *
     * 2 - Recherche avec l'action :
     *      A partir de la liste précédemment obtenu, on ajoute les critères sur les actions
     *      On recherche alors le premier connecteur qui répond à la liste de critère suivant l'algo du 1
     *      (type_connecteur, famille_connecteur, type_action, document_type, action)
     *      (type_connecteur, famille_connecteur, type_action, document_type)
     *      ...
     *      En cas de remonté de plusieurs connecteur, c'est celui avec l'id_cf le plus petit qui est élu
     *
     */
    public function getNearestConnecteurFromConnecteur(ConnecteurFrequence $connecteurFrequence)
    {
        $id_cf = false;
        $criteria = [
            'type_connecteur' => $connecteurFrequence->type_connecteur,
            'famille_connecteur' => $connecteurFrequence->famille_connecteur,
            'id_connecteur' => $connecteurFrequence->id_connecteur,
            'id_ce' => $connecteurFrequence->id_ce
        ];
        $criteria_mask = [];

        foreach ($this->getCriteriaMaskList($criteria) as $criteria_mask) {
            $id_cf = $this->getConnecteurIdWithCriteria($criteria, $criteria_mask);
            if ($id_cf) {
                break;
            }
        }
        if (! $id_cf) {
            return null;
        }

        foreach ($criteria as $key => $value) {
            if (! in_array($key, $criteria_mask)) {
                $criteria[$key] = '';
            }
        }

        $criteria['action_type'] = $connecteurFrequence->action_type;
        $criteria['type_document'] = $connecteurFrequence->type_document;
        $criteria['action'] = $connecteurFrequence->action;

        foreach ($this->getCriteriaMaskList($criteria) as $criteria_mask) {
            $id_cf = $this->getConnecteurIdWithCriteria($criteria, $criteria_mask);
            if ($id_cf) {
                break;
            }
        }
        if (! $id_cf) {
            return null;
        }

        return $this->getConnecteurFrequence($id_cf);
    }

    private function getCriteriaMaskList($criteria)
    {
        $result = [];
        $keys = array_reverse(array_keys($criteria));
        $keys[] = '';
        foreach ($keys as $key) {
            $result[] = array_keys($criteria);
            unset($criteria[$key]);
        }
        return $result;
    }

    private function getConnecteurIdWithCriteria($criteria, $criteria_mask)
    {

        $sql_criteria = [];
        $param = [];
        foreach ($criteria as $key => $value) {
            $sql_criteria[] = " $key = ? ";
            if (in_array($key, $criteria_mask)) {
                $param[] = $value;
            } else {
                $param[] = '';
            }
        }
        $sql = "SELECT id_cf FROM connecteur_frequence WHERE " . implode(" AND ", $sql_criteria) . " ORDER BY id_cf";
        return $this->queryOne($sql, $param);
    }
}
