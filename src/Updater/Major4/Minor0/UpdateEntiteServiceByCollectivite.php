<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor0;

use Exception;
use Pastell\Updater\Version;
use PastellLogger;
use SQLQuery;

final class UpdateEntiteServiceByCollectivite implements Version
{
    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly ?PastellLogger $logger = null,
    ) {
    }
    /**
     * Suppression du modèle `Entite`, remplacé par `EntiteSQL` (et suppression du type d'entité "service") #1589
     * @throws Exception
     */
    public function update(): void
    {
        $this->logger?->info('Start');

        $sql = 'UPDATE entite ' .
            " SET type = 'collectivite' " .
            " WHERE type = 'service' ";
        $this->sqlQuery->query($sql);
        $this->logger?->info($sql);
    }
}
