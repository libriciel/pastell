<?php

namespace Pastell\Service\Connecteur;

use ConnecteurEntiteSQL;
use DocumentTypeFactory;
use DonneesFormulaireFactory;
use ActionExecutorFactory;
use Exception;
use FileUploader;
use Recuperateur;
use UnrecoverableException;

class ConnecteurModificationService
{
    private $lastMessage = '';

    public function __construct(
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly ActionExecutorFactory $actionExecutorFactory,
        private readonly ConnecteurActionService $connecteurActionService
    ) {
    }

    public function getLastMessage(): string
    {
        return $this->lastMessage;
    }

    public function setLastMessage(string $message): void
    {
        $this->lastMessage = $message;
    }

    /**
     * @throws Exception
     */
    public function editConnecteurLibelle(
        int $id_ce,
        string $libelle,
        int $frequence_en_minute = 1,
        string $id_verrou = '',
        int $id_e = 0,
        int $id_u = 0,
        string $message = ''
    ): void {

        $this->connecteurEntiteSQL->edit($id_ce, $libelle, $frequence_en_minute, $id_verrou);
        $this->connecteurActionService->add(
            $id_e,
            $id_u,
            $id_ce,
            '',
            ConnecteurActionService::ACTION_MODIFFIE,
            $message
        );
    }

    /**
     * @param int $id_ce
     * @param Recuperateur $recuperateur
     * @param FileUploader $fileUploader
     * @param bool $from_api
     * @param int $id_e
     * @param int $id_u
     * @param string $message
     * @return bool
     * @throws Exception
     */
    public function editConnecteurFormulaire(
        int $id_ce,
        Recuperateur $recuperateur,
        FileUploader $fileUploader,
        bool $from_api = false,
        int $id_e = 0,
        int $id_u = 0,
        string $message = ''
    ): bool {

        $result = true;

        $donneesFormulaire = $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce);
        if (! $from_api) {
            $donneesFormulaire->saveTab($recuperateur, $fileUploader, 0);
        } else {
            $donneesFormulaire->setTabDataVerif($recuperateur->getAll());
            $donneesFormulaire->saveAllFile($fileUploader);
        }

        foreach ($donneesFormulaire->getOnChangeAction() as $action) {
            $result = $this->actionExecutorFactory->executeOnConnecteur($id_ce, $id_u, $action, $from_api);
            if (! $result) {
                $this->setLastMessage($this->actionExecutorFactory->getLastMessage());
            }
        }

        if ($donneesFormulaire->isModified()) {
            $this->connecteurActionService->add(
                $id_e,
                $id_u,
                $id_ce,
                '',
                ConnecteurActionService::ACTION_MODIFFIE,
                $message
            );
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function addFileFromData(
        int $id_ce,
        string $field_name,
        string $file_name,
        string $file_content,
        int $file_number = 0,
        int $id_e = 0,
        int $id_u = 0,
        string $message = ''
    ): void {

        $donneesFormulaire = $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce);
        $donneesFormulaire->addFileFromData($field_name, $file_name, $file_content, $file_number);

        $this->connecteurActionService->add(
            $id_e,
            $id_u,
            $id_ce,
            '',
            ConnecteurActionService::ACTION_MODIFFIE,
            $message
        );
    }

    /**
     * @throws Exception
     */
    public function removeFile(
        int $id_ce,
        string $field_name,
        int $file_number = 0,
        int $id_e = 0,
        int $id_u = 0,
        string $message = ''
    ): void {

        $donneesFormulaire = $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce);
        $donneesFormulaire->removeFile($field_name, $file_number);
        $this->connecteurActionService->add(
            $id_e,
            $id_u,
            $id_ce,
            '',
            ConnecteurActionService::ACTION_MODIFFIE,
            $message
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function addExternalData(
        int $id_ce,
        string $field_name,
        int $id_u = 0,
        string $message = '',
        bool $from_api = false,
        array $post_data = []
    ): bool {
        $connecteur_info = $this->connecteurEntiteSQL->getInfo($id_ce);
        $id_e = $connecteur_info['id_e'];

        $documentType = ($connecteur_info['global']) ?
            $this->documentTypeFactory->getGlobalDocumentType($connecteur_info['id_connecteur'])
            : $this->documentTypeFactory->getEntiteDocumentType($connecteur_info['id_connecteur']);

        $theField = $documentType->getFormulaire()->getField($field_name);
        if (!$theField) {
            throw new UnrecoverableException("Type $field_name introuvable");
        }
        $action_name = $theField->getProperties('choice-action');
        $result = $this->actionExecutorFactory
            ->goChoiceOnConnecteur($id_ce, $id_u, $action_name, $field_name, $from_api, $post_data);
        if (!$result) {
            $this->setLastMessage($this->actionExecutorFactory->getLastMessage());
        }
        $this->connecteurActionService->add(
            $id_e,
            $id_u,
            $id_ce,
            '',
            ConnecteurActionService::ACTION_MODIFFIE,
            $message
        );
        return $result;
    }
}
