<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor1;

use Exception;
use Pastell\Updater\Version;
use PastellLogger;
use SQLQuery;

final class SetColumnGlobalConnecteur implements Version
{
    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * @throws Exception
     */
    public function update(): void
    {
        $selectConnectorsQuery = <<<EOT
SELECT id_ce, id_e
FROM connecteur_entite
WHERE global IS NULL;
EOT;
        $connectors = $this->sqlQuery->query($selectConnectorsQuery);
        foreach ($connectors as $connector) {
            $connectorId = $connector['id_ce'];
            $global = $connector['id_e'] === 0 ? 1 : 0;

            $updateConnectorQuery = <<<EOT
UPDATE connecteur_entite
SET global = ?
WHERE id_ce = ?;
EOT;
            $this->sqlQuery->query($updateConnectorQuery, $global, $connectorId);
            $this->logger?->info(
                \sprintf(
                    "Update connector id_ce='%s', id_e='%s',  set column global '%s'",
                    $connectorId,
                    $connector['id_e'],
                    $global,
                )
            );
        }
    }
}
