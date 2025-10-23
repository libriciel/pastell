<?php

class TdTRecupHelios extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        /** @var TdtConnecteur $tdT */
        $tdT = $this->getConnecteur("TdT");

        $tdt_error = $this->getMappingValue('tdt-error');
        $info_tdt_action = $this->getMappingValue('info-tdt');
        $has_reponse_element = $this->getMappingValue('pes_has_reponse');
        $fichier_reponse_element = $this->getMappingValue('pes_acquit');
        $fichier_pes = $this->getMappingValue('fichier_pes');


        $tedetis_transaction_id = $this->getDonneesFormulaire()->get($this->getMappingValue('pes_tedetis_transaction_id'));

        if (! $tedetis_transaction_id) {
            $message = "Une erreur est survenue lors de l'envoi à " . $tdT->getLogicielName();
            $this->changeOrUpdateAction($tdt_error, $message);
            $this->notify($tdt_error, $this->type, $message);
            return false;
        }

        $status = $tdT->getStatusHelios($tedetis_transaction_id);

        if ($status === false) {
            $this->setLastMessage($tdT->getLastError());
            return false;
        }

        $status_info = $tdT->getStatusInfo($status);

        if ($status == TdtConnecteur::STATUS_ERREUR) {
            $this->setDocumentToError($tdT, $tdt_error);
            return false;
        }

        $next_message = "La transaction est dans l'état : $status_info ($status) ";

        if (in_array($status, [TdtConnecteur::STATUS_ACQUITTEMENT_RECU,TdtConnecteur::STATUS_REFUSE,TdtConnecteur::STATUS_HELIOS_INFO])) {
            $next_action = $info_tdt_action;
            $next_message = "Une réponse est disponible pour ce fichier PES";
            $this->getDonneesFormulaire()->setData($has_reponse_element, true);
            $retour = $tdT->getFichierRetour($tedetis_transaction_id);
            $pes_aller_filename = $this->getDonneesFormulaire()->getFileName($fichier_pes);
            $fichier_pes_filename = pathinfo($pes_aller_filename, PATHINFO_FILENAME);
            $ack_filename = $fichier_pes_filename . '_ACK.xml';

            $this->getDonneesFormulaire()->addFileFromData($fichier_reponse_element, $ack_filename, $retour);
            $this->changeAction($next_action, $next_message);
            $this->notify($next_action, $this->type, $next_message);
            $this->recupPESAcquitInfo();
        }
        $this->setLastMessage($next_message);
        return true;
    }


    private function setDocumentToError(TdtConnecteur $tdT, $tdt_error)
    {
        $message = 'Transaction en erreur sur le TdT';
        if ($tdT->getLastReponseFile()) {
            try {
                $simpleXMLWrapper = new SimpleXMLWrapper();
                $xml = $simpleXMLWrapper->loadString($tdT->getLastReponseFile());
                if (isset($xml->{'message'})) {
                    $message .= ': ' . $xml->{'message'};
                }
            } catch (SimpleXMLWrapperException $e) {
                /** Nothing to do */
            }
        }
        $this->changeOrUpdateAction($tdt_error, $message);
        $this->notify($tdt_error, $this->type, $message);
        return false;
    }


    /**
     * @throws Exception
     */
    public function recupPESAcquitInfo()
    {
        $heliosGeneriquePESAcquit = new PESAcquitFile();
        $etat_ack = $heliosGeneriquePESAcquit->getEtatAck($this->getDonneesFormulaire()->getFilePath($this->getMappingValue('pes_acquit'))) ? 1 : 2;
        $this->getDonneesFormulaire()->setData($this->getMappingValue('pes_etat_ack'), $etat_ack);
    }
}
