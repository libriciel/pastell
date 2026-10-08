<?php

class ListeStatutsCPP extends ChoiceActionExecutor
{
    public function go()
    {
        $recuperateur = $this->getRecuperateur();
        $statut_cible_liste = $recuperateur->get('statut_cible_liste');
        $this->getDonneesFormulaire()->setData('statut_cible_liste', $statut_cible_liste);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function displayAPI()
    {
        return $this->getListeStatuts();
    }

    /**
     * @throws Exception
     */
    public function display()
    {
        $this->setViewParameter('statut_cible_liste', $this->getListeStatuts());
        $this->renderPage("Choix d'un nouveau statut", 'module/factureCpp/ListeStatutsCPP');
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getListeStatuts()
    {
        /** @var PortailFactureConnecteur $conn */
        $conn = $this->getConnecteur('PortailFacture');
        $liste_statuts = $conn->getListeStatutCible();
        return $liste_statuts;
    }
}
