<?php

use Pastell\File\Chunk\ChunkRequest;
use Pastell\File\Chunk\ChunkUploader;
use Pastell\Service\Connecteur\ConnecteurCreationService;
use Pastell\Service\Connecteur\ConnecteurDeletionService;
use Pastell\Service\Connecteur\ConnecteurModificationService;
use Pastell\Service\Droit\DroitService;

class ConnecteurAPIController extends BaseAPIController
{
    public function __construct(
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly ActionPossible $actionPossible,
        private readonly ActionExecutorFactory $actionExecutorFactory,
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly ConnecteurDefinitionFiles $connecteurDefinitionFiles,
        private readonly EntiteSQL $entiteSQL,
        private readonly ConnecteurCreationService $connecteurCreationService,
        private readonly ConnecteurDeletionService $connecteurDeletionService,
        private readonly ConnecteurModificationService $connecteurModificationService,
        private readonly ChunkUploader $chunkUploader,
    ) {
    }

    /**
     * @throws Exception
     */
    private function verifExists($id_ce)
    {
        $info = $this->connecteurEntiteSQL->getInfo($id_ce);
        if (!$info) {
            throw new Exception("Ce connecteur n'existe pas.");
        }
    }

    /**
     * @throws NotFoundException
     * @throws ForbiddenException
     */
    private function checkedEntite()
    {
        $id_e = $this->getFromQueryArgs(0) ?: 0;
        if ($id_e && !$this->entiteSQL->getInfo($id_e)) {
            throw new NotFoundException("L'entité $id_e n'existe pas");
        }
        $this->checkDroit($id_e, "entite:lecture");
        return $id_e;
    }

    /**
     * @return array|bool|mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public function get()
    {
        if ($this->getFromQueryArgs(0) === 'all') {
            return $this->listAllConnecteur();
        }
        $id_e = $this->checkedEntite();
        $this->checkConnecteurLecture($id_e);

        $id_ce = $this->getFromQueryArgs(2);
        if ($id_ce) {
            return $this->detail($id_e, $id_ce);
        }

        $connectors = $this->connecteurEntiteSQL->getAll($id_e);

        foreach ($connectors as &$connector) {
            $connector['id_ce'] = (string)$connector['id_ce'];
            $connector['id_e'] = (string)$connector['id_e'];
            $connector['frequence_en_minute'] = (string)$connector['frequence_en_minute'];
        }

        return $connectors;
    }

    /**
     * @return array
     * @throws ForbiddenException
     */
    public function listAllConnecteur(): array
    {
        $this->checkConnecteurLecture(0);
        $id_connecteur = $this->getFromQueryArgs(1);
        if (!$id_connecteur) {
            $connectors = $this->connecteurEntiteSQL->getAllForPlateform();
        } else {
            $connectors = $this->connecteurEntiteSQL->getAllById($id_connecteur);
        }

        foreach ($connectors as &$connector) {
            $connector['id_ce'] = (string)$connector['id_ce'];
            $connector['id_e'] = (string)$connector['id_e'];
            $connector['frequence_en_minute'] = (string)$connector['frequence_en_minute'];
        }
        return $connectors;
    }

    /**
     * @param $id_e
     * @param $id_ce
     * @return array|bool|mixed
     * @throws NotFoundException
     * @throws Exception
     */
    public function detail($id_e, $id_ce)
    {
        $this->checkConnecteurLecture($id_e);
        $this->checkedConnecteur($id_e, $id_ce);
        if ('file' == $this->getFromQueryArgs(3)) {
            return $this->readFichier($id_ce);
        }
        if ('externalData' == $this->getFromQueryArgs(3)) {
            return $this->getExternalData($id_ce);
        }
        return $this->getDetail($id_e, $id_ce);
    }

    /**
     * @throws Exception
     */
    private function getDetail($id_e, $id_ce)
    {
        $result = $this->checkedConnecteur($id_e, $id_ce);

        $donneesFormulaire = $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce);
        $result['data'] = $donneesFormulaire->getRawDataWithoutPassword();
        $result['action-possible'] = $this->actionPossible
            ->getActionPossibleOnConnecteur($id_ce, $this->getUtilisateurId());

        $result['id_ce'] = (string)$result['id_ce'];
        $result['id_e'] = (string)$result['id_e'];
        $result['frequence_en_minute'] = (string)$result['frequence_en_minute'];

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getExternalData($id_ce)
    {
        $field = $this->getFromQueryArgs(4);
        $action_name = $this->getActionNameFromField($id_ce, $field);
        return $this->actionExecutorFactory->displayChoiceOnConnecteur(
            $id_ce,
            $this->getUtilisateurId(),
            $action_name,
            $field,
            true
        );
    }

    //TODO assurément c'est pas la bonne place de cette fonction

    /**
     * @throws Exception
     */
    private function getActionNameFromField($id_ce, $field)
    {
        $connecteurConfig = $this->connecteurFactory->getConnecteurConfig($id_ce);

        $formulaire = $connecteurConfig->getFormulaire();
        $theField = $formulaire->getField($field);

        if (!$theField) {
            throw new Exception("Type $field introuvable");
        }

        return $theField->getProperties('choice-action');
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function patchExternalData($id_e, $id_ce)
    {
        $field = $this->getFromQueryArgs(4);

        $this->connecteurModificationService->addExternalData(
            $id_ce,
            $field,
            $this->getUtilisateurId(),
            "L'external data $field a été modifié via l'API",
            true,
            $this->getRequest()
        );

        return $this->getDetail($id_e, $id_ce);
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function getFichier($id_ce, mixed $field, mixed $num): array
    {
        $donneesFormulaire = $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce);

        $file_path = $donneesFormulaire->getFilePath($field, $num);
        $file_name_array = $donneesFormulaire->get($field);
        if (empty($file_name_array[$num])) {
            throw new NotFoundException("Ce fichier n'existe pas");
        }
        $file_name = $file_name_array[$num];

        if (!file_exists($file_path)) {
            throw new Exception("Ce fichier n'existe pas");
        }
        return [$file_path, $file_name];
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function readFichier($id_ce)
    {
        $field = $this->getFromQueryArgs(4);
        $num = $this->getFromQueryArgs(5) ?: 0;
        list($file_path, $file_name) = $this->getFichier($id_ce, $field, $num);

        header_wrapper('Content-type: ' . mime_content_type($file_path));
        header_wrapper("Content-disposition: attachment; filename=\"$file_name\"");
        header_wrapper('Expires: 0');
        header_wrapper('Cache-Control: must-revalidate, post-check=0,pre-check=0');
        header_wrapper('Pragma: public');

        readfile($file_path);

        exit_wrapper(0);
    }

    /**
     * @throws Exception
     */
    public function checkedConnecteur($id_e, $id_ce)
    {
        $this->verifExists($id_ce);
        $result = $this->connecteurEntiteSQL->getInfo($id_ce);
        if ($result['id_e'] != $id_e) {
            throw new Exception("Le connecteur $id_ce n'appartient pas à l'entité $id_e");
        }
        return $result;
    }

    /**
     * @param int $id_e
     * @throws ForbiddenException
     */
    private function checkConnecteurLecture(int $id_e): void
    {
        $this->checkDroit($id_e, DroitService::getDroitLecture(DroitService::DROIT_CONNECTEUR));
    }

    /**
     * @param int $id_e
     * @throws ForbiddenException
     */
    private function checkConnecteurEdition(int $id_e): void
    {
        $this->checkDroit($id_e, DroitService::getDroitEdition(DroitService::DROIT_CONNECTEUR));
    }

    /**
     * @return array|bool|mixed
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws Exception
     */
    public function post(): mixed
    {
        $id_e = $this->checkedEntite();
        $this->checkConnecteurEdition($id_e);
        $id_connecteur = $this->getFromRequest('id_connecteur');

        $id_ce = $this->getFromQueryArgs(2);
        if ($id_ce) {
            $this->checkedConnecteur($id_e, $id_ce);
            $file_type = $this->getFromQueryArgs(3);
            if ($file_type === 'chunk') {
                return $this->postChunk($id_e, $id_ce);
            }
            return $this->postFile($id_e, $id_ce);
        }

        $libelle = $this->getFromRequest('libelle');

        if (!$libelle) {
            throw new Exception("Le libellé est obligatoire.");
        }

        if ($id_e) {
            $connecteur_info = $this->connecteurDefinitionFiles->getInfo($id_connecteur);
        } else {
            $connecteur_info = $this->connecteurDefinitionFiles->getInfoGlobal($id_connecteur);
        }

        if (!$connecteur_info) {
            throw new Exception("Aucun connecteur du type « $id_connecteur »");
        }

        $id_ce = $this->connecteurCreationService->createConnecteur(
            $id_connecteur,
            $connecteur_info['type'],
            $id_e,
            $this->getUtilisateurId(),
            $libelle,
            [],
            "Le connecteur $id_connecteur « $libelle » a été créé"
        );

        //TODO Ajouter une fonction pour lancer les actions autos sur le connecteur
        //$this->jobManager->setJobForConnecteur($id_ce,$action_name,"création du connecteur");

        return $this->detail($id_e, $id_ce);
    }

    /**
     * @return array
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws Exception
     */
    public function delete(): array
    {
        $id_e = $this->checkedEntite();
        $id_ce = $this->getFromQueryArgs(2);

        $this->checkedConnecteur($id_e, $id_ce);
        $this->checkConnecteurEdition($id_e);
        if ($this->getFromQueryArgs(3) === 'file') {
            $field_name = $this->getFromQueryArgs(4);
            $file_num = $this->getFromQueryArgs(5) ?: 0;
            if ($field_name) {
                $this->connecteurModificationService->removeFile(
                    $id_ce,
                    $field_name,
                    $file_num,
                    $id_e,
                    $this->getUtilisateurId(),
                    "Le fichier $field_name a été supprimé"
                );
            } else {
                throw new Exception('Paramètre manquant');
            }
        } else {
            $this->connecteurDeletionService->deleteConnecteur($id_ce);
        }
        $result['result'] = self::RESULT_OK;
        return $result;
    }

    /**
     * @return array|bool|mixed
     * @throws NotFoundException
     * @throws Exception
     */
    public function patch()
    {
        $id_e = $this->checkedEntite();
        $id_ce = $this->getFromQueryArgs(2);

        $this->checkedConnecteur($id_e, $id_ce);
        $this->checkConnecteurEdition($id_e);

        $content = $this->getFromQueryArgs(3);
        if ($content == 'content') {
            return $this->patchContent();
        }
        if ($content == 'externalData') {
            return $this->patchExternalData($id_e, $id_ce);
        }


        $libelle = $this->getFromRequest('libelle');
        $frequence_en_minute = $this->getFromRequest('frequence_en_minute', 1);
        $id_verrou = $this->getFromRequest('id_verrou', '');

        if (!$libelle) {
            throw new Exception("Le libellé est obligatoire.");
        }
        $this->connecteurModificationService->editConnecteurLibelle(
            $id_ce,
            $libelle,
            $frequence_en_minute,
            $id_verrou,
            $id_e,
            $this->getUtilisateurId(),
            "Le libellé a été modifié en « $libelle »"
        );

        return $this->detail($id_e, $id_ce);
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function patchContent()
    {
        $id_e = $this->checkedEntite();
        $id_ce = $this->getFromQueryArgs(2);

        $this->connecteurModificationService->editConnecteurFormulaire(
            $id_ce,
            new Recuperateur($this->getRequest()),
            $this->getFileUploader(),
            true,
            $id_e,
            $this->getUtilisateurId(),
            "Modification du connecteur via l'API"
        );

        $result = $this->detail($id_e, $id_ce);
        $result['result'] = self::RESULT_OK;
        return $result;
    }

    /**
     * @throws Exception
     */
    public function postFile($id_e, $id_ce)
    {
        $type = $this->getFromQueryArgs(3);
        if ($type === 'action') {
            return $this->postAction($id_e, $id_ce);
        }

        $field_name = $this->getFromQueryArgs(4);
        $file_number = $this->getFromQueryArgs(5) ?: 0;

        $file_name = $this->getFromRequest('file_name');

        $fileUploader = $this->getFileUploader();
        $file_content = $fileUploader->getFileContent('file_content');
        if (!$file_content) {
            $file_content = $this->getFromRequest('file_content');
        }

        $this->connecteurModificationService->addFileFromData(
            $id_ce,
            $field_name,
            $file_name,
            $file_content,
            $file_number,
            $id_e,
            $this->getUtilisateurId(),
            "Le fichier $field_name a été modifié via l'API"
        );

        return $this->getDetail($id_e, $id_ce);
    }

    /**
     * @param $id_e
     * @param $id_ce
     * @return array
     * @throws Exception
     */
    public function postAction($id_e, $id_ce): array
    {
        $action_name = $this->getFromQueryArgs(4);
        $action_params = $this->getFromRequest('action_params', []);

        $this->checkedConnecteur($id_e, $id_ce);

        $connecteur_entite_info = $this->connecteurEntiteSQL->getInfo($id_ce);

        $id_connecteur = $this->connecteurDefinitionFiles->getInfo($connecteur_entite_info['id_connecteur']);
        if (!$id_connecteur) {
            throw new NotFoundException("Impossible de trouver le connecteur");
        }

        if (!$this->actionPossible->isActionPossibleOnConnecteur($id_ce, $this->getUtilisateurId(), $action_name)) {
            throw new ForbiddenException(
                "L'action « $action_name »  n'est pas permise : " . $this->actionPossible->getLastBadRule()
            );
        }

        //Si l'action n'existe pas, alors on isActionPossibleOnConnecteur passe... C'est mal foutu.
        if (
            !in_array(
                $action_name,
                $this->actionPossible->getActionPossibleOnConnecteur($id_ce, $this->getUtilisateurId())
            )
        ) {
            throw new NotFoundException("L'action $action_name n'existe pas");
        }

        $result = $this->actionExecutorFactory->executeOnConnecteur(
            $id_ce,
            $this->getUtilisateurId(),
            $action_name,
            true,
            $action_params
        );

        return [
            "result" => $result,
            "last_message" => $this->actionExecutorFactory->getLastMessage()
        ];
    }

    /**
     * @throws Exception
     */
    public function postChunk(string $id_e, string $id_ce): array
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
            $id_ce,
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
                $this->connecteurModificationService->addFileFromData(
                    (int) $id_ce,
                    $field_name,
                    $file_name,
                    file_get_contents($upload_filepath),
                    $file_number,
                    $this->getUtilisateurId(),
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
}
