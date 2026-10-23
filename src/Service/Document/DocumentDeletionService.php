<?php

namespace Pastell\Service\Document;

use DocumentEmailReponseSQL;
use DocumentSQL;
use DonneesFormulaireFactory;
use JobManager;
use NotificationDigestSQL;
use NotFoundException;
use JournalEventType;
use Pastell\Service\Journal\JournalEntryService;

class DocumentDeletionService
{
    public function __construct(
        private readonly DocumentSQL $documentSQL,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly DocumentEntiteService $documentEntiteService,
        private readonly JobManager $jobManager,
        private readonly JournalEntryService $journalEntryService,
        private readonly DocumentEmailReponseSQL $documentEmailReponseSQL,
        private readonly NotificationDigestSQL $notificationDigestSQL,
    ) {
    }

    /**
     * @param string $id_d
     * @param string|null $message
     * @return string
     * @throws NotFoundException
     */
    public function delete(string $id_d, ?string $message = null): string
    {
        $id_e = $this->documentEntiteService->getEntiteId($id_d);
        $info = $this->documentSQL->getInfo($id_d);

        $reponses = array_merge(
            $this->documentEmailReponseSQL->getAllReponse($id_d),
            $this->documentEmailReponseSQL->getAllReponse($id_d, false)
        );

        foreach ($reponses as $reponse) {
            $this->delete($reponse['id_d_reponse']);
        }
        $this->donneesFormulaireFactory->get($id_d)->delete();
        $this->documentSQL->delete($id_d);
        $this->jobManager->deleteDocumentForAllEntities($id_d);
        $this->notificationDigestSQL->deleteByDocument($id_d);

        $message = sprintf(
            'Le document « %s » (%s) a été supprimé %s',
            $info['titre'],
            $id_d,
            ($message) ? ' - ' . $message : '',
        );
        $this->journalEntryService->add(
            JournalEventType::DOCUMENT_ACTION,
            $id_e,
            $id_d,
            'suppression',
            $message
        );
        return $message;
    }
}
