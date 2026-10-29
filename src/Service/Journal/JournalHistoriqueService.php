<?php

namespace Pastell\Service\Journal;

use JournalHistoriqueSQL;
use JournalEventType;

class JournalHistoriqueService
{
    private $journalHistoriqueSQL;
    private JournalEntryService $journalEntryService;

    public function __construct(
        JournalHistoriqueSQL $journalHistoriqueSQL,
        JournalEntryService $journalEntryService
    ) {
        $this->journalHistoriqueSQL = $journalHistoriqueSQL;
        $this->journalEntryService = $journalEntryService;
    }

    public function truncate()
    {
        $count = $this->journalHistoriqueSQL->getCount();
        $min_date = $this->journalHistoriqueSQL->getFirstDate();
        $max_date = $this->journalHistoriqueSQL->getLastDate();
        if ($count <= 0) {
            return;
        }
        $this->journalHistoriqueSQL->truncate();

        $message = sprintf(
            "Purge de la table journal_historique : %d enregistrement(s) supprimé(s), enregistrement le plus agé : %s, enregistrement le plus récent : %s",
            $count,
            $min_date,
            $max_date
        );

        $this->journalEntryService->addSQL(
            JournalEventType::JOURNAL,
            0,
            0,
            '',
            '',
            $message
        );
    }
}
