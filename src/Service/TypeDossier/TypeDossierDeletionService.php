<?php

namespace Pastell\Service\TypeDossier;

use Pastell\Service\Entite\RootEntity;
use TypeDossierSQL;
use TypeDossierPersonnaliseDirectoryManager;
use TypeDossierException;
use JournalAction;
use JournalEventType;
use Pastell\Service\Journal\JournalEntryService;

class TypeDossierDeletionService
{
    /**
     * @var TypeDossierSQL
     */
    private $typeDossierSQL;

    /**
     * @var TypeDossierPersonnaliseDirectoryManager
     */
    private $typeDossierPersonnaliseDirectoryManager;

    /**
     * @var TypeDossierExportService
     */
    private $typeDossierExportService;

    private JournalEntryService $journalEntryService;

    public function __construct(
        TypeDossierSQL $typeDossierSQL,
        TypeDossierPersonnaliseDirectoryManager $typeDossierPersonnaliseDirectoryManager,
        TypeDossierExportService $typeDossierExportService,
        JournalEntryService $journalEntryService
    ) {
        $this->typeDossierSQL = $typeDossierSQL;
        $this->typeDossierPersonnaliseDirectoryManager = $typeDossierPersonnaliseDirectoryManager;
        $this->typeDossierExportService = $typeDossierExportService;
        $this->journalEntryService = $journalEntryService;
    }

    /**
     * @param int $id_t
     * @throws TypeDossierException
     */
    public function delete(int $id_t): void
    {
        $export = $this->typeDossierExportService->export($id_t);

        $this->typeDossierPersonnaliseDirectoryManager->delete($id_t);
        $this->typeDossierSQL->delete($id_t);

        $this->journalEntryService->add(
            JournalEventType::DOCUMENT_TYPE_EDITION,
            RootEntity::ID_E,
            JournalEntryService::NO_ID_D,
            JournalAction::DELETED->value,
            "Suppression du type de dossier id_t=$id_t. JSON contenant l'export de la definition du type de dossier : " . $export
        );
    }
}
