<?php

/**
 * @deprecated 4.1.0, unused, will be removed in 5.0
 */
class TdTExtractionHelios extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        $fichier_pes_element = $this->getMappingValue('fichier_pes');

        $info = $this->objectInstancier
            ->getInstance(PESAllerFile::class)
            ->getAllInfo($this->getDonneesFormulaire()->getFilePath($fichier_pes_element));

        $info_to_retrieve =  [
            PESAllerFile::ID_COLL => 'id_coll',
            PESAllerFile::DTE_STR => 'dte_str',
            PESAllerFile::COD_BUD => 'cod_bud',
            PESAllerFile::EXERCICE => 'exercice',
            PESAllerFile::ID_BORD => 'id_bordereau',
            PESAllerFile::ID_PJ => 'id_pj',
            PESAllerFile::ID_PCE => 'id_pce',
            PESAllerFile::ID_NATURE => 'id_nature',
            PESAllerFile::ID_FONCTION => 'id_fonction',
        ];

        foreach ($info_to_retrieve as $pes_element_name => $pastell_element_name) {
            $this->getDonneesFormulaire()->setData($this->getMappingValue($pastell_element_name), $info[$pes_element_name]);
        }
        $this->getDonneesFormulaire()->setData($this->getMappingValue('pes_etat_ack'), 0);
        $this->getDonneesFormulaire()->setData($this->getMappingValue('pes_information_pes_aller'), true);


        $this->addActionOK("Les données ont été extraites du fichier PES ALLER");
        return true;
    }
}
