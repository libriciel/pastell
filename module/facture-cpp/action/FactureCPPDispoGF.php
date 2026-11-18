<?php

class FactureCPPDispoGF extends ActionExecutor
{
    protected function metier()
    {
        $this->addActionOK('Affectation de l\'état "Mise à disposition GF"');
    }

    public function go()
    {
        $donneesFormulaire = $this->getDonneesFormulaire();

        if ($donneesFormulaire->get('has_mise_a_dispo_gf') == true) {
            $message = 'La facture est déja passée dans l\'état "Mise à disposition GF"';
            $this->changeAction('modification', $message);
            return false;
        }

        $this->metier();
        $donneesFormulaire->setData('has_mise_a_dispo_gf', true);
        $message = 'Affectation de l\'état "Mise à disposition GF"';
        $this->setLastMessage($message);
        $this->notify($this->action, $this->type, $message);
        return true;
    }
}
