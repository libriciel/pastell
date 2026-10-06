<?php

class TedetisEnvoie extends ActionExecutor
{
    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws Exception
     */
    public function go()
    {
        /** @var TdtConnecteur $tdT */
        $tdT = $this->getConnecteur("TdT");
        try {
            $act = $this->getTdtActes($this->getDonneesFormulaire());
            $transactionId = $tdT->sendActes($act);
            $this->getDonneesFormulaire()->setData('tedetis_transaction_id', $transactionId);
        } catch (Exception $e) {
            if ($this->id_worker) {
                $message = "Erreur lors de l'envoi au Tdt : " . $e->getMessage();
                $this->changeAction("send-tdt-erreur", $message);
                $this->notify("send-tdt-erreur", $this->type, $message);
            }
            throw $e;
        }

        $tdtConfig = $this->getConnecteurConfigByType("TdT");
        if ($tdtConfig->get('authentication_for_teletransmisson')) {
            $message = "Le document a été envoyé au TdT, en attente du certificat RGS**";
            $this->changeAction("document-transmis-tdt", $message);
            $this->notify("document-transmis-tdt", $this->type, $message);
        } else {
            $this->addActionOK("Le document a été envoyé au contrôle de légalité");
            $this->notify($this->action, $this->type, "Le document a été envoyé au contrôle de légalité");
        }

        return true;
    }

    public function goLot(array $all_id_d)
    {
        $tdt_config = $this->getConnecteurConfigByType("TdT");
        if (! $tdt_config->get("forward_x509_certificate")) {
            parent::goLot($all_id_d);
            return;
        }

        foreach ($all_id_d as $id_d) {
            try {
                $this->clearCache();
                $this->setDocumentId($this->type, $id_d);
                $this->go();
            } catch (Exception $e) {
                $this->getJournalEntryService()->add(JournalEventType::DOCUMENT_TRAITEMENT_LOT, $this->id_e, $id_d, $this->action, "Erreur lors du traitement par lot de $id_d : " . $e->getMessage());
            }
        }
        $this->setJobManagerForLot($all_id_d);
    }

    private function getTdtActes(DonneesFormulaire $donneesFormulaire): TdtActes
    {
        $acte = new TdtActes();
        $acte->acte_nature = $donneesFormulaire->get('acte_nature');
        $acte->numero_de_lacte = $donneesFormulaire->get('numero_de_lacte');
        $acte->objet = $donneesFormulaire->get('objet');
        $acte->date_de_lacte = $donneesFormulaire->get('date_de_lacte');
        $acte->document_papier = $donneesFormulaire->get('document_papier');
        $acte->type_acte = $donneesFormulaire->get('type_acte');
        $acte->type_pj = $donneesFormulaire->get('type_pj');
        $acte->classification = $donneesFormulaire->get('classification');

        if ($donneesFormulaire->get('is_pades')) {
            $field = 'signature';
        } else {
            $field = 'arrete';
        }
        $acte->arrete = new Fichier();
        $acte->arrete->filename = $donneesFormulaire->getFileName($field);
        $acte->arrete->filepath = $donneesFormulaire->getFilePath($field);
        $acte->arrete->content = $donneesFormulaire->getFileContent($field);
        $acte->arrete->contentType = $donneesFormulaire->getContentType($field);

        $annexes = [];
        $annexesField = 'autre_document_attache';
        if ($donneesFormulaire->get($annexesField)) {
            for ($i = 0, $annexesNumber = \count($donneesFormulaire->get($annexesField)); $i < $annexesNumber; ++$i) {
                $annexe = new Fichier();
                $annexe->filename = $donneesFormulaire->getFileName($annexesField, $i);
                $annexe->filepath = $donneesFormulaire->getFilePath($annexesField, $i);
                $annexe->content = $donneesFormulaire->getFileContent($annexesField, $i);
                $annexe->contentType = $donneesFormulaire->getContentType($annexesField, $i);
                $annexes[] = $annexe;
            }
        }
        $acte->autre_document_attache = $annexes;

        return $acte;
    }
}
