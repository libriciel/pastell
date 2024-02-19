<?php

use Pastell\File\Chunk\ChunkRequest;
use Pastell\File\Chunk\ChunkUploader;

class DocumentAPIController extends BaseAPIController
{
    public function __construct(
        private DocumentActionEntite $documentActionEntite,
        private DocumentSQL $document,
        private DonneesFormulaireFactory $donneesFormulaireFactory,
        private ActionPossible $actionPossible,
        private DocumentEntite $documentEntite,
        private DocumentTypeFactory $documentTypeFactory,
        private ActionExecutorFactory $actionExecutorFactory,
        private Journal $journal,
        private UtilisateurSQL $utilisateur,
        private EntiteSQL $entiteSQL,
        private DocumentCount $documentCount,
        private DocumentCreationService $documentCreationService,
        private DocumentModificationService $documentModificationService,
        private DocumentEmail $documentEmail,
        private DocumentEmailReponseSQL $documentEmailReponseSQL,
        private readonly ChunkUploader $chunkUploader,
    ) {
    }

    private function checkedEntite()
    {
        $id_e = $this->getFromQueryArgs(0) ?: 0;
        if ($id_e && !$this->entiteSQL->getInfo($id_e)) {
            throw new NotFoundException("L'entité $id_e n'existe pas");
        }
        $this->checkDroit($id_e, 'entite:lecture');
        return $id_e;
    }

    public function get()
    {
        if ($this->getFromQueryArgs(0) == 'count') {
            return $this->count();
        }

        $id_e = $this->checkedEntite();
        $id_d = $this->getFromQueryArgs(2);
        if ($id_d) {
            return $this->detail($id_e, $id_d);
        }

        $all_id_d = $this->getFromRequest('id_d', 0);
        if ($all_id_d) {
            return $this->getAll($id_e, $all_id_d);
        }

        $type = $this->getFromRequest('type');
        $offset = intval($this->getFromRequest('offset', 0));
        $limit = intval($this->getFromRequest('limit', 100));
        $search = $this->getFromRequest('search');
        $lastEtat = $this->getFromRequest('lastetat');
        $last_state_begin = $this->getFromRequest('last_state_begin');
        $last_state_end = $this->getFromRequest('last_state_end');
        $tri = $this->getFromRequest('tri', 'date_dernier_etat');
        $etatTransit = $this->getFromRequest('etatTransit');
        $state_begin = $this->getFromRequest('state_begin');
        $state_end = $this->getFromRequest('state_end');
        $notEtatTransit = $this->getFromRequest('notEtatTransit');
        $sens_tri = $this->getFromRequest('sens_tri', 'DESC');

        $date_in_fr = $this->getFromRequest('date_in_fr', false);

        if ($date_in_fr) {
            $last_state_begin = getDateIso($last_state_begin);
            $last_state_end = getDateIso($last_state_end);
            $state_begin = getDateIso($state_begin);
            $state_end = getDateIso($state_end);
        }

        if (!$id_e) {
            throw new Exception('id_e est obligatoire');
        }
        $this->checkDroit($id_e, 'entite:lecture');

        $allDroitEntite = $this->getDroitService()->getAllDocumentLecture($this->getUtilisateurId(), $id_e);

        $indexedFieldValue = [];
        if ($type) {
            $this->checkDroit($id_e, "$type:lecture");
            $documentType = $this->documentTypeFactory->getFluxDocumentType($type);
            $indexedFieldsList = $documentType->getFormulaire()->getIndexedFields();

            foreach ($indexedFieldsList as $indexField => $indexLibelle) {
                $indexedFieldValue[$indexField] = $this->getFromRequest($indexField);
                if ($documentType->getFormulaire()->getField($indexField)->getType() == 'date' && $date_in_fr) {
                    $indexedFieldValue[$indexField] = date_fr_to_iso($this->getFromRequest($indexField));
                }
            }
        }

        $documents = $this->documentActionEntite->getListBySearch(
            $id_e,
            $type,
            $offset,
            $limit,
            $search,
            $lastEtat,
            $last_state_begin,
            $last_state_end,
            $tri,
            $allDroitEntite,
            $etatTransit,
            $state_begin,
            $state_end,
            $notEtatTransit,
            $indexedFieldValue,
            $sens_tri
        );

        foreach ($documents as &$document) {
            $document['id_e'] = (string)$document['id_e'];
            $document['centre_de_gestion'] = (string)$document['centre_de_gestion'];
            $document['is_active'] = (bool)$document['is_active'];
        }
        return $documents;
    }

    private function countByEntityFormat()
    {
        $id_e = $this->getFromRequest('id_e');
        $type = $this->getFromRequest('type');

        if ($id_e === false || $type === false) {
            throw new Exception('Les paramètres id_e et type sont obligatoires.');
        }

        // verifier les droits
        $this->checkDroit($id_e, 'entite:lecture');
        $this->checkDroit($id_e, $type . ':lecture');

        $req = $this->getRequest();
        unset($req['id_e']);
        unset($req['type']);
        unset($req['api_function']);
        unset($req['output']);

        return $this->documentCount->getCountByEntityFormat($id_e, $type, $req);
    }

    private function count()
    {
        $output = $this->getFromRequest('output', 'detail');
        if ($output === 'simple') {
            return $this->countByEntityFormat();
        }

        $id_e = $this->getFromRequest('id_e');
        $type = $this->getFromRequest('type');
        return $this->documentCount->getAll($this->getUtilisateurId(), $id_e, $type);
    }

    private function detail($id_e, $id_d)
    {
        if ('externalData' == $this->getFromQueryArgs(3)) {
            return $this->externalDataAction($id_e, $id_d);
        }
        if ('file' == $this->getFromQueryArgs(3)) {
            return $this->getFichier($id_e, $id_d);
        }

        return $this->internalDetail($id_e, $id_d);
    }

    /**
     * @param $id_e
     * @param $id_d
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws Exception
     */
    private function internalDetail($id_e, $id_d): array
    {
        $info = $this->GetDocument($id_d, $id_e);
        $result['info'] = $info;
        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d, $info['type']);

        $result['data'] = $donneesFormulaire->getRawDataWithoutPassword();

        $email_info_list = $this->documentEmail->getInfo($id_d);

        foreach ($email_info_list as $num_mail => $email_info) {
            unset($email_info['key']);
            $result['email_info'][$num_mail] = $email_info;
        }

        $all_response = $this->documentEmailReponseSQL->getAllReponse($id_d);
        if ($all_response) {
            $result['email_reponse'] = $all_response;
        }

        $result['action_possible'] = $this->actionPossible->getActionPossible($id_e, $this->getUtilisateurId(), $id_d);

        /* Afin d'assurer la compatibilité V1 !!!*/
        /* Ne doit jamais être utilisé en V2 */
        $result['action-possible'] = $result['action_possible'];

        $result['last_action'] = $this->documentActionEntite->getLastActionInfo($id_e, $id_d);

        return $result;
    }

    public function getAll($id_e, $all_id_d)
    {
        if (!is_array($all_id_d)) {
            throw new Exception('Le paramètre id_d[] ne semble pas valide');
        }

        $max_execution_time = ini_get('max_execution_time');
        $result = [];
        foreach ($all_id_d as $id_d) {
            ini_set('max_execution_time', $max_execution_time);
            $result[$id_d] = $this->internalDetail($id_e, $id_d);
            $this->donneesFormulaireFactory->clearCache();
            $this->document->clearCache();
        }
        return $result;
    }

    /**
     * @return array|mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function post()
    {
        $id_e = $this->checkedEntite();

        $id_d = $this->getFromQueryArgs(2);
        if ($id_d && $this->GetDocument($id_d, $id_e)) {
            $file_type = $this->getFromQueryArgs(3);
            if ($file_type === 'chunk') {
                return $this->postChunk($id_e, $id_d);
            }
            return $this->postFile($id_e, $id_d);
        }

        $type = $this->getFromRequest('type', '');

        $id_d = $this->documentCreationService->createDocument($id_e, $this->getUtilisateurId(), $type);

        $result = $this->internalDetail($id_e, $id_d);
        $result['id_d'] = $id_d; //Compatibilité...

        return $result;
    }


    /**
     * @return mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function patch()
    {
        $id_e = $this->checkedEntite();
        $id_d = $this->getFromQueryArgs(2);

        if ('externalData' == $this->getFromQueryArgs(3)) {
            return $this->patchExternalData($id_e, $id_d);
        }
        $this->documentModificationService->modifyDocument(
            $id_e,
            $this->getUtilisateurId(),
            $id_d,
            new Recuperateur($this->getRequest()),
            $this->getFileUploader(),
            true
        );

        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d);
        $result['content'] = $this->internalDetail($id_e, $id_d);
        $result['result'] = self::RESULT_OK;
        $result['formulaire_ok'] = $donneesFormulaire->isValidable() ? 1 : 0;
        if (!$result['formulaire_ok']) {
            $result['message'] = $donneesFormulaire->getLastError();
        } else {
            $result['message'] = '';
        }
        return $result;
    }


    public function externalDataAction($id_e, $id_d)
    {
        $field = $this->getFromQueryArgs(4);

        $info = $this->document->getInfo($id_d);

        $this->checkDroit($id_e, "{$info['type']}:edition");

        $documentType = $this->documentTypeFactory->getFluxDocumentType($info['type']);
        $formulaire = $documentType->getFormulaire();
        $theField = $formulaire->getField($field);

        if (!$theField) {
            throw new Exception("Type $field introuvable");
        }

        $action_name = $theField->getProperties('choice-action');
        return $this->actionExecutorFactory->displayChoice(
            $id_e,
            $this->getUtilisateurId(),
            $id_d,
            $action_name,
            true,
            $field
        );
    }

    public function patchExternalData($id_e, $id_d)
    {
        $field = $this->getFromQueryArgs(4);
        $action_name = $this->getActionNameFromField($id_d, $field);
        $this->actionExecutorFactory->goChoice(
            $id_e,
            $this->getUtilisateurId(),
            $id_d,
            $action_name,
            $field,
            true,
            0,
            $this->getRequest()
        );
        $result = $this->internalDetail($id_e, $id_d);
        $result['result'] = 'ok'; //Compat V1
        return $result;
    }

    private function getActionNameFromField($id_d, $field)
    {
        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d);

        $formulaire = $donneesFormulaire->getFormulaire();
        $theField = $formulaire->getField($field);

        if (!$theField) {
            throw new Exception("Type $field introuvable");
        }

        return $theField->getProperties('choice-action');
    }

    public function getFichier($id_e, $id_d)
    {
        $field = $this->getFromQueryArgs(4);
        $num = $this->getFromQueryArgs(5) ?: 0;

        $mode_receive = $this->getFromRequest('receive');
        if ($mode_receive) {
            return $this->receiveFileAction($id_e, $id_d, $field, $num);
        }
        $info = $this->document->getInfo($id_d);

        $this->checkDroit($id_e, "{$info['type']}:edition");

        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d, $info['type']);

        $file_path = $donneesFormulaire->getFilePath($field, $num);
        $file_name_array = $donneesFormulaire->get($field);
        if (empty($file_name_array[$num])) {
            throw new NotFoundException("Ce fichier n'existe pas");
        }
        $file_name = $file_name_array[$num];

        if (!file_exists($file_path)) {
            throw new Exception("Ce fichier n'existe pas");
        }

        $infoUtilisateur = $this->utilisateur->getInfo($this->getUtilisateurId());
        $nom = $infoUtilisateur['prenom'] . ' ' . $infoUtilisateur['nom'];

        $this->journal->add(
            Journal::DOCUMENT_CONSULTATION,
            $id_e,
            $id_d,
            'Consulté',
            "$nom a consulté le document $file_name"
        );

        header_wrapper('Content-type: ' . mime_content_type($file_path));
        header_wrapper("Content-disposition: attachment; filename=\"$file_name\"");
        header_wrapper('Expires: 0');
        header_wrapper('Cache-Control: must-revalidate, post-check=0,pre-check=0');
        header_wrapper('Pragma: public');

        readfile($file_path);

        exit_wrapper(0);
    }

    /**
     * @param $id_e
     * @param $id_d
     * @return array|mixed
     * @throws ForbiddenException
     * @throws Exception
     */
    public function postFile($id_e, $id_d)
    {
        if ('action' == $this->getFromQueryArgs(3)) {
            return $this->actionAction($id_e, $id_d);
        }

        if (!$this->actionPossible->isActionPossible($id_e, $this->getUtilisateurId(), $id_d, 'modification')) {
            throw new Exception("L'action « modification »  n'est pas permise");
        }

        $field_name = $this->getFromQueryArgs(4);
        $file_number = $this->getFromQueryArgs(5) ?: 0;

        $file_name = $this->getFromRequest('file_name');

        $fileUploader = $this->getFileUploader();
        $file_content = $fileUploader->getFileContent('file_content');
        if (!$file_content) {
            $file_content = $this->getFromRequest('file_content');
        }

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        file_put_contents($tmp_folder . '/tmp_file', $file_content);

        $this->documentModificationService->addFile(
            $id_e,
            $this->getUtilisateurId(),
            $id_d,
            $field_name,
            $file_number,
            $file_name,
            $tmp_folder . '/tmp_file'
        );
        $tmpFolder->delete($tmp_folder);
        $result['content'] = $this->internalDetail($id_e, $id_d);
        $result['result'] = self::RESULT_OK;

        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d);
        $result['formulaire_ok'] = $donneesFormulaire->isValidable() ? 1 : 0;
        if (!$result['formulaire_ok']) {
            $result['message'] = $donneesFormulaire->getLastError();
        } else {
            $result['message'] = '';
        }
        return $result;
    }

    public function receiveFileAction($id_e, $id_d, $field_name, $file_number)
    {
        $document = $this->document;
        $info = $document->getInfo($id_d);
        $this->checkDroit($id_e, "{$info['type']}:lecture");
        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d);

        $result['file_name'] = $donneesFormulaire->getFileName($field_name, $file_number);
        $result['file_content'] = $donneesFormulaire->getFileContent($field_name, $file_number);

        return $result;
    }

    /**
     * @throws ForbiddenException
     * @throws Exception
     */
    private function action(
        string $documentId,
        int $entityId,
        string $action,
        array $destId = [],
        array $actionParams = []
    ): array {
        $info = $this->document->getInfo($documentId);
        if (!$info) {
            throw new NotFoundException("Le document $documentId n'appartient pas à l'entité $entityId");
        }
        $this->checkDroit($entityId, $info['type'] . ':edition');
        if (!$this->actionPossible->isActionPossible($entityId, $this->getUtilisateurId(), $documentId, $action)) {
            throw new Exception("L'action « $action »  n'est pas permise : " . $this->actionPossible->getLastBadRule());
        }

        $result = $this->actionExecutorFactory->executeOnDocument(
            $entityId,
            $this->getUtilisateurId(),
            $documentId,
            $action,
            $destId,
            true,
            $actionParams
        );
        $message = $this->actionExecutorFactory->getLastMessage();

        if (!$result) {
            throw new Exception($message);
        }
        return ['result' => $result, 'message' => $message];
    }


    public function actionAction($id_e, $id_d)
    {
        $action = $this->getFromQueryArgs(4);
        $id_destinataire = $this->getFromRequest('id_destinataire', []);
        $action_params = $this->getFromRequest('action_params', []);

        return $this->action($id_d, (int)$id_e, $action, $id_destinataire, $action_params);
    }


    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function delete(): array
    {
        $id_e = $this->checkedEntite();
        $id_d = $this->getFromQueryArgs(2);

        if ($this->getFromQueryArgs(3) === 'file') {
            return $this->deleteFile($id_d, (int)$id_e);
        }

        // TODO: Use DocumentDeletionService with a safe delete method
        $result = $this->action($id_d, (int)$id_e, 'supression');

        header_wrapper('HTTP/1.1 204 No Content');

        return $result;
    }

    private function deleteFile(
        string $documentId,
        int $entityId,
    ): array {
        $field = $this->getFromQueryArgs(4);
        $number = $this->getFromQueryArgs(5) ?: 0;

        $this->documentModificationService->removeFile(
            $entityId,
            $this->getUtilisateurId(),
            $documentId,
            $field,
            $number
        );

        return $this->internalDetail($entityId, $documentId);
    }

    /**
     * @throws Exception
     */
    public function postChunk(string $id_e, string $id_d): array
    {
        $field_name = $this->getFromQueryArgs(4);
        $file_number = $this->getFromQueryArgs(5);
        $file_number = $file_number === '' || $file_number === false ? 0 : (int)$file_number;
        $file_name = $this->getFromRequest('file_name');

        if (!isset($field_name, $file_name)) {
            throw new BadRequestException('Les paramètres field_name et file_name sont obligatoires');
        }

        $upload_filepath = sprintf(
            '%s/%d_%d_%s_%s_%s',
            $this->chunkUploader->getUploadChunkDirectory(),
            $id_e,
            $id_d,
            $field_name,
            time(),
            random_int(0, mt_getrandmax())
        );

        try {
            $chunkRequest = new ChunkRequest();
        } catch (InvalidArgumentException $e) {
            header_wrapper('HTTP/1.1 400 Bad Request');
            throw new BadRequestException($e->getMessage());
        }

        $chunkRequest->setFileNumber($file_number);
        $this->chunkUploader->setRequest($chunkRequest->getRequest());
        if ($this->chunkUploader->uploadChunk($upload_filepath)) {
            try {
                $this->documentModificationService->addFile(
                    $id_e,
                    $this->getUtilisateurId(),
                    $id_d,
                    $field_name,
                    $file_number,
                    $file_name,
                    $upload_filepath,
                );
            } finally {
                unlink($upload_filepath);
                header_wrapper('HTTP/1.1 201 Created');
                $response = ['result' => 'success', 'message' => 'File uploaded'];
            }
        } else {
            header_wrapper('HTTP/1.1 200 Ok');
            $response = ['result' => 'success', 'message' => 'Chunk uploaded'];
        }
        $this->chunkUploader->pruneChunks();
        return $response;
    }

    private function getDocument(string $id_d, $id_e): array
    {
        $info = $this->document->getInfo($id_d);
        if (!$info) {
            throw new NotFoundException("Le document $id_d n'appartient pas à l'entité $id_e");
        }

        $this->checkDroit($id_e, $info['type'] . ':edition');
        $my_role = $this->documentEntite->getRole($id_e, $id_d);
        if (!$my_role) {
            throw new NotFoundException("Le document $id_d n'appartient pas à l'entité $id_e");
        }
        return $info;
    }
}
