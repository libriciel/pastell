<?php

class TdTFichierPESChange extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        $fichierPESElement = $this->getMappingValue('fichier_pes');
        $objetPESElement = $this->getMappingValue('objet_pes');

        if ($this->getDonneesFormulaire()->get($fichierPESElement) === false) {
            return true;
        }

        $info = $this->objectInstancier
            ->getInstance(PESAllerFile::class)
            ->getAllInfo($this->getDonneesFormulaire()->getFilePath($fichierPESElement));

        if (! $this->getDonneesFormulaire()->get($objetPESElement)) {
            $this->getDonneesFormulaire()->setData($objetPESElement, $info[PESAllerFile::NOM_FIC]);
            $this->getDocument()->setTitre($this->id_d, $info[PESAllerFile::NOM_FIC]);
        }

        return true;
    }
}
