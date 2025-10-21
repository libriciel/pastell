<?php

class GlaneurDocumentCreator
{
    public function __construct(
        private readonly ActionChange $actionChange,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly JobManager $jobManager,
        private readonly DocumentCreationService $documentCreationService,
        private readonly DocumentModificationService $documentModificationService,
        private readonly NotificationMail $notificationMail
    ) {
    }

    /**
     * @param GlaneurDocumentInfo $glaneurLocalDocumentInfo
     * @return string
     * @throws Exception
     */
    public function create(GlaneurDocumentInfo $glaneurLocalDocumentInfo, string $repertoire)
    {
        $new_id_d = $this->documentCreationService->createDocumentWithoutAuthorizationChecking(
            $glaneurLocalDocumentInfo->id_e,
            $glaneurLocalDocumentInfo->nom_flux
        );

        $files = [];
        foreach ($glaneurLocalDocumentInfo->element_files_association as $key => $files_list) {
            foreach ($files_list as $file_num => $file) {
                $files[$key]['name'][$file_num] = $file;
                $files[$key]['tmp_name'][$file_num] = $repertoire . "/" . $file;
                $files[$key]['error'][$file_num] = UPLOAD_ERR_OK;
            }
        }

        $fileUploader = new FileUploader();
        $fileUploader->setDontUseMoveUploadedFile(true);
        $fileUploader->setFiles($files);
        try {
            $this->documentModificationService->modifyDocumentWithoutAuthorizationChecking(
                $glaneurLocalDocumentInfo->id_e,
                0,
                $new_id_d,
                new Recuperateur($glaneurLocalDocumentInfo->metadata),
                $fileUploader,
                true,
                true
            );
        } catch (Exception $e) {
            // Errors will be caught on document validation
        }

        $this->actionChange->addAction(
            $new_id_d,
            $glaneurLocalDocumentInfo->id_e,
            0,
            Action::MODIFICATION,
            '[glaneur] Import du document'
        );

        if (! $glaneurLocalDocumentInfo->action_ok) {
            return $new_id_d;
        }
        $donneesFormulaire = $this->donneesFormulaireFactory->get($new_id_d);

        // A ce stade, l'import est réussi, si le document est ko alors il passe dans un etat d'erreur, mais on le supprime
        if ($donneesFormulaire->isValidable()) {
            $message = "[glaneur] Passage en action_ok : {$glaneurLocalDocumentInfo->action_ok}";
            $next_state = $glaneurLocalDocumentInfo->action_ok;
        } elseif ($glaneurLocalDocumentInfo->force_action_ok) {
            $message = "[glaneur] Passage en action_ok forcé : {$glaneurLocalDocumentInfo->action_ok}";
            $next_state = $glaneurLocalDocumentInfo->action_ok;
        } else {
            $message = "[glaneur] Le dossier n'est pas valide : " . $donneesFormulaire->getLastError();
            $next_state = $glaneurLocalDocumentInfo->action_ko ?: "fatal-error";
        }
        $this->actionChange->addAction(
            $new_id_d,
            $glaneurLocalDocumentInfo->id_e,
            0,
            $next_state,
            $message
        );
        $this->notificationMail->notify(
            $glaneurLocalDocumentInfo->id_e,
            $new_id_d,
            $next_state,
            $glaneurLocalDocumentInfo->nom_flux,
            $message
        );
        $this->jobManager->setJobForDocument($glaneurLocalDocumentInfo->id_e, $new_id_d, $message);
        return $new_id_d;
    }
}
