<?php

class GEDEnvoyer extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        $action_for_unrecoverable_error = $this->getMappingValue(FatalError::ACTION_ID);
        $has_ged_document_id = $this->getMappingValue('has_ged_document_id');
        $ged_document_id_file = $this->getMappingValue('ged_document_id_file');

        $donneesFormulaire = $this->getDonneesFormulaire();
        /** @var GEDConnecteur $ged */
        $ged = $this->getConnecteur("GED");

        try {
            $result = $ged->send($donneesFormulaire);
        } catch (UnrecoverableException $e) {
            $this->changeAction($action_for_unrecoverable_error, $e->getMessage());
            $this->notify(
                $action_for_unrecoverable_error,
                $this->type,
                "Erreur lors du dépot: " . $e->getMessage()
            );
            return false;
        } catch (RecoverableException $e) {
            $this->setLastMessage($e->getMessage());
            return false;
        }

        if (!empty($result)) {
            $donneesFormulaire->setData($has_ged_document_id, true);
            $donneesFormulaire->addFileFromData($ged_document_id_file, 'ged_document_id.json', json_encode($result));
        }

        $message = sprintf(
            'Le dossier %s a été versé sur le dépôt',
            $this->getDonneesFormulaire()->getTitre()
        );

        $this->addActionOK($message);
        $this->notify($this->action, $this->type, $message);

        return true;
    }
}
