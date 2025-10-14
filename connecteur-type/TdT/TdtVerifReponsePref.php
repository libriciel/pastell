<?php

class TdtVerifReponsePref extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go()
    {
        $acte_transaction_id = $this->getMappingValue('acte_transaction_id');
        $reponse_transaction_id = $this->getMappingValue('reponse_transaction_id');
        $type_reponse = $this->getMappingValue('type_reponse');

        $tdt_error = $this->getMappingValue('tdt-error');
        $erreur_verif_tdt = $this->getMappingValue('erreur-verif-tdt');
        $termine = $this->getMappingValue('termine');

        /** @var TdtConnecteur $tdT */
        $tdT = $this->getConnecteurOrFail('Tdt');

        $acte_transaction_id_element = $this->getDonneesFormulaire()->get($acte_transaction_id);
        $reponse_transaction_id_element = $this->getDonneesFormulaire()->get($reponse_transaction_id);
        $type_reponse_element = $this->getDonneesFormulaire()->get($type_reponse);
        $reponse_de_reponse_transaction_id = 'response_transaction_id';
        $reponse_de_reponse_transaction_id_element = $this->getDonneesFormulaire()->get($reponse_de_reponse_transaction_id);

        $actionCreator = $this->getActionCreator();

        if (( ! $acte_transaction_id_element) || ( ! $reponse_transaction_id_element)) {
            $message = "Une erreur est survenue lors de l'envoi à " . $tdT->getLogicielName() . " (tedetis_transaction_id non disponible)";
            $this->setLastMessage($message);
            $actionCreator->addAction($this->id_e, 0, $tdt_error, $message);
            $this->notify($tdt_error, $this->type, $message);
            return false;
        }

        if (!in_array($type_reponse_element, [TdtConnecteur::DEMANDE_PIECE_COMPLEMENTAIRE, TdtConnecteur::LETTRE_OBSERVATION])) {
            $message = "Ce type de réponse de la préfécture ne prévoit pas d'acquittement";
            $actionCreator->addAction($this->id_e, 0, $termine, $message);
            $this->setLastMessage($message);
            return false;
        }

        try {
            $status = $tdT->getStatus($reponse_de_reponse_transaction_id_element);
        } catch (Exception $e) {
            $message = "Echec de la récupération des informations : " .  $e->getMessage();
            $this->setLastMessage($message);
            return false;
        }

        if ($status == TdtConnecteur::STATUS_ERREUR) {
            $message = "Transaction en erreur sur le TdT : " . $tdT->getLastError();
            $this->setLastMessage($message);
            $this->changeOrUpdateAction($erreur_verif_tdt, $message);
            $this->notify($erreur_verif_tdt, $this->type, $message);
            return false;
        }

        if ($status != TdtConnecteur::STATUS_ACQUITTEMENT_RECU) {
            $this->setLastMessage('La transaction a comme statut : ' . TdtConnecteur::getStatusString($status));
            return true;
        }

        $has_acquittement = $this->getDonneesFormulaire()->get('has_acquittement');
        if ($has_acquittement) {
            return false;
        }

        $numero_acte = $this->getMappingValue('numero_de_lacte');
        $numero_acte_element = $this->getDonneesFormulaire()->get($numero_acte);

        $type = $this->getLibelleType($type_reponse_element);
        $this->getDonneesFormulaire()->setData('has_acquittement', true);
        $this->getDonneesFormulaire()->addFileFromData('acquittement_file', "$numero_acte_element-ar-$type.xml", $tdT->getARActes());

        $message = "Réception d'un message ($type) de la préfecture";
        $this->addActionOK($message);
        $this->notify('verif-reponse-tdt', $this->type, $message);
        return true;
    }

    private function getLibelleType($id_type)
    {
        $txt_message = [
            TdtConnecteur::COURRIER_SIMPLE => 'courrier_simple',
            TdtConnecteur::DEMANDE_PIECE_COMPLEMENTAIRE => 'demande_piece_complementaire',
            TdtConnecteur::LETTRE_OBSERVATION => 'lettre_observation',
            TdtConnecteur::DEFERE_TRIBUNAL_ADMINISTRATIF => 'defere_tribunal_administratif',
            6 => 'annulation'
        ];

        return $txt_message[$id_type];
    }
}
