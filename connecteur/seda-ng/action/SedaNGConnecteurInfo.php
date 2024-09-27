<?php

/**
 * @deprecated 4.1.0, use SedaGenerator instead, to be removed in v5
 */
class SedaNGConnecteurInfo extends ChoiceActionExecutor
{
    public function getMyConnecteurConfig()
    {
        return $this->getConnecteurConfig($this->id_ce);
    }

    /**
     * @throws Exception
     */
    public function go()
    {
        /** @var SedaNG $sedaNG */
        $sedaNG = $this->getMyConnecteur();
        $properties = $sedaNG->getProprietePastellConnecteur();

        $recuperateur = $this->getRecuperateur();
        $data = [];
        foreach ($properties as $property) {
            $data[$property] = $recuperateur->get($property);
        }

        $this->getMyConnecteurConfig()->addFileFromData('connecteur_info_content', "properties.json", json_encode($data));

        $this->getMyConnecteurConfig()->setData('connecteur_info', count($data) . " propriété(s)");
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function display()
    {
        /** @var SedaNG $sedaNG */
        $sedaNG = $this->getMyConnecteur();
        $properties = array_fill_keys($sedaNG->getProprietePastellConnecteur(), '');

        $file_content = $this->getMyConnecteurConfig()->getFileContent('connecteur_info_content');

        if ($file_content) {
            foreach (json_decode($file_content, true) as $property => $value) {
                if (isset($properties[$property])) {
                    $properties[$property] = $value;
                }
            }
        }
        $this->setViewParameter('properties', $properties);

        $this->renderPage(
            'Propriétés « pastell:connecteur » du profil',
            'connector/sedaNg/SedaNGConnecteurProperties'
        );
        return true;
    }

    public function displayAPI()
    {
    }
}
