<?php

class IparapheurType extends ChoiceActionExecutor
{
    public function go()
    {
        $recuperateur = $this->getRecuperateur();
        $type_iparapheur = $recuperateur->get('iparapheur_type');
        $connecteur_properties = $this->getConnecteurProperties();
        $connecteur_properties->setData('iparapheur_type', $type_iparapheur);
    }

    public function displayAPI()
    {
        return $this->getType();
    }

    /**
     * @throws NotFoundException
     */
    public function display()
    {
        $this->setViewParameter('type_iparapheur', $this->getType());
        $this->renderPage('Choix du type iparapheur', 'connector/iparapheur/IparapheurType');
        return true;
    }

    private function getType()
    {
        /** @var IParapheur $signature */
        $signature = $this->getMyConnecteur();
        return $signature->getType();
    }
}
