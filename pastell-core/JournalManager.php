<?php

declare(strict_types=1);

use Pastell\Mailer\AdminMailer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class JournalManager
{
    public function __construct(
        private readonly JournalSQL $journalSQL,
        private readonly int $journal_max_age_in_days,
        private readonly Monolog\Logger $logger,
        private readonly AdminMailer $adminMailer,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function purgeToHistorique(): bool
    {
        $this->logger->info('Lancement de la purge du journal des événements');
        try {
            $this->purge();
        } catch (Exception $e) {
            $message = sprintf('Erreur sur la purge du journal : %s', $e->getMessage());
            $this->logger->error($message);
            $this->adminMailer->notifyText('[Pastell] Problème sur la purge du journal', $message);
            return false;
        }
        $this->logger->info('Purge du journal des événements terminée');
        return true;
    }

    private function purge(): void
    {
        $date = date('Y-m-d H:i:s', strtotime("-$this->journal_max_age_in_days days"));
        do {
            $id_j_list = $this->journalSQL->getIdListOlderThan($date);
            foreach ($id_j_list as $id_j) {
                if (!$this->journalSQL->existsInHistorique($id_j)) {
                    $this->journalSQL->copyToHistorique($id_j);
                }
                $this->journalSQL->delete($id_j);
            }
        } while ($id_j_list);
    }
}
