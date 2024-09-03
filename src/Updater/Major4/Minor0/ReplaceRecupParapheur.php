<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor0;

use Pastell\Updater\Version;
use PastellLogger;
use SQLQuery;

final class ReplaceRecupParapheur implements Version
{
    private const PARAPHEUR_TRASH_RECOVERY_MODULE = 'recup-parapheur-corbeille';

    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function update(): void
    {
        $selectConnectorsQuery = <<<EOT
SELECT id_ce
FROM connecteur_entite
WHERE id_e != ?
AND id_connecteur= ?;
EOT;
        $connectors = $this->sqlQuery->query($selectConnectorsQuery, 0, 'recup-parapheur');
        foreach ($connectors as $connector) {
            $connectorId = $connector['id_ce'];

            $updateConnectorQuery = <<<EOT
UPDATE connecteur_entite
SET id_connecteur = ?
WHERE id_ce = ?;
EOT;
            $this->sqlQuery->query($updateConnectorQuery, self::PARAPHEUR_TRASH_RECOVERY_MODULE, $connectorId);
            $this->logger?->info(
                \sprintf(
                    "Update connector '%s' to '%s'",
                    $connectorId,
                    self::PARAPHEUR_TRASH_RECOVERY_MODULE,
                )
            );
        }
    }
}
