<?php

class CppSynchroniserStatut extends ActionExecutor
{
    public function go()
    {
        try {
            $result_synchro = $this->metier();

            /** @var PortailFactureConnecteur $portailFactureConnecteur */
            $portailFactureConnecteur = $this->getConnecteur('PortailFacture');
            $synchronisationFacture = new SynchronisationFacture($portailFactureConnecteur);

            $result_synchro = $synchronisationFacture->formatResultSynchro($result_synchro);

            //$this->getJournalEntryService()->addSQL(JournalEventType::DOCUMENT_ACTION, $this->id_e, $this->id_u, $this->id_d, 'synchroniser-statut', $result_synchro);
            $this->setLastMessage($result_synchro);
        } catch (Exception $e) {
            $this->setLastMessage('ERREUR : ' . $e->getMessage());
            return false;
        }
        return true;
    }

    protected function metier()
    {
        /** @var PortailFactureConnecteur $portailFactureConnecteur */
        $portailFactureConnecteur = $this->getConnecteur('PortailFacture');
        $synchronisationFacture = new SynchronisationFacture($portailFactureConnecteur);

        return $synchronisationFacture->getSynchroDocumentFacture($this->getDonneesFormulaire(), false);
    }
}
