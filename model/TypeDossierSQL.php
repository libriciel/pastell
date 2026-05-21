<?php

class TypeDossierSQL extends SQL
{
    public function edit($id_t, TypeDossierProperties $typeDossierProperties)
    {
        if ($this->exists($id_t)) {
            $sql = "UPDATE type_dossier SET id_type_dossier=?,definition=? WHERE id_t=?";
            $this->query($sql, $typeDossierProperties->id_type_dossier, json_encode($typeDossierProperties), $id_t);
        } else {
            $sql = "INSERT INTO type_dossier(id_type_dossier,definition) VALUES (?,?)";
            $this->query($sql, $typeDossierProperties->id_type_dossier, json_encode($typeDossierProperties));
            $id_t = $this->lastInsertId();
        }
        return $id_t;
    }

    public function exists($id_t): bool
    {
        if ($id_t == 0) {
            return false;
        }
        $sql = "SELECT count(*) FROM type_dossier WHERE id_t=?";
        return boolval($this->queryOne($sql, $id_t));
    }

    public function getTypeDossierArray($id_t)
    {
        $sql = "SELECT definition FROM type_dossier WHERE id_t=?";
        return json_decode($this->queryOne($sql, $id_t), true);
    }

    public function getAll()
    {
        $sql = "SELECT * FROM type_dossier ORDER BY id_type_dossier";
        return $this->query($sql);
    }

    public function getInfo($id_t)
    {
        $sql = "SELECT * FROM type_dossier WHERE id_t=?";
        return $this->queryOne($sql, $id_t);
    }

    public function delete($id_t)
    {
        $sql = "DELETE FROM type_dossier WHERE id_t=?";
        $this->query($sql, $id_t);

        $sql = "DELETE FROM type_dossier_action WHERE id_t=?";
        $this->query($sql, $id_t);
    }

    public function getByIdTypeDossier($id_type_dossier)
    {
        $sql = "SELECT id_t FROM type_dossier WHERE id_type_dossier = ?";
        return $this->queryOne($sql, $id_type_dossier);
    }

    public function getNotFinished($flux): array
    {
        $sql = "SELECT document.id_d, id_e FROM document_entite " .
            " JOIN document ON document_entite.id_d=document.id_d " .
            " WHERE last_action NOT IN ('fatal-error', 'termine') " .
            " AND document.type = ? " ;
        return $this->query($sql, $flux);
    }

    public function getByIdT(int $id_t)
    {
        $sql = "SELECT id_type_dossier FROM type_dossier WHERE id_t = ?";
        return $this->queryOne($sql, $id_t);
    }
}
