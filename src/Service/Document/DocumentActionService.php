<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use Action;
use ActionChange;
use ActionExecutorFactory;
use ActionPossible;
use DocumentSQL;
use DocumentTypeFactory;
use DonneesFormulaireFactory;
use Exception;
use FatalError;
use JobManager;
use NotFoundException;
use TdtRetourTeletransmettre;
use UnrecoverableException;

final class DocumentActionService
{
    private const NO_ACTION_EXECUTED = "<br/><br/>Aucune action n'a été executée";
    private const NO_DOCUMENT_SELECTED = 'Vous devez sélectionner au moins un document';

    public function __construct(
        private readonly ActionExecutorFactory $actionExecutorFactory,
        private readonly ActionPossible $actionPossible,
        private readonly ActionChange $actionChange,
        private readonly JobManager $jobManager,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly DocumentSQL $documentSQL,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly DocumentRoleService $documentRoleService,
        private readonly DocumentEntiteService $documentEntiteService,
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function execute(int $id_e, int $id_u, string $id_d, string $action, array $recipient_id_e_list = []): string
    {
        $result = $this->actionExecutorFactory->executeOnDocument(
            $id_e,
            $id_u,
            $id_d,
            $action,
            $recipient_id_e_list
        );
        $message = (string) $this->actionExecutorFactory->getLastMessage();
        if ($action === FatalError::ACTION_ID) {
            $this->jobManager->deleteDocumentForAllEntities($id_d);
        }
        if (!$result) {
            throw new UnrecoverableException($message);
        }
        return $message;
    }

    /**
     * @throws NotFoundException
     */
    public function executeOnChange(int $id_e, int $id_u, string $id_d, string $field_name): void
    {
        $field = $this->donneesFormulaireFactory->get($id_d)->getFormulaire()->getField($field_name);
        if (!$field || !$field->getOnChange()) {
            return;
        }
        $this->actionExecutorFactory->executeOnDocument($id_e, $id_u, $id_d, $field->getOnChange());
    }

    /**
     * @throws UnrecoverableException
     */
    public function changeState(int $id_e, int $id_u, string $id_d, string $action, string $message): void
    {
        if (!$this->documentRoleService->getRole($id_e, $id_d)) {
            throw new UnrecoverableException("Le document $id_d n'appartient pas à l'entité $id_e");
        }
        $type = $this->documentSQL->getInfo($id_d)['type'];
        if (!\in_array($action, $this->getDocumentTypeAction($type)->getAll(), true)) {
            throw new UnrecoverableException("L'action $action n'existe pas");
        }

        $this->actionChange->addAction($id_d, $id_e, $id_u, $action, "Modification manuelle de l'état - $message");
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function executeTeletransmissionReturn(
        int $id_e,
        int $id_u,
        string $type,
        array $id_d_list,
        string $action,
    ): string {
        $documentTypeAction = $this->getDocumentTypeAction($type);
        if ($documentTypeAction->getActionClass($action) !== TdtRetourTeletransmettre::class) {
            throw new UnrecoverableException("Cette action n'est pas un retour de télétransmission");
        }
        $document_info_list = $this->getDocumentInfoList($id_e, $id_d_list);
        $this->checkDocumentInfoListType($type, $document_info_list);
        $action_label = $documentTypeAction->getActionName($action);

        $message = '';
        $possible_id_d_list = [];
        foreach ($document_info_list as $document_info) {
            if ($this->actionPossible->isActionPossible($id_e, $id_u, $document_info['id_d'], $action)) {
                $possible_id_d_list[] = $document_info['id_d'];
            } else {
                $document_title = $this->getDocumentTitle($document_info);
                $message .= "L'action « $action_label » n'est pas possible pour le document « $document_title »<br/>";
            }
        }
        if ($possible_id_d_list) {
            $this->actionExecutorFactory->executeLotDocument($id_e, $id_u, $possible_id_d_list, $action);
            $message .= (string) $this->actionExecutorFactory->getLastMessage();
        }
        return $message;
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function getDocumentsForBatchAction(
        int $id_e,
        int $id_u,
        string $type,
        array $id_d_list,
        string $action,
    ): array {
        return $this->checkBatchDocuments(
            $id_e,
            $id_u,
            $type,
            $id_d_list,
            $action,
            $this->getDocumentTypeAction($type)->getActionName($action)
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function executeBatch(int $id_e, int $id_u, string $type, array $id_d_list, string $action): string
    {
        $action_label = $this->getDocumentTypeAction($type)->getDoActionName($action);
        $document_info_list = $this->checkBatchDocuments($id_e, $id_u, $type, $id_d_list, $action, $action_label);
        $this->actionExecutorFactory->executeLotDocument($id_e, $id_u, $id_d_list, $action);

        $message = '';
        foreach ($document_info_list as $document_info) {
            $document_title = $this->getDocumentTitle($document_info);
            $message .= "L'action « $action_label » est programmée pour le document « $document_title »<br/>";
        }
        return $message;
    }

    /**
     * @throws Exception
     */
    public function addPossibleBatchActions(int $id_e, int $id_u, array $document_list): array
    {
        foreach ($document_list as $i => $document_info) {
            $document_list[$i]['action_possible'] = $this->actionPossible->getActionPossibleLot(
                $id_e,
                $id_u,
                $document_info['id_d']
            );
        }
        return $document_list;
    }

    public function getAllPossibleBatchActions(array $document_list): array
    {
        $action_list = [];
        foreach ($document_list as $document_info) {
            $action_list = array_merge($action_list, $document_info['action_possible']);
        }
        return array_unique($action_list);
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    private function checkBatchDocuments(
        int $id_e,
        int $id_u,
        string $type,
        array $id_d_list,
        string $action,
        string $action_label,
    ): array {
        $document_info_list = $this->getDocumentInfoList($id_e, $id_d_list);
        $this->checkDocumentInfoListType($type, $document_info_list);

        $error = '';
        foreach ($document_info_list as $document_info) {
            $id_d = $document_info['id_d'];
            $document_title = $this->getDocumentTitle($document_info);
            if (!$this->actionPossible->isActionPossible($id_e, $id_u, $id_d, $action)) {
                $error .= "L'action « $action_label » n'est pas possible pour le document « $document_title »<br/>";
            }
            if ($this->jobManager->hasActionProgramme($id_e, $id_d)) {
                $error .= "Il y a déjà une action programmée pour le document « $document_title »<br/>";
            }
        }
        if ($error) {
            throw new UnrecoverableException($error . self::NO_ACTION_EXECUTED);
        }
        return $document_info_list;
    }

    private function getDocumentTypeAction(string $type): Action
    {
        return $this->documentTypeFactory->getFluxDocumentType($type)->getAction();
    }

    /**
     * @throws UnrecoverableException
     */
    private function getDocumentInfoList(int $id_e, array $id_d_list): array
    {
        if (!$id_d_list) {
            throw new UnrecoverableException(self::NO_DOCUMENT_SELECTED);
        }
        $document_info_list = [];
        foreach ($id_d_list as $id_d) {
            $id_d = (string) $id_d;
            $document_info_list[] = $this->documentEntiteService->getInfo($id_e, $id_d) ?? ['id_d' => $id_d];
        }
        return $document_info_list;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkDocumentInfoListType(string $type, array $document_info_list): void
    {
        $error = '';
        foreach ($document_info_list as $document_info) {
            if (($document_info['type'] ?? null) !== $type) {
                $document_title = $this->getDocumentTitle($document_info);
                $error .= "Le document « $document_title » n'est pas de type « $type »<br/>";
            }
        }
        if ($error) {
            throw new UnrecoverableException($error . self::NO_ACTION_EXECUTED);
        }
    }

    private function getDocumentTitle(array $document_info): string
    {
        return ($document_info['titre'] ?? '') ?: (string) $document_info['id_d'];
    }
}
