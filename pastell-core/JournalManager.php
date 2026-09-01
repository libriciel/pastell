<?php

declare(strict_types=1);

use Pastell\Mailer\AdminMailer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class JournalManager
{
    public function __construct(
        private readonly Journal $journalSQL,
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
            $this->journalSQL->purgeToHistorique($this->journal_max_age_in_days);
        } catch (Exception $e) {
            $message = sprintf('Erreur sur la purge du journal : %s', $e->getMessage());
            $this->logger->error($message);
            $this->adminMailer->notifyText('[Pastell] Problème sur la purge du journal', $message);
            return false;
        }
        $this->logger->info('Purge du journal des événements terminée');
        return true;
    }
}
