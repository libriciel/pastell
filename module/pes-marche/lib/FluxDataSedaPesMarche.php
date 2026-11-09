<?php

class FluxDataSedaPesMarche extends FluxDataSedaDefault
{
    private $info_from_pes_aller;
    private $info_from_pes_retour;

    public function getData($key)
    {
        $method = "get_$key";
        if (method_exists($this, $method)) {
            return $this->$method($key);
        }
        return parent::getData($key);
    }

    /**
     * @return string Contenu de la balise NomFic du fichier PES_ALLER
     */
    public function get_NomFic()
    {
        return $this->getSpecificInfoFromPesAller('NomFic');
    }

    /**
     * @return string empreinte sha1 du contenu du fichier PES_ALLER
     */
    public function get_pesAller_sha1()
    {
        return sha1($this->donneesFormulaire->getFileContent('fichier_pes'));
    }

    /**
     * @return string Contenu de la balise LibelleCodBud
     */
    public function get_LibelleColBud()
    {
        return $this->getSpecificInfoFromPesAller('LibelleColBud');
    }

    /**
     * @return string Domaine (recette, dépense, pj)
     */
    public function get_Domaine()
    {
        $info = $this->getInfoFromPesAller();
        $result = [];
        if ($info['is_recette']) {
            $result[] = "PES_RecetteAller";
        }
        if ($info['is_depense']) {
            $result[] = "PES_DepenseAller";
        }
        if ($info['is_facture']) {
            $result[] = "PES_Facture";
        }
        if ($info['is_marche']) {
            $result[] = "PES_Marche";
        }
        if (! $result && $info['is_pj']) {
            $result[] = "PES_PJ";
        }
        return mb_convert_encoding(implode(", ", $result), 'UTF-8', 'ISO-8859-1');
    }

    public function get_IdContrat()
    {
        return $this->getSpecificInfoFromPesAller('IdContrat');
    }

    public function get_SequenceEnvoi()
    {
        return $this->getSpecificInfoFromPesAller('SequenceEnvoi');
    }

    public function get_IsCorrectif()
    {
        if ($this->getSpecificInfoFromPesAller('is_correctif')) {
            return " - correctif";
        }
        return "";
    }

    public function get_Objet()
    {
        return $this->getSpecificInfoFromPesAller('Objet');
    }

    public function get_url_profil_acheteur()
    {
        return $this->getSpecificInfoFromPesAller('url_profil_acheteur');
    }

    public function get_Libelle_nature_marche()
    {
        return $this->getSpecificInfoFromPesAller('Libelle_nature_marche');
    }

    public function get_Libelle_procedure_marche()
    {
        return $this->getSpecificInfoFromPesAller('Libelle_procedure_marche');
    }

    public function get_accord_cadre()
    {
        return $this->getSpecificInfoFromPesAller('accord_cadre');
    }

    public function get_date_notification()
    {
        return $this->getSpecificInfoFromPesAller('date_notification');
    }

    public function get_Acheteur()
    {
        $result = [];
        $info = $this->getInfoFromPesAller();
        foreach ($info['Acheteur'] as $acheteur) {
            $result[] = "Acheteur $acheteur";
        }
        return $result;
    }

    public function get_Titulaire()
    {
        $result = [];
        $info = $this->getInfoFromPesAller();
        foreach ($info['Titulaire'] as $titulaire) {
            $result[] = "Titulaire $titulaire";
        }
        return $result;
    }

    public function get_IdPost()
    {
        return $this->getSpecificInfoFromPesAller('IdPost');
    }


    public function get_IdColl()
    {
        return $this->getSpecificInfoFromPesAller('IdColl');
    }

    public function get_CodBud()
    {
        return $this->getSpecificInfoFromPesAller('CodBud');
    }


    public function get_date_mandatement_iso_8601()
    {
        $info = $this->getInfoFromPesAller();
        return date("c", strtotime($info['DteStr']));
    }

    public function get_date_mandatement()
    {
        $info = $this->getInfoFromPesAller();
        return date('Y-m-d', strtotime($info['DteStr']));
    }

    private function getSpecificInfoFromPesAller($key)
    {
        $info = $this->getInfoFromPesAller();
        return $info[$key];
    }

    public function get_pes_aller_size_in_byte()
    {
        return filesize($this->donneesFormulaire->getFilePath('fichier_pes'));
    }

    public function get_retour_size_in_byte()
    {
        return filesize($this->donneesFormulaire->getFilePath('fichier_reponse'));
    }

    public function get_start_date()
    {
        $info = $this->getInfoFromPesRetour();
        if (!$info['DteStr']) {
            $info = $this->getInfoFromPesAller();
        }
        return $info['DteStr'];
    }

    public function get_CodcolCodbud()
    {
        $info = $this->getInfoFromPesAller();
        return $info['CodCol'] . $info['CodBud'];
    }


    public function get_date_ack_iso_8601()
    {
        $info = $this->getInfoFromPesRetour();
        return date("c", strtotime($info['DteStr']));
    }

    public function get_date_acquittement()
    {
        $info = $this->getInfoFromPesRetour();
        if (!$info['DteStr']) {
            $info = $this->getInfoFromPesAller();
        }
        return date('Y-m-d', strtotime($info['DteStr']));
    }

    private function getInfoFromPesAller()
    {
        if (! $this->info_from_pes_aller) {
            $PESMarcheInfo = new PESMarcheInfo();
            $pes_aller_path = $this->donneesFormulaire->getFilePath('fichier_pes');
            $this->info_from_pes_aller = $PESMarcheInfo->getAllInfo($pes_aller_path);
        }
        return $this->info_from_pes_aller;
    }

    public function getInfoFromPesRetour()
    {
        if (! $this->info_from_pes_retour) {
            $pes_retour = $this->donneesFormulaire->getFilePath('fichier_reponse');
            $this->info_from_pes_retour = $this->extractInfoFromPESRetour($pes_retour);
        }
        return $this->info_from_pes_retour;
    }


    private function extractInfoFromPESRetour($pes_retour)
    {
        $pes_retour_content = file_get_contents($pes_retour);
        $xml =  simplexml_load_string($pes_retour_content, 'SimpleXMLElement', LIBXML_PARSEHUGE);

        $info = [];
        $info['DteStr'] =  strval($xml->EnTetePES->DteStr['V']);

        return $info;
    }
}
