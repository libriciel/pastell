<?php

class PESMarcheInfo extends PESV2XMLFile
{
    public const NOM_FIC = 'NomFic';
    public const ID_COLL = 'IdColl';
    public const DTE_STR = 'DteStr';
    public const COD_BUD = 'CodBud';

    public const ID_CONTRAT = 'IdContrat';

    public const ID_PJ = 'IdPJ';

    /**
     * @param $pes_aller_path
     * @return array
     * @throws Exception
     */
    public function getAllInfo($pes_aller_path)
    {
        $xml = $this->getSimpleXMLFromFile($pes_aller_path);

        $info = [];

        // EnTetePES
        $info[self::NOM_FIC] = $this->getValueFromXPath($xml, "//Enveloppe/Parametres/NomFic/@V");
        $info[self::ID_COLL] = $this->getValueFromXPath($xml, "//EnTetePES/IdColl/@V");
        $info[self::DTE_STR] = $this->getValueFromXPath($xml, "//EnTetePES/DteStr/@V");
        $info[self::COD_BUD] = $this->getValueFromXPath($xml, "//EnTetePES/CodBud/@V");
        $info['IdPost'] = $this->getValueFromXPath($xml, "//EnTetePES/IdPost/@V");
        $info['CodCol'] = $this->getValueFromXPath($xml, "//EnTetePES/CodCol/@V");
        $info['LibelleColBud'] = $this->getValueFromXPath($xml, "//EnTetePES/LibelleColBud/@V");
        if (! $info['LibelleColBud']) {
            $info['LibelleColBud'] =  $info['CodCol'];
        }

        // DOMAINE
        $info['is_recette'] = isset($xml->PES_RecetteAller);
        $info['is_depense'] = isset($xml->PES_DepenseAller);
        $info['is_pj'] = isset($xml->PES_PJ);
        $info['is_facture'] = isset($xml->PES_Facture);
        $info['is_marche'] = isset($xml->PES_Marche);
        $info['is_concession'] = false;
        $zone_id_marche = null;
        $zone_marche = null;
        if ($info['is_marche']) {
            if (isset($xml->PES_Marche->Marches)) {
                $zone_marche = $xml->PES_Marche->Marches->Marche;
                $zone_id_marche = $zone_marche->IdentifiantMarche;
            } else {
                $info['is_concession'] = true;
                $zone_marche = $xml->PES_Marche->Concessions->Concession;
                $zone_id_marche = $zone_marche->IdentifiantConcession;
            }
        }

        //PES_Marche
        $info[self::ID_CONTRAT] = '';
        $info[self::ID_PJ] = '';

        if ($info['is_marche']) {
            $info[self::ID_CONTRAT] = strval($zone_id_marche->IdContrat['V']);
            $info['SequenceEnvoi'] = strval($zone_id_marche->SequenceEnvoi['V']);
            $info['is_correctif'] = strval($zone_id_marche->CaractereCorrectifEnregistrement['V']);

            $info['Objet'] = strval($zone_marche->Objet['V']);
            $info['url_profil_acheteur'] = strval($zone_marche->URLProfilAcheteur['V']);
            $info['Libelle_nature_marche'] = $this->getNatureMarche($info['is_concession'], $zone_marche);
            $info['Libelle_procedure_marche'] = $this->getProcedureMarche($info['is_concession'], $zone_marche);

            $info['Acheteur'] = [];
            $info['Acheteur'] = $this->getAcheteur($info['is_concession'], $zone_marche);

            $info['Titulaire'] = [];
            $info['accord_cadre'] = "";
            $info['date_notification'] = "";
            if (!$info['is_concession']) {
                $info['Titulaire'] = $this->getTitulaire($info['is_concession'], $zone_marche);
                $info['accord_cadre'] = strval($zone_marche->ConditionsExecution->IdAccordCadre['V']) . " - " . strval($zone_marche->ConditionsExecution->SiretPAAccordCadre['V']);
                $info['date_notification'] = strval($zone_marche->DateNotification['V']);
            }
        }

        //PES_PJ
        if ($info['is_pj']) {
            $info[self::ID_PJ] = $this->getValueFromXPath($xml, "//PES_PJ/PJ/IdUnique/@V");
        }

        return $info;
    }

    /**
     * @param $pes_aller_path
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function getPJXML($pes_aller_path)
    {
        $xml = $this->getSimpleXMLFromFile($pes_aller_path);

        return $xml->{'PES_PJ'}->{'PJ'};
    }

    private function getNatureMarche($is_concession, $zone_marche)
    {

        $nature_contrat =  [
            "01" => "01 : Marché",
            "02" => "02 : Marché De Partenariat",
            "03" => "03 : Accord Cadre À Bons De Commande",
            "04" => "04 : Accord Cadre Avec Marché Subséquent Ou Mixte",
            "05" => "05 : Marché Subséquent",
            "06" => "06 : Complémentaire",
            "07" => "07 : Autre",
        ];
        $nature_concession =  [
            "01" => "01 : Concession de travaux",
            "02" => "02 : Concession de service",
            "03" => "03 : Concession de service public",
            "04" => "04 : Délégation de service public",
        ];
        if ($is_concession) {
            return $nature_concession[strval($zone_marche->NatureConcession['V'])];
        }
        return $nature_contrat[strval($zone_marche->NatureMarche['V'])];
    }

    private function getProcedureMarche($is_concession, $zone_marche)
    {

        $procedure_contrat =  [
            "01" => "01 : Procédure adaptée",
            "02" => "02 : Appel d'offres ouvert",
            "03" => "03 : Appel d'offres restreint",
            "04" => "04 : Procédure concurrentielle avec négociation",
            "05" => "05 : Procédure négociée avec mise en concurrence préalable",
            "06" => "06 : Marché négocié sans publicité ni mise en concurrence",
            "07" => "07 : Dialogue compétitif",
            "08" => "08 : Concours",
            "09" => "09 : Système d'acquisition dynamique",
            "10" => "10 : Autre",

        ];
        $procedure_concession =  [
            "01" => "01 : Procédure négociée ouverte",
            "02" => "02 : Procédure non négociée ouverte",
            "03" => "03 : Procédure négociée restreinte",
            "04" => "04 : Procédure non négociée restreinte",
        ];
        if ($is_concession) {
            return $procedure_concession[strval($zone_marche->ProcedureConcession['V'])];
        }
        return $procedure_contrat[strval($zone_marche->ProcedureMarche['V'])];
    }

    private function getAcheteur($is_concession, $zone_marche)
    {

        $result = [];

        if ($is_concession) {
            foreach ($zone_marche->Concessionnaires->Concessionnaire as $concessionnaire) {
                $chaine = strval($concessionnaire->IdConcessionnaire['V']);
                $chaine .= " - ";
                $chaine .= strval($concessionnaire->DenominationSociale['V']);
                $result[] = $chaine;
            }
        } else {
            foreach ($zone_marche->Acheteurs->Acheteur as $acheteur) {
                $chaine = strval($acheteur->IdAcheteur['V']);
                $chaine .= " - ";
                $chaine .= strval($acheteur->NomAcheteur['V']);
                $result[] = $chaine;
            }
        }
        return $result;
    }

    private function getTitulaire($is_concession, $zone_marche)
    {

        $result = [];

        if ($is_concession) {
            return $result;
        }

        $type_titulaire =  [
            "01" => "Titulaire du marché",
            "02" => "Co-traitant",
        ];

        foreach ($zone_marche->Operateurs->Titulaire as $titulaire) {
            $chaine = $type_titulaire[strval($titulaire->TypeTitulaireMarche['V'])];
            $chaine .= ": ";
            $chaine .= strval($titulaire->IdTitulaire['V']);
            $chaine .= " - ";
            $chaine .= strval($titulaire->DenominationSociale['V']);
            $result[] = $chaine;
        }

        return $result;
    }
}
