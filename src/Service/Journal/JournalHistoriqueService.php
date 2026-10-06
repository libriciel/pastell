<?php

namespace Pastell\Service\Journal;

use JournalHistoriqueSQL;
use JournalEventType;
use Pastell\Service\Journal\JournalEntryService;

class JournalHistoriqueService
{
    private $journalHistoriqueSQL;
    private $journal;

    public function __construct(
        JournalHistoriqueSQL $journalHistoriqueSQL,
        JournalEntryService $journal
    ) {
        $this->journalHistoriqueSQL = $journalHistoriqueSQL;
        $this->journal = $journal;
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

        $this->journal->addSQL(
            JournalEventType::JOURNAL,
            0,
            0,
            '',
            '',
            $message
        );
    }
}
