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

        $info_to_retrieve =  [
            PESAllerFile::ID_COLL => 'id_coll',
            PESAllerFile::DTE_STR => 'dte_str',
            PESAllerFile::COD_BUD => 'cod_bud',
            PESAllerFile::EXERCICE => 'exercice',
            PESAllerFile::ID_BORD => 'id_bordereau',
            PESAllerFile::MT_BORD_HT => 'montant_bordereau_ht',
            PESAllerFile::ID_PJ => 'id_pj',
            PESAllerFile::ID_PCE => 'id_pce',
            PESAllerFile::ID_NATURE => 'id_nature',
            PESAllerFile::ID_FONCTION => 'id_fonction',
        ];

        if ($this->getDonneesFormulaire()->get($fichierPESElement) === false) {
            foreach ($info_to_retrieve as $pes_element_name => $pastell_element_name) {
                $this->getDonneesFormulaire()->setData(
                    $this->getMappingValue($pastell_element_name),
                    ''
                );
            }
            return true;
        }

        $info = $this->objectInstancier
            ->getInstance(PESAllerFile::class)
            ->getAllInfo($this->getDonneesFormulaire()->getFilePath($fichierPESElement));

        if (! $this->getDonneesFormulaire()->get($objetPESElement)) {
            $this->getDonneesFormulaire()->setData($objetPESElement, $info[PESAllerFile::NOM_FIC]);
            $this->getDocument()->setTitre($this->id_d, $info[PESAllerFile::NOM_FIC]);
        }

        foreach ($info_to_retrieve as $pes_element_name => $pastell_element_name) {
            $this->getDonneesFormulaire()->setData(
                $this->getMappingValue($pastell_element_name),
                $info[$pes_element_name]
            );
        }
        $this->getDonneesFormulaire()->setData($this->getMappingValue('pes_etat_ack'), 0);
        $this->getDonneesFormulaire()->setData($this->getMappingValue('pes_information_pes_aller'), true);

        return true;
    }
}
