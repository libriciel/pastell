<?php

namespace Pastell\System\Check;

use JournalSQL;
use Pastell\System\CheckInterface;
use Pastell\System\HealthCheckItem;

class JournalCheck implements CheckInterface
{
    public function __construct(private readonly JournalSQL $journalSQL)
    {
    }

    public function check(): array
    {
        $firstLineDate = floor(
            (time() - strtotime(
                $this->journalSQL->getFirstDate() ?: date('Y-m-d H:i:s')
            )) / 86400
        );
        return [
            new HealthCheckItem(
                "Nombre d'enregistrements dans la table journal",
                number_format_fr($this->journalSQL->getCount())
            ),
            new HealthCheckItem(
                "Nombre d'enregistrements dans la table journal_historique",
                number_format_fr($this->journalSQL->getHistoriqueCount())
            ),
            new HealthCheckItem(
                'Date du premier enregistrement de la table journal',
                $this->journalSQL->getFirstDate()
            ),
            new HealthCheckItem("Nombre de mois de conservation du journal", (string)JOURNAL_MAX_AGE_IN_MONTHS),
            (new HealthCheckItem(
                "Age du premier enregistrement de la table journal",
                $firstLineDate . ' jours'
            ))->setSuccess($firstLineDate <= JOURNAL_MAX_AGE_IN_MONTHS * 31),
        ];
    }
}
