<?php

class ActionCreatorSQL extends SQL
{
    private string $lastAction;
    private int $id_a;

    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly Journal $journal,
    ) {
        parent::__construct($this->sqlQuery);
    }

    /**
     * @throws Exception
     */
    public function addAction($id_e, $id_u, $action, $message_journal, $id_d): void
    {
        $now = date(Date::DATE_ISO);
        $this->lastAction = $action;

        $sql = <<<SQL
INSERT INTO document_action(id_d, date, action, id_e, id_u)
VALUES (?, ?, ?, ?, ?);
SQL;
        $this->query($sql, $id_d, $now, $action, $id_e, $id_u);
        $this->id_a = $this->lastInsertId();

        $sql = <<<SQL
UPDATE document_entite
SET last_action = ? , last_action_date = ?
WHERE id_d = ? AND id_e = ?;
SQL;
        $this->query($sql, $action, $now, $id_d, $id_e);

        $this->addToSQL($id_e, $id_u, $message_journal, $id_d);
    }

    /**
     * @throws Exception
     */
    private function addToSQL($id_e, $id_u, $message_journal, $id_d): void
    {
        if (! $this->id_a) {
            throw new Exception("Problème lors de l'ajout dans le journal (id_a non présent)");
        }

        $id_j = $this->journal->addSQL(
            Journal::DOCUMENT_ACTION,
            $id_e,
            $id_u,
            $id_d,
            $this->lastAction,
            $message_journal
        );

        $sql = <<<SQL
INSERT INTO document_action_entite (id_a, id_e, id_j)
VALUES (?, ?, ?);
SQL;
        $this->query($sql, $this->id_a, $id_e, $id_j);
    }
}
