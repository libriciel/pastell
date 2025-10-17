<?php

class TdTRecupAnnulation extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function go()
    {
        $tedetis_annulation_id = $this->getMappingValue('tedetis_annulation_id');
        $tdt_error = $this->getMappingValue('tdt-error');
        $annuler_tdt = $this->getMappingValue('annuler-tdt');
        $date_ar_annulation = $this->getMappingValue('date_ar_annulation');
        $numero_de_lacte_element = $this->getMappingValue('numero_de_lacte');
        $aractes_annulation = $this->getMappingValue('aractes_annulation');

        /** @var TdtConnecteur $tdT */
        $tdT = $this->getConnecteur('TdT');

        $tedetis_annulation_id_element = $this->getDonneesFormulaire()->get($tedetis_annulation_id);

        if (!$tedetis_annulation_id_element) {
            $message = "Une erreur est survenue lors de l'envoi à " . $tdT->getLogicielName();
            $this->changeOrUpdateAction($tdt_error, $message);
            $this->notify($tdt_error, $this->type, $message);
            return false;
        }

        try {
            $status = $tdT->getStatus($tedetis_annulation_id_element);
        } catch (Exception $e) {
            $message = "Échec de la récupération des informations : " . $e->getMessage();
            $this->setLastMessage($message);
            return false;
        }
        if ($status != TdtConnecteur::STATUS_ACQUITTEMENT_RECU) {
            $this->setLastMessage(
                "La transaction d'annulation a comme statut : " . TdtConnecteur::getStatusString($status)
            );
            return true;
        }
        $this->changeAction($annuler_tdt, "L'acte a été annulé par le contrôle de légalité");

        $donneesFormulaire = $this->getDonneesFormulaire();
        $donneesFormulaire->setData($date_ar_annulation, $tdT->getDateAR($tedetis_annulation_id_element));
        $numero_de_lacte = $donneesFormulaire->get($numero_de_lacte_element);
        $donneesFormulaire->addFileFromData(
            $aractes_annulation,
            "$numero_de_lacte-ar-annulation.xml",
            $tdT->getARActes()
        );

        $message = "L'acquittement pour l'annulation de l'acte a été reçu.";
        $this->notify($annuler_tdt, $this->type, $message);
        $this->setLastMessage($message);
        return true;
    }
}
