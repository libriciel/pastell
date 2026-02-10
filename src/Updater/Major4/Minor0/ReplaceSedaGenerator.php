<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor0;

use DonneesFormulaireFactory;
use Exception;
use Pastell\Updater\Version;
use PastellLogger;
use SQLQuery;

final class ReplaceSedaGenerator implements Version
{
    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * @throws \JsonException
     * @throws Exception
     */
    public function update(): void
    {
        $this->logger?->info('Start');

        $selectConnectorsQuery = <<<EOT
SELECT id_ce
FROM connecteur_entite
WHERE id_e != ?
AND id_connecteur= ?;
EOT;
        $connectors = $this->sqlQuery->query($selectConnectorsQuery, 0, 'generateur-seda');
        foreach ($connectors as $connector) {
            $connectorId = $connector['id_ce'];
            $form = $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($connectorId);
            $fileContent = $form->getFileContent('data');
            if ($fileContent !== false) {
                $content = \json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);
                $version = $content['version'];
            } else {
                $version = '1.0';
            }
            $connectorName = $version === '2.1' ? 'generateur-seda-asalae-2.1' : 'generateur-seda-asalae-1.0';

            $updateConnectorQuery = <<<EOT
UPDATE connecteur_entite
SET id_connecteur = ?
WHERE id_ce = ?;
EOT;
            $this->sqlQuery->query($updateConnectorQuery, $connectorName, $connectorId);
            $this->logger?->info("Update connector '$connectorId' to '$connectorName'");
        }
    }
}
