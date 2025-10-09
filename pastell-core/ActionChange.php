<?php

class ActionChange extends SQL
{
    public function __construct(
        private readonly DocumentActionSQL $documentActionSQL,
        private readonly DocumentActionEntite $documentActionEntite,
        private readonly Journal $journal,
        private readonly SQLQuery $sqlQuery
    ) {
        parent::__construct($this->sqlQuery);
    }

    public function addAction($id_d, $id_e, $id_u, $action, $message_journal): void
    {
        $id_a = $this->documentActionSQL->add($id_d, $id_e, $id_u, $action);
        $id_j = $this->journal->addSQL(Journal::DOCUMENT_ACTION, $id_e, $id_u, $id_d, $action, $message_journal);
        $this->documentActionEntite->add($id_a, $id_e, $id_j);
    }

    /**
     * @deprecated 4.0.26 Use addOrUpdateAction instead
     */
    public function updateModification($id_d, $id_e, $id_u, $action)
    {
        $document_action = $this->documentActionSQL->getLastActionInfo($id_d, $id_e);

        if (! $document_action || $document_action['id_u'] != $id_u || $document_action['action'] != $action) {
            $this->addAction($id_d, $id_e, $id_u, $action, "Modification du document");
        } else {
            $this->documentActionSQL->updateDate($document_action['id_a']);
            $this->journal->addSQL(Journal::DOCUMENT_ACTION, $id_e, $id_u, $id_d, $action, "Modification du document");
        }
    }

    public function addOrUpdateAction(
        $id_d,
        $id_e,
        $id_u,
        string $action,
        string $message_journal
    ): void {
        $document_action = $this->documentActionSQL->getLastActionInfo($id_d, $id_e);

        if (! $document_action || $document_action['id_u'] !== $id_u || $document_action['action'] !== $action) {
            $this->addAction($id_d, $id_e, $id_u, $action, $message_journal);
        } else {
            $this->documentActionSQL->updateDate($document_action['id_a']);
            $this->journal->addSQL(
                Journal::DOCUMENT_ACTION,
                $id_e,
                $id_u,
                $id_d,
                $action,
                $message_journal
            );
        }
    }
}
