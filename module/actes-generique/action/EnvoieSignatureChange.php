<?php

class EnvoieSignatureChange extends ActionExecutor
{
    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function getConnecteurSignature(): SignatureConnecteur
    {
        /** @var SignatureConnecteur $connector */
        $connector = $this->getConnecteur('signature');
        return $connector;
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function go()
    {
        if ($this->getDonneesFormulaire()->get('envoi_signature_check')) {
            $this->getDonneesFormulaire()->setData('envoi_signature', true);
            $fast_parapheur = $this->getConnecteurSignature()->isFastSignature();

            $this->getDonneesFormulaire()->setData('envoi_signature', !$fast_parapheur);
            $this->getDonneesFormulaire()->setData('envoi_signature_fast', $fast_parapheur);
        } else {
            $this->getDonneesFormulaire()->setData('envoi_signature', false);
            $this->getDonneesFormulaire()->setData('envoi_signature_fast', false);

            return;
        }

        $recuperateur = new Recuperateur($_POST);
        if ($recuperateur->get('suivant') || $recuperateur->get('precedent')) {
            return;
        }

        $tab_name = $fast_parapheur ? 'Parapheur FAST' : 'Parapheur';
        $page = $this->getFormulaire()->getTabNumber($tab_name);
        $this->redirect("/Document/edition?id_d={$this->id_d}&id_e={$this->id_e}&page=$page");
    }
}
