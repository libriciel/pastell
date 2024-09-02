<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor1;

use ActionChange;
use Exception;
use JobManager;
use Pastell\Updater\Version;
use PastellLogger;
use SQLQuery;

final class DeleteActionHeliosExtraction implements Version
{
    private const HELIOS_PRE_EXTRACTION = 'helios-pre-extraction';
    private const PRE_SEND_TDT = 'pre-send-tdt';

    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly ActionChange $actionChange,
        private readonly JobManager $jobManager,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * Suppression des actions 'helios-pre-extraction' et 'helios-extraction' pour l'étape studio tdt_helios.
     * Il faut traiter les actions des documents qui se trouvent en état 'helios-pre-extraction' lors de la migration.
     * @throws Exception
     */
    public function update(): void
    {
        $selectLastActionExtraction = <<<EOT
SELECT *
FROM document_entite
WHERE last_action=?;
EOT;
        $documents = $this->sqlQuery->query(
            $selectLastActionExtraction,
            self::HELIOS_PRE_EXTRACTION
        );

        foreach ($documents as $document) {
            $this->actionChange->addAction(
                $document['id_d'],
                $document['id_e'],
                0,
                self::PRE_SEND_TDT,
                'Modification via l\'updater DeleteActionHeliosExtraction',
            );
            $this->jobManager->setJobForDocument(
                $document['id_e'],
                $document['id_d'],
                'Lancement du job via l\'updater DeleteActionHeliosExtraction'
            );
            $this->logger?->info(
                \sprintf(
                    "Update action to '%s' for id_d='%s' id_e='%s'",
                    self::PRE_SEND_TDT,
                    $document['id_d'],
                    $document['id_e']
                )
            );
        }
    }
}
