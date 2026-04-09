<?php

class DocumentIndexSQL extends SQL
{
    public const FIELD_NAME_LENGTH = 64;
    public const FIELD_VALUE_LENGTH = 128;

    public function index($id_d, $field_name, $field_value)
    {
        $field_name = substr($field_name, 0, self::FIELD_NAME_LENGTH);

        $sql = "SELECT count(*) FROM document_index WHERE id_d=? AND field_name=?";
        if ($this->queryOne($sql, $id_d, $field_name)) {
            $sql = "UPDATE document_index SET field_value = ? WHERE id_d=? AND field_name = ?";
            $this->query($sql, $field_value, $id_d, $field_name);
        } else {
            $sql = "INSERT INTO document_index(id_d,field_name,field_value) VALUES(?,?,?)";
            $this->query($sql, $id_d, $field_name, $field_value);
        }
    }

    public function get($id_d, $field_name)
    {
        $field_name = $this->fieldNameSubstring($field_name);
        $sql = "SELECT field_value FROM document_index WHERE id_d=? AND field_name=?";
        return $this->queryOne($sql, $id_d, $field_name);
    }

    public function getByFieldValue($field_name, $field_value)
    {
        $field_name = $this->fieldNameSubstring($field_name);
        $field_value = $this->fieldValueSubstring($field_value);
        $sql = "SELECT id_d FROM document_index WHERE field_name=? AND field_value=?";
        return $this->queryOne($sql, $field_name, $field_value);
    }

    public function getByFieldValueAndType(string $field_name, string $field_value, string $type): mixed
    {
        $field_name = $this->fieldNameSubstring($field_name);
        $field_value = $this->fieldValueSubstring($field_value);
        $sql = <<<SQL
SELECT document_index.id_d FROM document_index
JOIN document ON document_index.id_d = document.id_d
WHERE document_index.field_name = ? AND document_index.field_value = ? AND document.type = ?
SQL;
        return $this->queryOne($sql, $field_name, $field_value, $type);
    }

    private function fieldNameSubstring($field_name)
    {
        return substr($field_name, 0, self::FIELD_NAME_LENGTH);
    }

    private function fieldValueSubstring($field_value)
    {
        return substr($field_value, 0, self::FIELD_VALUE_LENGTH);
    }

    public function getAll($id_d)
    {
        $result = [];
        $sql = "SELECT field_name,field_value FROM document_index WHERE id_d=?";
        foreach ($this->query($sql, $id_d) as $line) {
            $result[$line['field_name']] = $line['field_value'];
        }
        return $result;
    }
}
