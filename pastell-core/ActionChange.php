<?php

declare(strict_types=1);

use Pastell\Service\Journal\JournalEntryService;

final readonly class ActionChange
{
    public function __construct(
        private DocumentActionSQL $documentActionSQL,
        private DocumentActionEntiteSQL $documentActionEntiteSQL,
        private JournalEntryService $journalEntryService,
    ) {
    }

    public function addAction($id_d, $id_e, $id_u, string $action, string $message_journal): void
    {
        $id_a = $this->documentActionSQL->add($id_d, $id_e, $id_u, $action);
        $id_j = $this->journalEntryService->addSQL(
            JournalEventType::DOCUMENT_ACTION,
            (int) $id_e,
            (int) $id_u,
            (string) $id_d,
            $action,
            $message_journal
        );
        $this->documentActionEntiteSQL->add((int) $id_a, (int) $id_e, $id_j);
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
            $this->journalEntryService->addSQL(
                JournalEventType::DOCUMENT_ACTION,
                (int) $id_e,
                (int) $id_u,
                (string) $id_d,
                $action,
                $message_journal
            );
        }
    }
}
