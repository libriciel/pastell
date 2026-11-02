<?php

namespace Pastell\Tests\Service\Journal;

use Exception;
use JournalHistoriqueSQL;
use JournalManager;
use Pastell\Service\Journal\JournalHistoriqueService;
use PastellTestCase;
use JournalEventType;

class JournalHistoriqueServiceTest extends PastellTestCase
{
    /**
     * @throws Exception
     */
    public function testTruncate()
    {
        $journalHistoriqueService = $this->getObjectInstancier()->getInstance(JournalHistoriqueService::class);

        $id_j = $this->getJournalEntryService()->add(JournalEventType::TEST, 0, '', '', 'foo');
        $sql = "UPDATE journal SET date=? WHERE id_j=?";
        $this->getSQLQuery()->query($sql, '1970-01-01', $id_j);

        $id_j = $this->getJournalEntryService()->add(JournalEventType::TEST, 0, '', '', 'bar');
        $sql = "UPDATE journal SET date=? WHERE id_j=?";
        $this->getSQLQuery()->query($sql, '1970-01-02', $id_j);

        $journalManager = $this->getObjectInstancier()->getInstance(JournalManager::class);
        $journalManager->purgeToHistorique();

        $journalHistoriqueSQL = $this->getObjectInstancier()->getInstance(JournalHistoriqueSQL::class);
        $this->assertEquals(2, $journalHistoriqueSQL->getCount());

        $journalHistoriqueService->truncate();
        $this->assertEquals(0, $journalHistoriqueSQL->getCount());
        $this->assertEquals(
            'Purge de la table journal_historique : 2 enregistrement(s) supprimé(s), enregistrement le plus agé : 1970-01-01 00:00:00, enregistrement le plus récent : 1970-01-02 00:00:00',
            $this->getJournalConsultationService()->getList()[0]['message']
        );
    }

    public function testTruncateEmpty()
    {
        $journalHistoriqueService = $this->getObjectInstancier()->getInstance(JournalHistoriqueService::class);
        $journalHistoriqueService->truncate();
        $this->assertEmpty($this->getJournalConsultationService()->getList());
    }
}
