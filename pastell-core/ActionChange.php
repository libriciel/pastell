<?php

declare(strict_types=1);

final readonly class ActionChange
{
    public function __construct(
        private DocumentActionSQL $documentActionSQL,
        private DocumentActionEntite $documentActionEntite,
        private Journal $journal,
    ) {
    }

    public function addAction($id_d, $id_e, $id_u, string $action, string $message_journal): void
    {
        $id_a = $this->documentActionSQL->add($id_d, $id_e, $id_u, $action);
        $id_j = $this->journal->addSQL(Journal::DOCUMENT_ACTION, $id_e, $id_u, $id_d, $action, $message_journal);
        $this->documentActionEntite->add($id_a, $id_e, $id_j);
    }

    public function addOrUpdateAction(
        $id_d,
        $id_e,
        $id_u,
        string $action,
        string $message_journal,
        bool $updateDate = false,
    ): void {
        $document_action = $this->documentActionSQL->getLastActionInfo($id_d, $id_e);

        if (! $document_action || $document_action['id_u'] !== $id_u || $document_action['action'] !== $action) {
            $this->addAction($id_d, $id_e, $id_u, $action, $message_journal);
        } else {
            if ($updateDate) {
                $this->documentActionSQL->updateDate($document_action['id_a']);
            }
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
