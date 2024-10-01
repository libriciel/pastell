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
        $firstLineDate = $this->journal->getFirstLineDate();
        $firstLineDateString = empty($firstLineDate) ? '' : round((time() - strtotime($firstLineDate)) / 86400) . ' jours';
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
            new HealthCheckItem("Nombre de mois de conservation du journal", (string)JOURNAL_MAX_AGE_IN_MONTHS),
            (new HealthCheckItem(
                "Age du premier enregistrement de la table journal",
                $firstLineDateString
            ))->setSuccess(round((time() - strtotime($firstLineDate)) / 86400) <= JOURNAL_MAX_AGE_IN_MONTHS * 31),
        ];
    }
}
