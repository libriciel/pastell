<?php

class TdtRetourTeletransmettre extends ActionExecutor
{
    public function go()
    {

        $stringMapper = $this->getDocumentType()->getAction()->getConnecteurMapper($this->action);

        $recuperateur = new Recuperateur($_GET);
        $error = $recuperateur->get("error");
        $message = $recuperateur->get("message");
        if ($error) {
            throw new Exception("Erreur sur le Tdt : " . $message);
        }

        /** @var TdtConnecteur $tdt */
        $tdt = $this->getConnecteur("TdT");

        $tedetis_transaction_id = $this->getDonneesFormulaire()->get($stringMapper->get('tedetis_transaction_id'));

        $status =  $tdt->getStatus($tedetis_transaction_id);

        //A priori, c'est le seul cas que je vois ou la transaction n'a pas encore été posté
        if (in_array($status, [TdtConnecteur::STATUS_ACTES_EN_ATTENTE_DE_POSTER])) {
            throw new Exception("La transaction n'a pas le bon statut : " . TdtConnecteur::getStatusString($status) . " trouvé") ;
        }

        $this->addActionOK("Ordre de télétransmission envoyé sur le TDT");
        $this->notify($this->action, $this->type, "Ordre de télétransmission envoyé sur le TDT");

        return true;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws Exception
     */
    public function goLot(array $all_id_d): void
    {
        $stringMapper = $this->getDocumentType()->getAction()->getConnecteurMapper($this->action);

        /** @var TdtConnecteur $tdtConnecteur */
        $tdtConnecteur = $this->getConnecteur('TdT');
        $jobManager = $this->objectInstancier->getInstance(JobManager::class);

        $message = '';
        foreach ($all_id_d as $id_d) {
            $document_title = $this->getDocument()->getInfo($id_d)['titre'];
            $transaction_id = $this->getDonneesFormulaireFactory()
                ->get($id_d)
                ->get($stringMapper->get('tedetis_transaction_id'));
            $status = $tdtConnecteur->getStatus($transaction_id);

            if ((int) $status === TdtConnecteur::STATUS_ACTES_EN_ATTENTE_DE_POSTER) {
                $message .= \sprintf(
                    "La transaction pour le document « %s » n'a pas le bon status : %s trouvé<br/>",
                    $document_title,
                    TdtConnecteur::getStatusString($status)
                );
            } else {
                $this->getActionChange()->addAction(
                    $id_d,
                    $this->id_e,
                    $this->id_u,
                    $stringMapper->get('send-tdt'),
                    'Le document a été télétransmis à la préfecture',
                );
                $message .= "Le document « $document_title » a été télétransmis<br/>";
            }

            $jobManager->setJobForDocument($this->id_e, $id_d, 'suite traitement par lot');
        }
        $this->setLastMessage($message);
    }
}
