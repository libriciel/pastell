<?php

class FactureCPPRecupGFChange extends ActionExecutor
{
    public function go()
    {

        $recup_par_gf = $this->getDonneesFormulaire()->get('recup_par_gf');
        if ($recup_par_gf) {
            $message = '"La GF a indiqué avoir récupéré la facture" passe à Oui';
        } else {
            $message = '"La GF a indiqué avoir récupéré la facture" passe à Non';
        }
        $this->getJournalEntryService()->addSQL(JournalEventType::DOCUMENT_ACTION, $this->id_e, $this->id_u, $this->id_d, 'recup-par-gf-change', $message);
        $this->setLastMessage($message);
        $this->notify($this->action, $this->type, $message);
        return true;
    }
}
