<?php

declare(strict_types=1);

class SAEValider extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go(): bool
    {
        $sae_transfert_id_element = $this->getMappingValue('sae_transfert_id');
        $reply_sae_element = $this->getMappingValue('reply_sae');
        $action_name_error_envoi = $this->getMappingValue('erreur-envoie-sae');
        $action_name_error_validation = $this->getMappingValue('validation-sae-erreur');
        $action_name_accepter = $this->getMappingValue('accepter-sae');
        $action_name_rejet = $this->getMappingValue('rejet-sae');
        $sae_atr_comment_element = $this->getMappingValue('sae_atr_comment');
        $sae_archival_identifier_element = $this->getMappingValue('sae_archival_identifier');
        $sae_bordereau = $this->getMappingValue('sae_bordereau');

        /** @var SAEConnecteur $sae */
        $sae = $this->getConnecteur('SAE');

        $donneesFormulaire = $this->getDonneesFormulaire();

        $sedaHelper = $sae->getSedaHelper();

        $simpleXMLWrapper = new SimpleXMLWrapper();
        $xml = $simpleXMLWrapper->loadString($donneesFormulaire->getFileContent($sae_bordereau));
        $originating_agency_id = $sedaHelper->getOriginatingAgency($xml);

        $id_transfert = $donneesFormulaire->get($sae_transfert_id_element);

        if (!$id_transfert) {
            $message = "Impossible de trouver l'identifiant du transfert";
            $this->setLastMessage($message);
            $this->changeOrUpdateAction($action_name_error_envoi, $message);
            $this->notify($this->action, $this->type, $message);
            return false;
        }

        try {
            $atr_content = $sae->getAtr($id_transfert, $originating_agency_id);
        } catch (UnrecoverableException $e) {
            $this->changeAction($action_name_error_validation, 'Erreur irrécupérable : ' . $e->getMessage());
            throw $e;
        }

        $donneesFormulaire->addFileFromData($reply_sae_element, 'ATR_unknown.xml', $atr_content);

        $simpleXMLWrapper = new SimpleXMLWrapper();
        $xml = $simpleXMLWrapper->loadString($atr_content);

        $atr_id = $sedaHelper->getAtrID($xml);

        $atr_name = sprintf('%s.xml', $atr_id);
        $donneesFormulaire->addFileFromData($reply_sae_element, $atr_name, $atr_content);

        $donneesFormulaire->setData($sae_atr_comment_element, $sedaHelper->getComment($xml));

        if (! $sedaHelper->isSIPAccepted($xml)) {
            $replyCode = $sedaHelper->getReplyCode($xml);
            $commentaire = $donneesFormulaire->get($sae_atr_comment_element);

            if ($replyCode !== '') {
                $commentaire .= " (Archive refusée - code de retour : $replyCode)";
            } else {
                $commentaire .= ' (Archive refusée)';
            }
            $donneesFormulaire->setData($sae_atr_comment_element, $commentaire);
            $this->addActionAndNotify($action_name_rejet, "La transaction a été refusée par le SAE. $commentaire");
            return false;
        }

        $sae_archival_identifier = $sedaHelper->getSAEArchivalIdentifierFromAtr($xml);
        $donneesFormulaire->setData($sae_archival_identifier_element, $sae_archival_identifier);

        $this->addActionAndNotify($action_name_accepter, 'La transaction a été acceptée par le SAE');
        return true;
    }

    private function addActionAndNotify(string $nextAction, string $message): void
    {
        $this->changeAction($nextAction, $message);
        $this->notify($nextAction, $this->type, $message);
    }
}
