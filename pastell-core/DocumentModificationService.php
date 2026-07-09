<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class DocumentModificationService
{
    private const ACTION_PARAM_RECUPERATEUR = 'recuperateur';
    private const ACTION_PARAM_FILE_UPLOADER = 'fileUploader';

    private $actionExecutorFactory;
    private $documentSQL;
    private $actionPossible;
    private $droitService;


    public function __construct(
        ActionExecutorFactory $actionExecutorFactory,
        DocumentSQL $documentSQL,
        ActionPossible $actionPossible,
        DroitService $droitService
    ) {
        $this->actionExecutorFactory = $actionExecutorFactory;
        $this->documentSQL = $documentSQL;
        $this->actionPossible = $actionPossible;
        $this->droitService = $droitService;
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $id_d
     * @param Recuperateur $recuperateur
     * @param FileUploader $fileUploader
     * @param bool $from_api
     * @param bool $from_glaneur
     * @return bool
     */
    public function modifyDocumentWithoutAuthorizationChecking(
        $id_e,
        $id_u,
        $id_d,
        Recuperateur $recuperateur,
        FileUploader $fileUploader,
        bool $from_api = false,
        bool $from_glaneur = false
    ) {
        $result = $this->actionExecutorFactory->executeOnDocument(
            $id_e,
            $id_u,
            $id_d,
            ModificationAction::ACTION_ID,
            [],
            $from_api,
            [
                self::ACTION_PARAM_RECUPERATEUR => $recuperateur,
                self::ACTION_PARAM_FILE_UPLOADER => $fileUploader,
                'from_glaneur' => $from_glaneur
            ]
        );

        if (! $result) {
            $lastException = $this->actionExecutorFactory->getLastException();
            if ($lastException) {
                throw $lastException;
            }
        }
        return $result;
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $id_d
     * @param Recuperateur $recuperateur
     * @param FileUploader $fileUploader
     * @param bool $from_api
     * @param bool $from_glaneur
     * @return bool
     * @throws ForbiddenException
     */
    public function modifyDocument(
        $id_e,
        $id_u,
        $id_d,
        Recuperateur $recuperateur,
        FileUploader $fileUploader,
        bool $from_api = false,
        bool $from_glaneur = false
    ) {
        $this->verifCanModify($id_e, $id_u, $id_d);
        return $this->modifyDocumentWithoutAuthorizationChecking(
            $id_e,
            $id_u,
            $id_d,
            $recuperateur,
            $fileUploader,
            $from_api,
            $from_glaneur
        );
    }

    public function addFile($id_e, $id_u, $id_d, $field_name, $field_num, $file_name, $file_path)
    {
        $result = $this->actionExecutorFactory->executeOnDocument(
            $id_e,
            $id_u,
            $id_d,
            ModificationAction::ACTION_ID,
            [],
            true,
            [
                self::ACTION_PARAM_RECUPERATEUR => new Recuperateur([
                    'field_name' => $field_name,
                    'field_num' => $field_num,
                    'file_name' => $file_name,
                    'file_path' => $file_path
                ]),
                self::ACTION_PARAM_FILE_UPLOADER => new FileUploader(),
                'add_file' => true
            ]
        );
        if (! $result) {
            $lastException = $this->actionExecutorFactory->getLastException();
            if ($lastException) {
                throw $lastException;
            }
        }
        return $result;
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $id_d
     * @param $field_name
     * @param $field_num
     * @return bool
     * @throws ForbiddenException
     * @throws Exception
     */
    public function removeFile($id_e, $id_u, $id_d, $field_name, $field_num)
    {
        $this->verifCanModify($id_e, $id_u, $id_d);

        $result = $this->actionExecutorFactory->executeOnDocument(
            $id_e,
            $id_u,
            $id_d,
            ModificationAction::ACTION_ID,
            [],
            true,
            [
                self::ACTION_PARAM_RECUPERATEUR => new Recuperateur(['field' => $field_name,'num' => $field_num]),
                self::ACTION_PARAM_FILE_UPLOADER => new FileUploader(),
                'delete_file' => true
            ]
        );
        if (! $result) {
            $lastException = $this->actionExecutorFactory->getLastException();
            if ($lastException) {
                throw $lastException;
            }
        }
        return $result;
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $id_d
     * @throws ForbiddenException
     * @throws Exception
     */
    private function verifCanModify($id_e, $id_u, $id_d)
    {
        $document_info = $this->documentSQL->getInfo($id_d);
        if (! $document_info) {
            throw new NotFoundException("Le document $id_d n'a pas été trouvé");
        }

        $droit = DroitService::getDroitFor($document_info['type'], DroitType::EDITION);
        if (! $this->droitService->hasDroitFor($id_u, $id_e, $document_info['type'], DroitType::EDITION)) {
            throw new ForbiddenException("Acces interdit id_e=$id_e, droit=$droit,id_u=$id_u");
        }

        if (! $this->actionPossible->isActionPossible($id_e, $id_u, $id_d, 'modification')) {
            throw new ForbiddenException(
                "L'action « modification »  n'est pas permise : " . $this->actionPossible->getLastBadRule()
            );
        }
    }
}
