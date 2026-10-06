<?php

use Pastell\Service\Journal\JournalEntryService;

/**
 * Class ActionCreator
 * @deprecated Use ActionCreatorSQL instead
 */
class ActionCreator extends SQL
{
    private $journal;
    private $id_d;

    private $lastAction;
    private $id_a;

    public function __construct(SQLQuery $sqlQuery, JournalEntryService $journal, $id_d)
    {
        parent::__construct($sqlQuery);
        $this->journal = $journal;
        $this->id_d = $id_d;
    }

    public function updateModification($id_e, $id_u, $action = false)
    {

        if (! $action) {
            $action = Action::MODIFICATION;
        }
        $sql = "SELECT * FROM document_action " .
                " WHERE id_d=? AND id_e=?" .
                " ORDER BY date DESC LIMIT 1";
        $document_action = $this->queryOne($sql, $this->id_d, $id_e);


        if (! $document_action || $document_action['id_u'] != $id_u || $document_action['action'] != $action) {
            return $this->addAction($id_e, $id_u, $action, "Modification du document");
        }

        $sql = "UPDATE document_action SET date=now() WHERE id_a=?";
        $this->query($sql, $document_action['id_a']);
        $this->journal->addSQL(JournalEventType::DOCUMENT_ACTION, $id_e, $id_u, $this->id_d, $action, "Modification du document");
    }

    public function addAction($id_e, $id_u, $action, $message_journal)
    {
        $now = date(Date::DATE_ISO);
        $this->lastAction = $action;

        $sql = "INSERT INTO document_action(id_d,date,action,id_e,id_u) VALUES (?,?,?,?,?)";
        $this->query($sql, $this->id_d, $now, $action, $id_e, $id_u);
        $this->id_a = $this->lastInsertId();

        $sql = "UPDATE document_entite SET last_action=? , last_action_date=? WHERE id_d=? AND id_e=?";
        $this->query($sql, $action, $now, $this->id_d, $id_e);

        $this->addToSQL($id_e, $id_u, $message_journal);
    }

    public function addToEntite($id_e, $message_journal)
    {
        $this->addToSQL($id_e, 0, $message_journal);
    }

    private function addToSQL($id_e, $id_u, $message_journal)
    {
        if (! $this->id_a) {
            throw new Exception("Problème lors de l'ajout dans le journal (id_a non présent)");
        }

        $id_j = $this->journal->addSQL(JournalEventType::DOCUMENT_ACTION, $id_e, $id_u, $this->id_d, $this->lastAction, $message_journal);

        $sql = "INSERT INTO document_action_entite (id_a,id_e,id_j) VALUES (?,?,?)";
        $this->query($sql, $this->id_a, $id_e, $id_j);
    }
}
