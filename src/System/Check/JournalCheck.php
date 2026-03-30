<?php

namespace Pastell\System\Check;

use Journal;
use Pastell\System\CheckInterface;
use Pastell\System\HealthCheckItem;

class JournalCheck implements CheckInterface
{
    public function __construct(private readonly Journal $journal)
    {
    }

    public function check(): array
    {
        $firstLineDate = floor(
            (time() - strtotime(
                $this->journal->getFirstLineDate() ?: date('Y-m-d H:i:s')
            )) / 86400
        );
        return [
            new HealthCheckItem(
                "Nombre d'enregistrements dans la table journal",
                number_format_fr($this->journal->getNbLine())
            ),
            new HealthCheckItem(
                "Nombre d'enregistrements dans la table journal_historique",
                number_format_fr($this->journal->getNbLineHistorique())
            ),
            new HealthCheckItem(
                'Date du premier enregistrement de la table journal',
                $this->journal->getFirstLineDate()
            ),
            new HealthCheckItem('Nombre de jours de conservation du journal', (string)JOURNAL_MAX_AGE_IN_DAYS),
            new HealthCheckItem(
                'Age du premier enregistrement de la table journal',
                $firstLineDate . ' jours'
            )->setSuccess($firstLineDate <= JOURNAL_MAX_AGE_IN_DAYS),
        ];
    }
}
